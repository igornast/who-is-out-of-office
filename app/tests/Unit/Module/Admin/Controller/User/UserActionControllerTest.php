<?php

declare(strict_types=1);

use App\Module\Admin\Controller\User\UserActionController;
use App\Shared\Facade\EmailFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->emailFacade = mock(EmailFacadeInterface::class);
    $this->logger = mock(LoggerInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);

    $this->authChecker = mock(AuthorizationCheckerInterface::class);
    $this->csrfManager = mock(CsrfTokenManagerInterface::class);
    $this->csrfManager->allows('isTokenValid')->andReturn(true);

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('security.authorization_checker')->andReturn($this->authChecker);
    $container->allows('get')->with('security.csrf.token_manager')->andReturn($this->csrfManager);

    $this->controller = new UserActionController(
        $this->userFacade,
        $this->emailFacade,
        $this->translator,
        $this->logger,
    );
    $this->controller->setContainer($container);
});

function resetPasswordRequest(): Request
{
    $request = Request::create('/', 'POST');
    $request->headers->set('Accept', 'application/json');
    $request->request->set('_token', 'valid-token');

    return $request;
}

describe('resetPassword', function (): void {
    it('returns 404 when user not found', function (): void {
        $this->userFacade->expects('getUser')->with('non-existent-id')->andReturn(null);

        $this->controller->resetPassword(resetPasswordRequest(), 'non-existent-id');
    })->throws(NotFoundHttpException::class);

    it('returns 403 on invalid CSRF token', function (): void {
        $authChecker = mock(AuthorizationCheckerInterface::class);
        $csrfManager = mock(CsrfTokenManagerInterface::class);
        $csrfManager->allows('isTokenValid')->andReturn(false);

        $container = mock(ContainerInterface::class);
        $container->allows('has')->with('serializer')->andReturn(false);
        $container->allows('has')->andReturn(true);
        $container->allows('get')->with('security.authorization_checker')->andReturn($authChecker);
        $container->allows('get')->with('security.csrf.token_manager')->andReturn($csrfManager);

        $controller = new UserActionController(
            $this->userFacade,
            $this->emailFacade,
            $this->translator,
            $this->logger,
        );
        $controller->setContainer($container);

        $userId = Uuid::uuid4()->toString();
        $userDTO = UserDTOFixture::create(['id' => $userId, 'isActive' => true]);

        $this->userFacade->expects('getUser')->with($userId)->andReturn($userDTO);

        $response = $controller->resetPassword(resetPasswordRequest(), $userId);

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
            ->and(json_decode($response->getContent(), true)['success'])->toBeFalse();
    });

    it('returns 422 when user is inactive', function (): void {
        $userId = Uuid::uuid4()->toString();
        $userDTO = UserDTOFixture::create(['id' => $userId, 'isActive' => false]);

        $this->userFacade->expects('getUser')->with($userId)->andReturn($userDTO);

        $response = $this->controller->resetPassword(resetPasswordRequest(), $userId);

        expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->and(json_decode($response->getContent(), true)['success'])->toBeFalse()
            ->and(json_decode($response->getContent(), true)['message'])->toBe('user.action.error.user_inactive');
    });

    it('returns 422 when token creation fails', function (): void {
        $userId = Uuid::uuid4()->toString();
        $userDTO = UserDTOFixture::create(['id' => $userId, 'isActive' => true, 'email' => 'test@whoisooo.app']);

        $this->userFacade->expects('getUser')->with($userId)->andReturn($userDTO);
        $this->userFacade->expects('createPasswordResetToken')->with('test@whoisooo.app')->andReturn(null);

        $response = $this->controller->resetPassword(resetPasswordRequest(), $userId);

        expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->and(json_decode($response->getContent(), true)['success'])->toBeFalse()
            ->and(json_decode($response->getContent(), true)['message'])->toBe('user.action.error.reset_failed');
    });

    it('sends password reset email to active user', function (): void {
        $userId = Uuid::uuid4()->toString();
        $userDTO = UserDTOFixture::create(['id' => $userId, 'isActive' => true, 'email' => 'test@whoisooo.app']);

        $this->userFacade->expects('getUser')->with($userId)->andReturn($userDTO);
        $this->userFacade->expects('createPasswordResetToken')->with('test@whoisooo.app')->andReturn('reset-token-abc');
        $this->emailFacade->expects('sendPasswordResetEmail')->with('test@whoisooo.app', 'reset-token-abc')->once();

        $response = $this->controller->resetPassword(resetPasswordRequest(), $userId);

        expect($response->getStatusCode())->toBe(Response::HTTP_OK)
            ->and(json_decode($response->getContent(), true)['success'])->toBeTrue()
            ->and(json_decode($response->getContent(), true)['message'])->toBe('user.action.success.reset_password');
    });

    it('returns 500 and logs error when email sending fails', function (): void {
        $userId = Uuid::uuid4()->toString();
        $userDTO = UserDTOFixture::create(['id' => $userId, 'isActive' => true, 'email' => 'test@whoisooo.app']);

        $this->userFacade->expects('getUser')->with($userId)->andReturn($userDTO);
        $this->userFacade->expects('createPasswordResetToken')->with('test@whoisooo.app')->andReturn('reset-token-abc');
        $this->emailFacade->expects('sendPasswordResetEmail')->andThrow(new Symfony\Component\Messenger\Exception\HandlerFailedException(
            new Symfony\Component\Messenger\Envelope(new App\Infrastructure\Email\Message\SendPasswordResetEmailMessage('test@whoisooo.app', 'reset-token-abc')),
            [new RuntimeException('SMTP down')],
        ));
        $this->logger->expects('error')->once();

        $response = $this->controller->resetPassword(resetPasswordRequest(), $userId);

        expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->and(json_decode($response->getContent(), true)['success'])->toBeFalse()
            ->and(json_decode($response->getContent(), true)['message'])->toBe('user.action.error.email_failed');
    });
});
