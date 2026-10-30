<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\LeavePeriod;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\UserCrudController;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Service\RoleTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

beforeEach(function (): void {
    self::bootKernel();
    $this->em = self::getContainer()->get(EntityManagerInterface::class);
});

afterEach(function (): void {
    self::ensureKernelShutdown();
});

it('persists a user, one initial period, and one opening entry atomically and idempotently', function (): void {
    $controller = self::getContainer()->get(UserCrudController::class);
    $userFacade = self::getContainer()->get(UserFacadeInterface::class);
    $user = createInitialPeriodTestUser();

    $controller->persistEntity($this->em, $user);
    $userFacade->openInitialLeavePeriod($user->id->toString());
    $this->em->clear();

    $persistedUser = $this->em->find(User::class, $user->id);
    $periods = $this->em->getRepository(LeavePeriod::class)->findBy(['user' => $user->id]);
    $entries = $this->em->getRepository(BalanceLedgerEntry::class)->findBy([
        'user' => $user->id,
        'type' => BalanceLedgerEntryTypeEnum::OpeningBalance,
    ]);

    expect($persistedUser)->toBeInstanceOf(User::class)
        ->and($periods)->toHaveCount(1)
        ->and($entries)->toHaveCount(1)
        ->and($entries[0]->leavePeriod->id->toString())->toBe($periods[0]->id->toString())
        ->and($entries[0]->delta)->toBe($user->currentLeaveBalance);
});

it('uses the allowance entered for a new user when opening the initial period', function (): void {
    $controller = self::getContainer()->get(UserCrudController::class);
    $user = createInitialPeriodTestUser();
    $user->currentLeaveBalance = 26;
    $user->annualLeaveAllowance = 12;

    $controller->persistEntity($this->em, $user);
    $this->em->clear();

    $persistedUser = $this->em->find(User::class, $user->id);
    $period = $this->em->getRepository(LeavePeriod::class)->findOneBy(['user' => $user->id]);
    $entry = $this->em->getRepository(BalanceLedgerEntry::class)->findOneBy([
        'user' => $user->id,
        'type' => BalanceLedgerEntryTypeEnum::OpeningBalance,
    ]);

    expect($persistedUser)->toBeInstanceOf(User::class)
        ->and($period)->toBeInstanceOf(LeavePeriod::class)
        ->and($entry)->toBeInstanceOf(BalanceLedgerEntry::class)
        ->and($persistedUser->currentLeaveBalance)->toBe(12)
        ->and($period->openingBalance)->toBe(12)
        ->and($entry->delta)->toBe(12);
});

it('rolls back the real user insert when initial period creation fails', function (): void {
    $userFacade = mock(UserFacadeInterface::class);
    $userFacade->expects('openInitialLeavePeriod')->once()->andThrow(new RuntimeException('boom'));
    $controller = new UserCrudController(
        roleTranslator: mock(RoleTranslator::class),
        passwordHasher: mock(UserPasswordHasherInterface::class),
        urlGenerator: mock(UrlGeneratorInterface::class),
        csrfTokenManager: mock(CsrfTokenManagerInterface::class),
        appSettingsFacade: mock(AppSettingsFacadeInterface::class),
        userFacade: $userFacade,
    );
    $user = createInitialPeriodTestUser();

    expect(fn () => $controller->persistEntity($this->em, $user))->toThrow(RuntimeException::class, 'boom');

    $this->em->clear();

    expect($this->em->find(User::class, $user->id))->toBeNull();
});

function createInitialPeriodTestUser(): User
{
    return new User(
        id: Uuid::uuid4(),
        firstName: 'Initial',
        lastName: 'Period',
        email: sprintf('initial-period-%s@example.com', Uuid::uuid4()->toString()),
        password: 'password',
        annualLeaveAllowance: 27,
        currentLeaveBalance: 27,
        absenceBalanceResetDay: new DateTimeImmutable('2027-01-01'),
    );
}
