<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Doctrine\Entity\UserSlackIntegration;
use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Infrastructure\Slack\UseCase\Command\UpdateLeaveRequestWithInteractiveNotificationCommandHandler;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use Doctrine\ORM\EntityManagerInterface;

beforeEach(function (): void {
    self::bootKernel();
    $this->em = self::getContainer()->get(EntityManagerInterface::class);
    $this->handler = self::getContainer()->get(UpdateLeaveRequestWithInteractiveNotificationCommandHandler::class);
});

afterEach(function (): void {
    self::ensureKernelShutdown();
});

it('keeps the rejected request and writes a Slack-sourced restoration entry', function () {
    $leaveRequest = $this->em->createQueryBuilder()
        ->select('leaveRequest')
        ->from(LeaveRequest::class, 'leaveRequest')
        ->join('leaveRequest.leaveType', 'leaveType')
        ->where('leaveRequest.status = :status')
        ->andWhere('leaveType.isAffectingBalance = true')
        ->setParameter('status', LeaveRequestStatusEnum::Pending)
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();

    expect($leaveRequest)->toBeInstanceOf(LeaveRequest::class);

    $requestId = $leaveRequest->id->toString();
    $userId = $leaveRequest->user->id->toString();
    $balanceBefore = $leaveRequest->user->currentLeaveBalance;
    $workDays = $leaveRequest->workDays;
    $userFacade = self::getContainer()->get(UserFacadeInterface::class);
    $userFacade->openInitialLeavePeriod($userId);
    $userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
        userId: $userId,
        delta: -$workDays,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
        leaveRequestId: $requestId,
    ));
    $actor = $this->em->createQueryBuilder()
        ->select('user')
        ->from(User::class, 'user')
        ->where('user.id != :requesterId')
        ->setParameter('requesterId', $userId)
        ->setMaxResults(1)
        ->getQuery()
        ->getSingleResult();
    $slackMemberId = 'U-BALANCE-REVIEWER';
    $slackIntegration = new UserSlackIntegration($actor, $slackMemberId);
    $actor->slackIntegration = $slackIntegration;
    $this->em->persist($slackIntegration);
    $this->em->flush();

    $notification = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Rejected,
        type: 'leaveRequest',
        identifier: $requestId,
        channel: 'C0000000000',
        responseUrl: null,
        memberId: $slackMemberId,
    );

    $firstResult = $this->handler->handle($notification);
    $retryResult = $this->handler->handle($notification);

    $this->em->clear();

    $reloaded = $this->em->getRepository(LeaveRequest::class)->find($requestId);
    $user = $this->em->getRepository(User::class)->find($userId);
    $entries = $this->em->getRepository(BalanceLedgerEntry::class)->findBy([
        'leaveRequest' => $requestId,
        'type' => BalanceLedgerEntryTypeEnum::Restoration,
    ]);

    expect($firstResult)->not->toBeNull()
        ->and($retryResult)->toBeNull()
        ->and($reloaded)->not->toBeNull()
        ->and($reloaded->status)->toBe(LeaveRequestStatusEnum::Rejected)
        ->and($reloaded->approvedBy?->id->toString())->toBe($actor->id->toString())
        ->and($user)->not->toBeNull()
        ->and($user->currentLeaveBalance)->toBe($balanceBefore)
        ->and($entries)->toHaveCount(1)
        ->and($entries[0]->source)->toBe(BalanceLedgerSourceEnum::Slack)
        ->and($entries[0]->actor?->id->toString())->toBe($actor->id->toString())
        ->and($entries[0]->delta)->toBe($workDays)
        ->and($entries[0]->balanceAfter)->toBe($balanceBefore);
});
