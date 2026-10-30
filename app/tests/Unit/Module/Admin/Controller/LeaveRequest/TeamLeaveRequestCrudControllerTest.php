<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\LeaveRequest\TeamLeaveRequestCrudController;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

it('attributes a batch rejection to the current app user', function () {
    $approverId = Uuid::uuid4();
    $approver = new User(
        id: $approverId,
        firstName: 'Admin',
        lastName: 'User',
        email: 'admin@example.com',
        password: 'password',
        workingDays: [1, 2, 3, 4, 5],
    );
    $leaveRequest = LeaveRequestDTOFixture::create([
        'status' => LeaveRequestStatusEnum::Pending,
        'user' => UserDTOFixture::create(['id' => Uuid::uuid4()->toString()]),
    ]);
    $leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $leaveRequestFacade->expects('getById')->once()->with($leaveRequest->id->toString())->andReturn($leaveRequest);
    $leaveRequestFacade
        ->expects('updateAndRestoreBalanceIfNeeded')
        ->once()
        ->withArgs(fn ($dto, $source, $actorUserId): bool => $leaveRequest === $dto
            && LeaveRequestStatusEnum::Rejected === $dto->status
            && BalanceLedgerSourceEnum::App === $source
            && $approverId->toString() === $actorUserId)
        ->andReturn(true);

    $controller = new class (
        mock(UrlGeneratorInterface::class),
        mock(CsrfTokenManagerInterface::class),
        $leaveRequestFacade,
        $approver,
    ) extends TeamLeaveRequestCrudController {
        public function __construct(
            UrlGeneratorInterface $urlGenerator,
            CsrfTokenManagerInterface $csrfTokenManager,
            LeaveRequestFacadeInterface $leaveRequestFacade,
            private readonly User $testUser,
        ) {
            parent::__construct($urlGenerator, $csrfTokenManager, $leaveRequestFacade);
        }

        protected function getUser(): User
        {
            return $this->testUser;
        }

        protected function isAdmin(): bool
        {
            return true;
        }

        protected function isCsrfTokenValid(string $id, ?string $token): bool
        {
            return true;
        }

        protected function addFlash(string $type, mixed $message): void
        {
        }

        protected function redirectToIndex(): Response
        {
            return new RedirectResponse('/admin');
        }
    };

    $batchAction = new BatchActionDto(
        'batchReject',
        [$leaveRequest->id->toString()],
        LeaveRequest::class,
        '/admin',
        'token',
    );
    $context = (new ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();
    assert($context instanceof AdminContext);

    $response = $controller->batchReject($context, $batchAction);

    expect($response->getTargetUrl())->toBe('/admin');
});
