<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\LeavePeriod;
use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Doctrine\Repository\BalanceLedgerEntryRepository;
use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Enum\RoleEnum;
use App\Shared\Facade\UserFacadeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    self::bootKernel();
    $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    $this->userFacade = self::getContainer()->get(UserFacadeInterface::class);
    $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
});

it('shows the balance history page to a logged-in user', function (): void {
    expect($this->admin)->toBeInstanceOf(User::class);
    $this->userFacade->openInitialLeavePeriod($this->admin->id->toString());

    $client = static::createClient();
    $client->loginUser($this->admin);

    $crawler = $client->request('GET', '/app/balance-history');

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('.content-header h1')->text())->toContain('Balance History')
        ->and($crawler->filter('table tbody tr')->count())->toBeGreaterThan(0)
        ->and($crawler->filter('a[href="/app/balance-history"]')->count())->toBeGreaterThan(0);

    $dashboard = $client->request('GET', '/app/dashboard');
    expect($dashboard->filter('.stat-card a[href="/app/balance-history"]')->count())->toBe(1);
});

it('falls back to the open owned period when a foreign period is requested', function (): void {
    expect($this->admin)->toBeInstanceOf(User::class);
    $this->userFacade->openInitialLeavePeriod($this->admin->id->toString());
    $foreignUser = createBalanceHistoryTestUser($this->entityManager, 'foreign');
    $this->userFacade->openInitialLeavePeriod($foreignUser->id->toString());
    $foreignPeriod = $this->entityManager->getRepository(LeavePeriod::class)->findOneBy(['user' => $foreignUser]);
    $ownedPeriodId = null;
    foreach ($this->userFacade->getLeavePeriods($this->admin->id->toString()) as $period) {
        if ($period->isOpen()) {
            $ownedPeriodId = $period->id;
            break;
        }
    }
    expect($foreignPeriod)->toBeInstanceOf(LeavePeriod::class)
        ->and($ownedPeriodId)->not->toBeNull();
    $foreignMarker = sprintf('foreign-marker-%s', Uuid::uuid4()->toString());
    $this->entityManager->persist(new BalanceLedgerEntry(
        delta: 1,
        balanceBefore: 24,
        balanceAfter: 25,
        sequence: 2,
        id: Uuid::uuid4(),
        type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
        source: BalanceLedgerSourceEnum::App,
        user: $foreignUser,
        leavePeriod: $foreignPeriod,
        reason: $foreignMarker,
    ));
    $this->entityManager->flush();

    $client = static::createClient();
    $client->loginUser($this->admin);
    $crawler = $client->request('GET', '/app/balance-history?period='.$foreignPeriod->id->toString());

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('select[name="period"] option[selected]')->count())->toBe(1)
        ->and($crawler->filter('select[name="period"] option[selected]')->attr('value'))->toBe($ownedPeriodId)
        ->and($crawler->text())->not->toContain($foreignMarker);
});

it('shows an empty state when the user has no periods', function (): void {
    $user = createBalanceHistoryTestUser($this->entityManager, 'empty');

    $client = static::createClient();
    $client->loginUser($user);
    $crawler = $client->request('GET', '/app/balance-history');

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('.alert')->text())->toContain('Nothing here yet.')
        ->and($crawler->filter('table')->count())->toBe(0);
});

it('redirects unauthenticated users to login', function (): void {
    $client = static::createClient();
    $client->request('GET', '/app/balance-history');

    expect($client->getResponse()->getStatusCode())->toBe(302);
});

