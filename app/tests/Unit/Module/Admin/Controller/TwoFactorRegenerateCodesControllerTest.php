<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\TwoFactorRegenerateCodesController;
use App\Shared\Facade\UserFacadeInterface;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

beforeEach(function (): void {
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->passwordHasher = mock(UserPasswordHasherInterface::class);

    $this->user = new User(
        id: Uuid::uuid4(),
        firstName: 'Jane',
        lastName: 'Doe',
        email: 'jane@whoisooo.app',
        password: 'hashed',
        isTwoFactorEnabled: true,
    );

    $token = mock(TokenInterface::class);
    $token->allows('getUser')->andReturn($this->user);
    $tokenStorage = mock(TokenStorageInterface::class);
    $tokenStorage->allows('getToken')->andReturn($token);

    $this->csrfManager = mock(CsrfTokenManagerInterface::class);
    $this->csrfManager->allows('isTokenValid')->andReturn(true);

    $urlGenerator = mock(UrlGeneratorInterface::class);
    $urlGenerator->allows('generate')->andReturnUsing(fn (string $name) => '/'.$name);

    $this->flashBag = mock(FlashBagInterface::class);

    $this->sessionStore = [];
    $this->session = mock(Session::class);
    $this->session->allows('getFlashBag')->andReturn($this->flashBag);
    $this->session->allows('set')->andReturnUsing(function (string $key, mixed $value): void {
        $this->sessionStore[$key] = $value;
    });
    $this->session->allows('get')->andReturnUsing(fn (string $key) => $this->sessionStore[$key] ?? null);

    $this->request = Request::create('/app/settings/two-factor/regenerate-codes', 'POST');
    $this->request->setSession($this->session);
    $this->request->request->set('_csrf_token', 'valid-token');
    $this->request->request->set('_password', 'correct-password');

    $requestStack = new RequestStack();
    $requestStack->push($this->request);

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('security.token_storage')->andReturn($tokenStorage);
    $container->allows('get')->with('security.csrf.token_manager')->andReturn($this->csrfManager);
    $container->allows('get')->with('request_stack')->andReturn($requestStack);
    $container->allows('get')->with('router')->andReturn($urlGenerator);

    $this->controller = new TwoFactorRegenerateCodesController(
        userFacade: $this->userFacade,
        passwordHasher: $this->passwordHasher,
    );
    $this->controller->setContainer($container);
});

it('redirects to account security settings when 2FA is not enabled', function (): void {
    $this->user->isTwoFactorEnabled = false;

    $this->userFacade->shouldNotReceive('regenerateBackupCodes');

    $response = ($this->controller)($this->request);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe('/app_settings_account_security');
});

it('redirects with danger flash on invalid CSRF token', function (): void {
    $csrfManager = mock(CsrfTokenManagerInterface::class);
    $csrfManager->allows('isTokenValid')->andReturn(false);

    $tokenStorage = mock(TokenStorageInterface::class);
    $token = mock(TokenInterface::class);
    $token->allows('getUser')->andReturn($this->user);
    $tokenStorage->allows('getToken')->andReturn($token);

    $urlGenerator = mock(UrlGeneratorInterface::class);
    $urlGenerator->allows('generate')->andReturnUsing(fn (string $name) => '/'.$name);

    $requestStack = new RequestStack();
    $requestStack->push($this->request);

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('security.token_storage')->andReturn($tokenStorage);
    $container->allows('get')->with('security.csrf.token_manager')->andReturn($csrfManager);
    $container->allows('get')->with('request_stack')->andReturn($requestStack);
    $container->allows('get')->with('router')->andReturn($urlGenerator);

    $this->flashBag->expects('add')->with('danger', 'error.invalid_csrf_token')->once();
    $this->userFacade->shouldNotReceive('regenerateBackupCodes');

    $controller = new TwoFactorRegenerateCodesController(
        userFacade: $this->userFacade,
        passwordHasher: $this->passwordHasher,
    );
    $controller->setContainer($container);

    $response = ($controller)($this->request);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe('/app_settings_account_security');
});

it('redirects with danger flash when password is invalid', function (): void {
    $this->passwordHasher->expects('isPasswordValid')
        ->with($this->user, 'correct-password')
        ->andReturn(false);

    $this->flashBag->expects('add')->with('danger', 'settings.two_factor.disable.error.invalid_password')->once();
    $this->userFacade->shouldNotReceive('regenerateBackupCodes');

    $response = ($this->controller)($this->request);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe('/app_settings_account_security');
});

it('regenerates backup codes, stores them in session, and redirects to recovery codes page', function (): void {
    $codes = ['code-1', 'code-2', 'code-3'];

    $this->passwordHasher->expects('isPasswordValid')
        ->with($this->user, 'correct-password')
        ->andReturn(true);

    $this->userFacade->expects('regenerateBackupCodes')
        ->with($this->user->id->toString())
        ->andReturn($codes);

    $response = ($this->controller)($this->request);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe('/app_two_factor_recovery_codes')
        ->and($this->session->get('2fa_recovery_codes'))->toBe($codes);
});
