<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\LeaveRequest\LeaveRequestActionController;
use App\Shared\Enum\LeaveRequestPermission;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\EmailFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);

    $this->currentUserId = Uuid::uuid4();
    $this->currentUser = new User(
        id: $this->currentUserId,
        firstName: 'Current',
        lastName: 'User',
        email: 'current@ooo.com',
        password: 'password',
        workingDays: [1, 2, 3, 4, 5],
    );

    $this->authChecker = mock(AuthorizationCheckerInterface::class);
    $this->csrfManager = mock(CsrfTokenManagerInterface::class);
    $this->csrfManager->allows('isTokenValid')->andReturn(true);

    $token = mock(TokenInterface::class);
    $token->allows('getUser')->andReturn($this->currentUser);

    $tokenStorage = mock(TokenStorageInterface::class);
    $tokenStorage->allows('getToken')->andReturn($token);

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('security.authorization_checker')->andReturn($this->authChecker);
    $container->allows('get')->with('security.csrf.token_manager')->andReturn($this->csrfManager);
    $container->allows('get')->with('security.token_storage')->andReturn($tokenStorage);

    $this->emailFacade = mock(EmailFacadeInterface::class);

    $this->controller = new LeaveRequestActionController(
        $this->urlGenerator,
        $this->leaveRequestFacade,
        $this->translator,
        $this->emailFacade,
    );
    $this->controller->setContainer($container);
});

function jsonRequest(string $method = 'POST'): Request
{
    $request = Request::create('/', $method);
    $request->headers->set('Accept', 'application/json');
    $request->request->set('_token', 'valid-token');

    return $request;
}

describe('withdraw', function (): void {
    it('returns 404 when leave request not found', function (): void {
        $this->leaveRequestFacade->expects('getById')->with('non-existent-id')->andReturn(null);

        $this->controller->withdraw(jsonRequest(), 'non-existent-id');
    })->throws(NotFoundHttpException::class);

    it('denies access when current user is not the owner', function (): void {
        $otherUserId = Uuid::uuid4()->toString();
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => $otherUserId]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Withdraw->value === $attr && $subject === $leaveRequest)->andReturn(false);

        $response = $this->controller->withdraw(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    it('allows owner to withdraw their pending request', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => $this->currentUserId->toString()]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Withdraw->value === $attr && $subject === $leaveRequest)->andReturn(true);
        $this->leaveRequestFacade->expects('updateAndRestoreBalanceIfNeeded')
            ->withArgs(fn (LeaveRequestDTO $dto) => LeaveRequestStatusEnum::Withdrawn === $dto->status)
            ->once();
        $this->emailFacade->expects('sendLeaveRequestWithdrawnEmail')->once();

        $response = $this->controller->withdraw(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_OK)
            ->and(json_decode($response->getContent(), true)['status'])->toBe('withdrawn');
    });

    it('allows owner to withdraw their approved request', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Approved,
            'user' => UserDTOFixture::create(['id' => $this->currentUserId->toString()]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Withdraw->value === $attr && $subject === $leaveRequest)->andReturn(true);
        $this->leaveRequestFacade->expects('updateAndRestoreBalanceIfNeeded')->once();
        $this->emailFacade->expects('sendLeaveRequestWithdrawnEmail')->once();

        $response = $this->controller->withdraw(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    });

    it('rejects withdrawal of already rejected request', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Rejected,
            'user' => UserDTOFixture::create(['id' => $this->currentUserId->toString()]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Withdraw->value === $attr && $subject === $leaveRequest)->andReturn(true);

        $response = $this->controller->withdraw(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    });
});

describe('approve', function (): void {
    it('denies access for regular users', function (): void {
        $otherUserId = Uuid::uuid4()->toString();
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => $otherUserId]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Manage->value === $attr && $subject === $leaveRequest)->andReturn(false);

        $response = $this->controller->approve(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    it('denies self-approval', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => $this->currentUserId->toString()]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Manage->value === $attr && $subject === $leaveRequest)->andReturn(false);

        $response = $this->controller->approve(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    it('denies manager approving non-direct-report request', function (): void {
        $otherManagerId = Uuid::uuid4()->toString();
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create([
                'id' => Uuid::uuid4()->toString(),
                'managerId' => $otherManagerId,
            ]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Manage->value === $attr && $subject === $leaveRequest)->andReturn(false);

        $response = $this->controller->approve(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    it('allows manager to approve direct report request', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create([
                'id' => Uuid::uuid4()->toString(),
                'managerId' => $this->currentUserId->toString(),
            ]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Manage->value === $attr && $subject === $leaveRequest)->andReturn(true);
        $this->leaveRequestFacade->expects('update')
            ->withArgs(fn (LeaveRequestDTO $dto) => LeaveRequestStatusEnum::Approved === $dto->status && null !== $dto->approvedBy)
            ->once();
        $this->emailFacade->expects('sendLeaveRequestApprovedEmail')->once();

        $response = $this->controller->approve(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_OK)
            ->and(json_decode($response->getContent(), true)['status'])->toBe('approved');
    });

    it('allows admin to approve any request', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => Uuid::uuid4()->toString()]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Manage->value === $attr && $subject === $leaveRequest)->andReturn(true);
        $this->leaveRequestFacade->expects('update')->once();
        $this->emailFacade->expects('sendLeaveRequestApprovedEmail')->once();

        $response = $this->controller->approve(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    });

    it('rejects approving non-pending request', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Approved,
            'user' => UserDTOFixture::create(['id' => Uuid::uuid4()->toString()]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Manage->value === $attr && $subject === $leaveRequest)->andReturn(true);

        $response = $this->controller->approve(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    });
});

describe('reject', function (): void {
    it('denies access for regular users', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => Uuid::uuid4()->toString()]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Manage->value === $attr && $subject === $leaveRequest)->andReturn(false);

        $response = $this->controller->reject(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    it('allows manager to reject direct report request', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create([
                'id' => Uuid::uuid4()->toString(),
                'managerId' => $this->currentUserId->toString(),
            ]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Manage->value === $attr && $subject === $leaveRequest)->andReturn(true);
        $this->leaveRequestFacade->expects('updateAndRestoreBalanceIfNeeded')
            ->withArgs(fn (LeaveRequestDTO $dto) => LeaveRequestStatusEnum::Rejected === $dto->status && null !== $dto->approvedBy)
            ->once();
        $this->emailFacade->expects('sendLeaveRequestRejectedEmail')->once();

        $response = $this->controller->reject(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_OK)
            ->and(json_decode($response->getContent(), true)['status'])->toBe('rejected');
    });

    it('denies self-rejection', function (): void {
        $leaveRequest = LeaveRequestDTOFixture::create([
            'status' => LeaveRequestStatusEnum::Pending,
            'user' => UserDTOFixture::create(['id' => $this->currentUserId->toString()]),
        ]);

        $this->leaveRequestFacade->expects('getById')->andReturn($leaveRequest);
        $this->authChecker->expects('isGranted')->withArgs(fn (string $attr, mixed $subject) => LeaveRequestPermission::Manage->value === $attr && $subject === $leaveRequest)->andReturn(false);

        $response = $this->controller->reject(jsonRequest(), $leaveRequest->id->toString());

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });
});