it('selects an owned closed period and renders its ledger newest first', function (): void {
    $user = createBalanceHistoryTestUser($this->entityManager, 'closed');
    $closedPeriod = new LeavePeriod(
        allowanceGranted: 24,
        openingBalance: 10,
        id: Uuid::uuid4(),
        user: $user,
        startDate: new DateTimeImmutable('2024-01-01'),
        endDate: new DateTimeImmutable('2025-01-01'),
        closingBalance: 12,
        closedAt: new DateTimeImmutable('2025-01-01'),
    );
    $olderMarker = sprintf('older-marker-%s', Uuid::uuid4()->toString());
    $newerMarker = sprintf('newer-marker-%s', Uuid::uuid4()->toString());
    $olderEntry = new BalanceLedgerEntry(
        delta: 1,
        balanceBefore: 10,
        balanceAfter: 11,
        sequence: 1,
        id: Uuid::uuid4(),
        type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
        source: BalanceLedgerSourceEnum::App,
        user: $user,
        leavePeriod: $closedPeriod,
        reason: $olderMarker,
    );
    $olderEntry->setCreatedAt(new DateTimeImmutable('2024-06-01 09:00:00'));
    $newerEntry = new BalanceLedgerEntry(
        delta: 1,
        balanceBefore: 11,
        balanceAfter: 12,
        sequence: 2,
        id: Uuid::uuid4(),
        type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
        source: BalanceLedgerSourceEnum::App,
        user: $user,
        leavePeriod: $closedPeriod,
        reason: $newerMarker,
    );
    $newerEntry->setCreatedAt(new DateTimeImmutable('2024-07-01 09:00:00'));
    $this->entityManager->persist($closedPeriod);
    $this->entityManager->persist($olderEntry);
    $this->entityManager->persist($newerEntry);
    $this->entityManager->flush();

    $client = static::createClient();
    $client->loginUser($user);
    $crawler = $client->request('GET', '/app/balance-history?period='.$closedPeriod->id->toString());
    $rows = $crawler->filter('table tbody tr')->each(fn ($row): string => $row->text());

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('select[name="period"] option[selected]')->attr('value'))->toBe($closedPeriod->id->toString())
        ->and($rows)->toHaveCount(2)
        ->and($rows[0])->toContain($newerMarker)
        ->and($rows[1])->toContain($olderMarker);
});

it('paginates older entries in both employee and admin history', function (): void {
    $user = createBalanceHistoryTestUser($this->entityManager, 'pagination', [RoleEnum::Admin->value]);
    $userId = $user->id->toString();
    $this->userFacade->openInitialLeavePeriod($userId);
    $period = $this->entityManager->getRepository(LeavePeriod::class)->findOneBy(['user' => $user]);
    expect($period)->toBeInstanceOf(LeavePeriod::class);

    $ledgerRepository = self::getContainer()->get(BalanceLedgerEntryRepository::class);
    for ($entryNumber = 1; $entryNumber <= 51; ++$entryNumber) {
        $ledgerRepository->add(new BalanceLedgerEntryDTO(
            id: Uuid::uuid4()->toString(),
            userId: $userId,
            leavePeriodId: $period->id->toString(),
            delta: 1,
            balanceBefore: $entryNumber - 1,
            balanceAfter: $entryNumber,
            type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
            source: BalanceLedgerSourceEnum::App,
            createdAt: new DateTimeImmutable(),
            reason: sprintf('Pagination marker %03d', $entryNumber),
        ));
    }

    $client = static::createClient();
    $client->loginUser($user);

    foreach (['/app/balance-history', sprintf('/app/my-organization/%s/balance-history', $userId)] as $historyUrl) {
        $firstPage = $client->request('GET', $historyUrl);
        expect($client->getResponse()->getStatusCode())->toBe(200)
            ->and($firstPage->text())->toContain('Pagination marker 051')
            ->and($firstPage->text())->not->toContain('Pagination marker 001')
            ->and($firstPage->filter('a[data-balance-page="next"]')->count())->toBe(1);

        $secondPage = $client->request('GET', sprintf('%s?page=2', $historyUrl));
        expect($client->getResponse()->getStatusCode())->toBe(200)
            ->and($secondPage->text())->toContain('Pagination marker 001')
            ->and($secondPage->filter('a[data-balance-page="previous"]')->count())->toBe(1);

        foreach (['-1', '3', (string) PHP_INT_MAX] as $invalidPage) {
            $client->request('GET', sprintf('%s?period=%s&page=%s', $historyUrl, $period->id->toString(), $invalidPage));
            expect($client->getResponse()->isRedirect())->toBeTrue()
                ->and($client->getResponse()->headers->get('Location'))->toContain('period='.$period->id->toString())
                ->and($client->getResponse()->headers->get('Location'))->not->toContain('page=');
        }
    }

    $adminPage = $client->request('GET', sprintf('/app/my-organization/%s/balance-history', $userId));
    expect($adminPage->text())->toContain('Where this employee’s leave days went, newest change first.');
});

/**
 * @param string[] $roles
 */
function createBalanceHistoryTestUser(EntityManagerInterface $entityManager, string $prefix, array $roles = [RoleEnum::User->value]): User
{
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'Balance',
        lastName: 'History',
        email: sprintf('%s-%s@example.com', $prefix, Uuid::uuid4()->toString()),
        password: 'password',
        isActive: true,
        roles: $roles,
    );
    $entityManager->persist($user);
    $entityManager->flush();

    return $user;
}
