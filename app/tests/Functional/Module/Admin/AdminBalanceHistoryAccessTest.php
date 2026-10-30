<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

it('lets an admin read any user balance history', function (): void {
    $client = static::createClient();
    $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
    $userFacade = $client->getContainer()->get(UserFacadeInterface::class);
    $admin = findBalanceHistoryUser($entityManager, 'admin@whoisooo.app');
    $target = findBalanceHistoryUser($entityManager, 'manager@whoisooo.app');
    $reason = sprintf('Admin audit marker %s', Uuid::uuid4()->toString());
    $userFacade->openInitialLeavePeriod($target->id->toString());
    $userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
        userId: $target->id->toString(),
        delta: 2,
        type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
        source: BalanceLedgerSourceEnum::App,
        reason: $reason,
        actorUserId: $admin->id->toString(),
    ));
    $entry = $entityManager->getRepository(BalanceLedgerEntry::class)->findOneBy(['reason' => $reason]);
    expect($entry)->toBeInstanceOf(BalanceLedgerEntry::class);

    $client->loginUser($admin);
    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/balance-history', $target->id->toString()));
    $tableText = $crawler->filter('table')->text();

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($crawler->filter('h1')->last()->text())->toContain(sprintf('%s %s', $target->firstName, $target->lastName))
        ->and($tableText)->toContain('Source')
        ->and($tableText)->toContain('Actor')
        ->and($tableText)->toContain('Before → After')
        ->and($tableText)->toContain('App')
        ->and($tableText)->toContain('System')
        ->and($tableText)->toContain($reason)
        ->and($tableText)->toContain('Hans Müller')
        ->and($tableText)->toContain(sprintf('%d → %d', $entry->balanceBefore, $entry->balanceAfter));

    $detail = $client->request('GET', '/app/dashboard/my-organization/'.$target->id->toString());
    expect($detail->filter('a[href="/app/my-organization/'.$target->id->toString().'/balance-history"]')->count())->toBeGreaterThan(0);

    $edit = $client->request('GET', '/app/dashboard/my-organization/'.$target->id->toString().'/edit');
    expect($edit->filter('a[href="/app/my-organization/'.$target->id->toString().'/balance-history"]')->count())->toBeGreaterThan(0);
});

it('lets a manager read a direct report balance history', function (): void {
    $client = static::createClient();
    $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
    $manager = findBalanceHistoryUser($entityManager, 'elena.novak@whoisooo.app');
    $target = findBalanceHistoryUser($entityManager, 'user@whoisooo.app');

    $client->loginUser($manager);
    $client->request('GET', sprintf('/app/my-organization/%s/balance-history', $target->id->toString()));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK);

    $client->request('GET', '/app/dashboard/team/members');
    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK);

    $detail = $client->request('GET', '/app/dashboard/team/members/'.$target->id->toString());
    expect($detail->filter('a[href="/app/my-organization/'.$target->id->toString().'/balance-history"]')->count())->toBeGreaterThan(0);
});

it('keeps the materialized reset boundary read only for an existing user', function (): void {
    $client = static::createClient();
    $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
    $admin = findBalanceHistoryUser($entityManager, 'admin@whoisooo.app');
    $target = findBalanceHistoryUser($entityManager, 'manager@whoisooo.app');

    $client->loginUser($admin);
    $crawler = $client->request('GET', '/app/dashboard/my-organization/'.$target->id->toString().'/edit');
    $resetField = $crawler->filter('input[name$="[absenceBalanceResetDay]"]');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($resetField->count())->toBe(1)
        ->and($resetField->attr('disabled'))->not->toBeNull();
});

it('shows the empty state when a user has no leave period', function (): void {
    $client = static::createClient();
    $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
    $admin = findBalanceHistoryUser($entityManager, 'admin@whoisooo.app');
    $target = new User(
        id: Uuid::uuid4(),
        firstName: 'No',
        lastName: 'History',
        email: sprintf('no-history-%s@example.com', Uuid::uuid4()->toString()),
        password: 'password',
        isActive: true,
    );
    $entityManager->persist($target);
    $entityManager->flush();

    $client->loginUser($admin);
    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/balance-history', $target->id->toString()));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($crawler->filter('.alert')->text())->toContain('Nothing here yet.');
});

it('denies a manager access to a user outside their direct reports', function (): void {
    $client = static::createClient();
    $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
    $manager = findBalanceHistoryUser($entityManager, 'elena.novak@whoisooo.app');
    $target = findBalanceHistoryUser($entityManager, 'admin@whoisooo.app');

    $client->loginUser($manager);
    $client->request('GET', sprintf('/app/my-organization/%s/balance-history', $target->id->toString()));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);

    $client->request('GET', '/app/dashboard/team/members/'.$target->id->toString());
    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('denies a plain user access to another user balance history', function (): void {
    $client = static::createClient();
    $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
    $plainUser = findBalanceHistoryUser($entityManager, 'user@whoisooo.app');
    $target = findBalanceHistoryUser($entityManager, 'admin@whoisooo.app');

    $client->loginUser($plainUser);
    $client->request('GET', sprintf('/app/my-organization/%s/balance-history', $target->id->toString()));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('returns not found for a missing target user', function (): void {
    $client = static::createClient();
    $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
    $admin = findBalanceHistoryUser($entityManager, 'admin@whoisooo.app');

    $client->loginUser($admin);
    $client->request('GET', '/app/my-organization/00000000-0000-0000-0000-000000000000/balance-history');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
});

it('never selects a period owned by another user', function (): void {
    $client = static::createClient();
    $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
    $userFacade = $client->getContainer()->get(UserFacadeInterface::class);
    $admin = findBalanceHistoryUser($entityManager, 'admin@whoisooo.app');
    $target = findBalanceHistoryUser($entityManager, 'manager@whoisooo.app');
    $userFacade->openInitialLeavePeriod($admin->id->toString());
    $userFacade->openInitialLeavePeriod($target->id->toString());
    $foreignPeriodId = findOpenBalanceHistoryPeriodId($userFacade, $admin->id->toString());
    $targetPeriodId = findOpenBalanceHistoryPeriodId($userFacade, $target->id->toString());

    $client->loginUser($admin);
    $crawler = $client->request('GET', sprintf(
        '/app/my-organization/%s/balance-history?period=%s',
        $target->id->toString(),
        $foreignPeriodId,
    ));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($crawler->filter('select[name="period"] option[selected]')->attr('value'))->toBe($targetPeriodId);
});

function findBalanceHistoryUser(EntityManagerInterface $entityManager, string $email): User
{
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    expect($user)->toBeInstanceOf(User::class);

    return $user;
}

function findOpenBalanceHistoryPeriodId(UserFacadeInterface $userFacade, string $userId): string
{
    foreach ($userFacade->getLeavePeriods($userId) as $period) {
        if ($period->isOpen()) {
            return $period->id;
        }
    }

    throw new RuntimeException(sprintf('No open leave period for user "%s".', $userId));
}
