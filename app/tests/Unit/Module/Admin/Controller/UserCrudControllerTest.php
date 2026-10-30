<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\UserCrudController;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Service\RoleTranslator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

beforeEach(function (): void {
    $this->roleTranslator = mock(RoleTranslator::class);
    $this->passwordHasher = mock(UserPasswordHasherInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->csrfTokenManager = mock(CsrfTokenManagerInterface::class);
    $this->appSettingsFacade = mock(AppSettingsFacadeInterface::class);
    $this->userFacade = mock(UserFacadeInterface::class);
});

it('uses defaultAnnualAllowance from settings when creating a new user', function (): void {
    $this->appSettingsFacade->allows('defaultAnnualAllowance')->andReturn(30);

    $controller = new UserCrudController(
        roleTranslator: $this->roleTranslator,
        passwordHasher: $this->passwordHasher,
        urlGenerator: $this->urlGenerator,
        csrfTokenManager: $this->csrfTokenManager,
        appSettingsFacade: $this->appSettingsFacade,
        userFacade: $this->userFacade,
    );

    $user = $controller->createEntity(User::class);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->annualLeaveAllowance)->toBe(30)
        ->and($user->currentLeaveBalance)->toBe(30);
});

it('persists a new user and opens its initial period atomically', function (): void {
    $user = new User(
        id: Ramsey\Uuid\Uuid::uuid4(),
        firstName: 'New',
        lastName: 'User',
        email: 'new@example.com',
        password: 'password',
    );
    $connection = mock(Connection::class);
    $entityManager = mock(EntityManagerInterface::class);
    $entityManager->expects('getConnection')->once()->andReturn($connection);
    $connection->expects('isTransactionActive')->once()->andReturn(false);
    $entityManager->expects('beginTransaction')->once();
    $entityManager->expects('persist')->once()->with($user);
    $entityManager->expects('flush')->once();
    $this->userFacade->expects('openInitialLeavePeriod')->once()->with($user->id->toString(), true);
    $entityManager->expects('commit')->once();
    $entityManager->expects('rollback')->never();

    $controller = new UserCrudController(
        roleTranslator: $this->roleTranslator,
        passwordHasher: $this->passwordHasher,
        urlGenerator: $this->urlGenerator,
        csrfTokenManager: $this->csrfTokenManager,
        appSettingsFacade: $this->appSettingsFacade,
        userFacade: $this->userFacade,
    );

    $controller->persistEntity($entityManager, $user);
});

it('schedules the first balance reset for 1 January next year when creating a new user', function (): void {
    $this->appSettingsFacade->allows('defaultAnnualAllowance')->andReturn(30);

    $controller = new UserCrudController(
        roleTranslator: $this->roleTranslator,
        passwordHasher: $this->passwordHasher,
        urlGenerator: $this->urlGenerator,
        csrfTokenManager: $this->csrfTokenManager,
        appSettingsFacade: $this->appSettingsFacade,
        userFacade: $this->userFacade,
    );

    $user = $controller->createEntity(User::class);

    expect($user->absenceBalanceResetDay->format('Y-m-d'))->toBe(sprintf('%d-01-01', (int) date('Y') + 1))
        ->and($user->absenceBalanceResetDay > new DateTimeImmutable('today'))->toBeTrue();
});

it('rolls back the user insert when initial period creation fails', function (): void {
    $user = new User(
        id: Ramsey\Uuid\Uuid::uuid4(),
        firstName: 'New',
        lastName: 'User',
        email: 'new@example.com',
        password: 'password',
    );
    $connection = mock(Connection::class);
    $entityManager = mock(EntityManagerInterface::class);
    $entityManager->expects('getConnection')->once()->andReturn($connection);
    $connection->expects('isTransactionActive')->once()->andReturn(false);
    $entityManager->expects('beginTransaction')->once();
    $entityManager->expects('persist')->once()->with($user);
    $entityManager->expects('flush')->once();
    $this->userFacade->expects('openInitialLeavePeriod')->once()->andThrow(new RuntimeException('boom'));
    $entityManager->expects('commit')->never();
    $entityManager->expects('rollback')->once();

    $controller = new UserCrudController(
        roleTranslator: $this->roleTranslator,
        passwordHasher: $this->passwordHasher,
        urlGenerator: $this->urlGenerator,
        csrfTokenManager: $this->csrfTokenManager,
        appSettingsFacade: $this->appSettingsFacade,
        userFacade: $this->userFacade,
    );

    expect(fn () => $controller->persistEntity($entityManager, $user))->toThrow(RuntimeException::class, 'boom');
});

it('joins an existing transaction without committing it', function (): void {
    $user = new User(
        id: Ramsey\Uuid\Uuid::uuid4(),
        firstName: 'New',
        lastName: 'User',
        email: 'new@example.com',
        password: 'password',
    );
    $connection = mock(Connection::class);
    $entityManager = mock(EntityManagerInterface::class);
    $entityManager->expects('getConnection')->once()->andReturn($connection);
    $connection->expects('isTransactionActive')->once()->andReturn(true);
    $entityManager->expects('beginTransaction')->never();
    $entityManager->expects('persist')->once()->with($user);
    $entityManager->expects('flush')->once();
    $this->userFacade->expects('openInitialLeavePeriod')->once()->with($user->id->toString(), true);
    $entityManager->expects('commit')->never();
    $entityManager->expects('rollback')->never();

    $controller = new UserCrudController(
        roleTranslator: $this->roleTranslator,
        passwordHasher: $this->passwordHasher,
        urlGenerator: $this->urlGenerator,
        csrfTokenManager: $this->csrfTokenManager,
        appSettingsFacade: $this->appSettingsFacade,
        userFacade: $this->userFacade,
    );

    $controller->persistEntity($entityManager, $user);
});

it('leaves rollback to the owner of an existing transaction', function (): void {
    $user = new User(
        id: Ramsey\Uuid\Uuid::uuid4(),
        firstName: 'New',
        lastName: 'User',
        email: 'new@example.com',
        password: 'password',
    );
    $connection = mock(Connection::class);
    $entityManager = mock(EntityManagerInterface::class);
    $entityManager->expects('getConnection')->once()->andReturn($connection);
    $connection->expects('isTransactionActive')->once()->andReturn(true);
    $entityManager->expects('beginTransaction')->never();
    $entityManager->expects('persist')->once()->with($user);
    $entityManager->expects('flush')->once();
    $this->userFacade->expects('openInitialLeavePeriod')->once()->andThrow(new RuntimeException('boom'));
    $entityManager->expects('commit')->never();
    $entityManager->expects('rollback')->never();

    $controller = new UserCrudController(
        roleTranslator: $this->roleTranslator,
        passwordHasher: $this->passwordHasher,
        urlGenerator: $this->urlGenerator,
        csrfTokenManager: $this->csrfTokenManager,
        appSettingsFacade: $this->appSettingsFacade,
        userFacade: $this->userFacade,
    );

    expect(fn () => $controller->persistEntity($entityManager, $user))->toThrow(RuntimeException::class, 'boom');
});
