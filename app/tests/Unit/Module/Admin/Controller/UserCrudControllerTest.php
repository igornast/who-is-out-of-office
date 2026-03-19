<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\UserCrudController;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Service\RoleTranslator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

beforeEach(function (): void {
    $this->roleTranslator = mock(RoleTranslator::class);
    $this->passwordHasher = mock(UserPasswordHasherInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->csrfTokenManager = mock(CsrfTokenManagerInterface::class);
    $this->appSettingsFacade = mock(AppSettingsFacadeInterface::class);
});

it('uses defaultAnnualAllowance from settings when creating a new user', function (): void {
    $this->appSettingsFacade->allows('defaultAnnualAllowance')->andReturn(30);

    $controller = new UserCrudController(
        roleTranslator: $this->roleTranslator,
        passwordHasher: $this->passwordHasher,
        urlGenerator: $this->urlGenerator,
        csrfTokenManager: $this->csrfTokenManager,
        appSettingsFacade: $this->appSettingsFacade,
    );

    $user = $controller->createEntity(User::class);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->annualLeaveAllowance)->toBe(30)
        ->and($user->currentLeaveBalance)->toBe(30);
});
