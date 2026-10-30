<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\LeavePeriod;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    static::bootKernel();
    $this->em = static::getContainer()->get('doctrine')->getManager();
});

it('catches a missing ledger mapping or schema column by round-tripping ledger data through Doctrine', function (): void {
    $user = new User(
        Uuid::uuid4(),
        'Ledger',
        'Owner',
        sprintf('ledger-%s@example.com', Uuid::uuid4()->toString()),
        'password',
    );
    $periodId = Uuid::uuid4();
    $entryId = Uuid::uuid4();
    $startDate = new DateTimeImmutable('2026-01-01');
    $endDate = new DateTimeImmutable('2027-01-01');

    $leavePeriod = new LeavePeriod(
        24,
        12,
        $periodId,
        $user,
        $startDate,
        $endDate,
    );
    $entry = new BalanceLedgerEntry(
        -3,
        12,
        9,
        1,
        $entryId,
        BalanceLedgerEntryTypeEnum::Usage,
        BalanceLedgerSourceEnum::Scheduler,
        $user,
        $leavePeriod,
        'Three days of leave used',
    );

    $this->em->persist($user);
    $this->em->persist($leavePeriod);
    $this->em->persist($entry);
    $this->em->flush();
    $this->em->clear();

    $reloaded = $this->em->find(BalanceLedgerEntry::class, $entryId);

    expect($reloaded)->not->toBeNull()
        ->and($reloaded->type)->toBe(BalanceLedgerEntryTypeEnum::Usage)
        ->and($reloaded->source)->toBe(BalanceLedgerSourceEnum::Scheduler)
        ->and($reloaded->delta)->toBe(-3)
        ->and($reloaded->balanceBefore)->toBe(12)
        ->and($reloaded->balanceAfter)->toBe(9)
        ->and($reloaded->reason)->toBe('Three days of leave used')
        ->and($reloaded->user->id->toString())->toBe($user->id->toString())
        ->and($reloaded->leavePeriod->id->toString())->toBe($periodId->toString())
        ->and($reloaded->leavePeriod->startDate)->toEqual($startDate)
        ->and($reloaded->leavePeriod->endDate)->toEqual($endDate);
});
