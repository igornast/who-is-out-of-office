<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\LeavePeriod;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get('doctrine')->getManager();

    $this->admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    // Elena (user_12) is the direct manager of John Doe (user_3, user@whoisooo.app).
    $this->managerUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'elena.novak@whoisooo.app']);
    $this->regularUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $sickLeaveType = $this->em->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Sick Leave']);

    $this->pendingRequest = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->regularUser,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $sickLeaveType,
        startDate: new DateTimeImmutable('+30 days'),
        endDate: new DateTimeImmutable('+34 days'),
        workDays: 5,
    );
    $this->em->persist($this->pendingRequest);

    $this->approvedRequest = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->regularUser,
        status: LeaveRequestStatusEnum::Approved,
        leaveType: $sickLeaveType,
        startDate: new DateTimeImmutable('+40 days'),
        endDate: new DateTimeImmutable('+44 days'),
        workDays: 5,
        approvedBy: $this->managerUser,
    );
    $this->em->persist($this->approvedRequest);

    $this->adminPendingRequest = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->admin,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $sickLeaveType,
        startDate: new DateTimeImmutable('+50 days'),
        endDate: new DateTimeImmutable('+54 days'),
        workDays: 5,
    );
    $this->em->persist($this->adminPendingRequest);

    $this->otherTeamUser = $this->em->getRepository(User::class)
        ->createQueryBuilder('u')
        ->where('u.manager = :admin')
        ->andWhere('u.email != :managerEmail')
        ->setParameter('admin', $this->admin)
        ->setParameter('managerEmail', 'manager@whoisooo.app')
        ->setMaxResults(1)
        ->getQuery()
        ->getSingleResult();

    $this->otherTeamPendingRequest = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->otherTeamUser,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $sickLeaveType,
        startDate: new DateTimeImmutable('+60 days'),
        endDate: new DateTimeImmutable('+64 days'),
        workDays: 5,
    );
    $this->em->persist($this->otherTeamPendingRequest);

    $this->em->flush();
});

function leaveRequestDetailUrl(string $entityId): string
{
    return sprintf('/app/dashboard/leave-request/%s', $entityId);
}

function findActionUrl(Crawler $crawler, string $action): ?string
{
    $link = $crawler->filter(sprintf('a[data-lr-action="%s"]', $action));

    return $link->count() > 0 ? $link->attr('href') : null;
}

it('returns 405 for GET requests to action routes', function (): void {
    $this->client->loginUser($this->admin);
    $id = $this->pendingRequest->id;

    $this->client->request('GET', sprintf('/app/leave-request/%s/approve', $id));
    expect($this->client->getResponse()->getStatusCode())->toBe(405);

    $this->client->request('GET', sprintf('/app/leave-request/%s/reject', $id));
    expect($this->client->getResponse()->getStatusCode())->toBe(405);

    $this->client->request('GET', sprintf('/app/leave-request/%s/withdraw', $id));
    expect($this->client->getResponse()->getStatusCode())->toBe(405);
});

it('returns JSON 403 for missing CSRF token', function (): void {
    $this->client->loginUser($this->admin);
    $id = $this->pendingRequest->id;

    $this->client->request('POST', sprintf('/app/leave-request/%s/approve', $id));
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(403);

    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse()
        ->and($data['message'])->not->toBeEmpty();
});

it('allows manager to approve direct report pending request', function (): void {
    $this->client->loginUser($this->managerUser);
    $id = $this->pendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    $approveUrl = findActionUrl($crawler, 'approve');
    expect($approveUrl)->not->toBeNull();

    $this->client->request('POST', $approveUrl);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue()
        ->and($data['status'])->toBe('approved')
        ->and($data['message'])->toBe('Leave request approved.');

    $this->em->clear();
    $updated = $this->em->find(LeaveRequest::class, $id);
    expect($updated->status)->toBe(LeaveRequestStatusEnum::Approved)
        ->and($updated->approvedBy->id->toString())->toBe($this->managerUser->id->toString());
});

it('allows admin to reject a pending request', function (): void {
    $this->client->loginUser($this->admin);
    $id = $this->pendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    $rejectUrl = findActionUrl($crawler, 'reject');
    expect($rejectUrl)->not->toBeNull();

    $this->client->request('POST', $rejectUrl);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue()
        ->and($data['status'])->toBe('rejected')
        ->and($data['message'])->toBe('Leave request rejected.');

    $this->em->clear();
    $updated = $this->em->find(LeaveRequest::class, $id);
    expect($updated->status)->toBe(LeaveRequestStatusEnum::Rejected);
});

it('restores a balance-affecting request through the app with audit attribution', function (): void {
    $leaveType = $this->em->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Vacation']);
    expect($leaveType)->toBeInstanceOf(LeaveRequestType::class)
        ->and($leaveType->isAffectingBalance)->toBeTrue();

    $request = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->regularUser,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $leaveType,
        startDate: new DateTimeImmutable('+70 days'),
        endDate: new DateTimeImmutable('+71 days'),
        workDays: 2,
    );
    $this->em->persist($request);
    $this->em->flush();

    $userFacade = self::getContainer()->get(UserFacadeInterface::class);
    $userFacade->openInitialLeavePeriod($this->regularUser->id->toString());
    $userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
        userId: $this->regularUser->id->toString(),
        delta: -2,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
        actorUserId: $this->regularUser->id->toString(),
        leaveRequestId: $request->id->toString(),
    ));
    $usage = $this->em->getRepository(BalanceLedgerEntry::class)->findOneBy([
        'user' => $this->regularUser->id,
        'leaveRequest' => $request->id,
        'type' => BalanceLedgerEntryTypeEnum::Usage,
    ]);
    expect($usage)->toBeInstanceOf(BalanceLedgerEntry::class);

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', leaveRequestDetailUrl($request->id->toString()));
    $rejectUrl = findActionUrl($crawler, 'reject');
    expect($rejectUrl)->not->toBeNull();
    $this->client->request('POST', $rejectUrl);

    $this->em->clear();
    $updatedRequest = $this->em->find(LeaveRequest::class, $request->id);
    $updatedUser = $this->em->find(User::class, $this->regularUser->id);
    $restorations = $this->em->getRepository(BalanceLedgerEntry::class)->findBy([
        'user' => $this->regularUser->id,
        'leaveRequest' => $request->id,
        'type' => BalanceLedgerEntryTypeEnum::Restoration,
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($updatedRequest)->toBeInstanceOf(LeaveRequest::class)
        ->and($updatedRequest->status)->toBe(LeaveRequestStatusEnum::Rejected)
        ->and($updatedUser)->toBeInstanceOf(User::class)
        ->and($updatedUser->currentLeaveBalance)->toBe($usage->balanceBefore)
        ->and($restorations)->toHaveCount(1)
        ->and($restorations[0]->delta)->toBe(2)
        ->and($restorations[0]->balanceBefore)->toBe($usage->balanceAfter)
        ->and($restorations[0]->balanceAfter)->toBe($usage->balanceBefore)
        ->and($restorations[0]->source)->toBe(BalanceLedgerSourceEnum::App)
        ->and($restorations[0]->actor?->id->toString())->toBe($this->admin->id->toString())
        ->and($restorations[0]->leaveRequest?->id->toString())->toBe($request->id->toString());
});

it('restores usage after the leave type flag is turned off', function (): void {
    $leaveType = $this->em->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Vacation']);
    $request = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->regularUser,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $leaveType,
        startDate: new DateTimeImmutable('+70 days'),
        endDate: new DateTimeImmutable('+71 days'),
        workDays: 2,
    );
    $this->em->persist($request);
    $this->em->flush();

    $userFacade = self::getContainer()->get(UserFacadeInterface::class);
    $userFacade->openInitialLeavePeriod($this->regularUser->id->toString());
    $userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
        userId: $this->regularUser->id->toString(),
        delta: -2,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
        actorUserId: $this->regularUser->id->toString(),
        leaveRequestId: $request->id->toString(),
    ));
    $balanceAfterUsage = $this->em->find(User::class, $this->regularUser->id)->currentLeaveBalance;
    $leaveType->isAffectingBalance = false;
    $this->em->flush();

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', leaveRequestDetailUrl($request->id->toString()));
    $rejectUrl = findActionUrl($crawler, 'reject');
    $this->client->request('POST', $rejectUrl);

    $this->em->clear();
    $updatedUser = $this->em->find(User::class, $this->regularUser->id);
    $restoration = $this->em->getRepository(BalanceLedgerEntry::class)->findOneBy([
        'leaveRequest' => $request->id,
        'type' => BalanceLedgerEntryTypeEnum::Restoration,
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($restoration)->toBeInstanceOf(BalanceLedgerEntry::class)
        ->and($restoration->delta)->toBe(2)
        ->and($updatedUser->currentLeaveBalance)->toBe($balanceAfterUsage + 2);

    $this->em->clear();
    $restoredLeaveType = $this->em->find(LeaveRequestType::class, $leaveType->id);
    $restoredLeaveType->isAffectingBalance = true;
    $this->em->flush();
});

it('returns 422 and leaves balance and ledger unchanged when approving twice', function (): void {
    $this->client->loginUser($this->managerUser);
    $id = $this->pendingRequest->id->toString();
    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id));
    $approveUrl = findActionUrl($crawler, 'approve');
    $this->client->request('POST', $approveUrl);
    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $this->em->clear();
    $user = $this->em->find(User::class, $this->regularUser->id);
    $ledgerCount = $this->em->getRepository(BalanceLedgerEntry::class)->count(['user' => $this->regularUser->id]);
    $balance = $user->currentLeaveBalance;
    $this->client->request('POST', $approveUrl);

    $this->em->clear();
    $reloaded = $this->em->find(User::class, $this->regularUser->id);
    $reloadedLedgerCount = $this->em->getRepository(BalanceLedgerEntry::class)->count(['user' => $this->regularUser->id]);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($reloaded->currentLeaveBalance)->toBe($balance)
        ->and($reloadedLedgerCount)->toBe($ledgerCount);
});

it('returns 422 and leaves balance and ledger unchanged when rejecting an approved request', function (): void {
    $id = $this->pendingRequest->id->toString();
    $this->client->loginUser($this->managerUser);
    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id));
    $approveUrl = findActionUrl($crawler, 'approve');
    $rejectUrl = findActionUrl($crawler, 'reject');
    $this->client->request('POST', $approveUrl);
    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $this->client->loginUser($this->admin);
    $this->em->clear();
    $user = $this->em->find(User::class, $this->regularUser->id);
    $ledgerCount = $this->em->getRepository(BalanceLedgerEntry::class)->count(['user' => $this->regularUser->id]);
    $balance = $user->currentLeaveBalance;

    $this->client->request('POST', $rejectUrl);

    $this->em->clear();
    $reloaded = $this->em->find(User::class, $this->regularUser->id);
    $reloadedLedgerCount = $this->em->getRepository(BalanceLedgerEntry::class)->count(['user' => $this->regularUser->id]);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($reloaded->currentLeaveBalance)->toBe($balance)
        ->and($reloadedLedgerCount)->toBe($ledgerCount);
});

it('restores a pre-migration pending request without a usage entry when rejected', function (): void {
    $leaveType = $this->em->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Vacation']);
    $requestCreatedAt = new DateTimeImmutable('-2 days');
    $migrationCreatedAt = new DateTimeImmutable('-1 day');
    $request = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->regularUser,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $leaveType,
        startDate: new DateTimeImmutable('+70 days'),
        endDate: new DateTimeImmutable('+71 days'),
        workDays: 2,
    );
    $request->setCreatedAt($requestCreatedAt);
    $this->em->persist($request);
    $this->em->flush();

    self::getContainer()->get(UserFacadeInterface::class)->openInitialLeavePeriod($this->regularUser->id->toString());
    addMigrationOpeningEntry($this->em, $this->regularUser, $migrationCreatedAt);
    $balanceBefore = $this->regularUser->currentLeaveBalance;

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', leaveRequestDetailUrl($request->id->toString()));
    $rejectUrl = findActionUrl($crawler, 'reject');
    $this->client->request('POST', $rejectUrl);

    $this->em->clear();
    $updatedUser = $this->em->find(User::class, $this->regularUser->id);
    $restorations = $this->em->getRepository(BalanceLedgerEntry::class)->findBy([
        'leaveRequest' => $request->id,
        'type' => BalanceLedgerEntryTypeEnum::Restoration,
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($updatedUser->currentLeaveBalance)->toBe($balanceBefore + 2)
        ->and($restorations)->toHaveCount(1)
        ->and($restorations[0]->delta)->toBe(2);

    $this->client->request('POST', $rejectUrl);
    $this->em->clear();
    $restorationsAfterSecondAttempt = $this->em->getRepository(BalanceLedgerEntry::class)->findBy([
        'leaveRequest' => $request->id,
        'type' => BalanceLedgerEntryTypeEnum::Restoration,
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($restorationsAfterSecondAttempt)->toHaveCount(1);
});

it('restores a pre-migration approved request without a usage entry when withdrawn', function (): void {
    $leaveType = $this->em->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Vacation']);
    $request = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->regularUser,
        status: LeaveRequestStatusEnum::Approved,
        leaveType: $leaveType,
        startDate: new DateTimeImmutable('+70 days'),
        endDate: new DateTimeImmutable('+71 days'),
        workDays: 2,
        approvedBy: $this->managerUser,
    );
    $request->setCreatedAt(new DateTimeImmutable('-2 days'));
    $this->em->persist($request);
    $this->em->flush();

    self::getContainer()->get(UserFacadeInterface::class)->openInitialLeavePeriod($this->regularUser->id->toString());
    addMigrationOpeningEntry($this->em, $this->regularUser, new DateTimeImmutable('-1 day'));
    $balanceBefore = $this->regularUser->currentLeaveBalance;

    $this->client->loginUser($this->regularUser);
    $crawler = $this->client->request('GET', leaveRequestDetailUrl($request->id->toString()));
    $withdrawUrl = findActionUrl($crawler, 'withdraw');
    $this->client->request('POST', $withdrawUrl);

    $this->em->clear();
    $updatedUser = $this->em->find(User::class, $this->regularUser->id);
    $restorations = $this->em->getRepository(BalanceLedgerEntry::class)->findBy([
        'leaveRequest' => $request->id,
        'type' => BalanceLedgerEntryTypeEnum::Restoration,
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($updatedUser->currentLeaveBalance)->toBe($balanceBefore + 2)
        ->and($restorations)->toHaveCount(1)
        ->and($restorations[0]->delta)->toBe(2)
        ->and($restorations[0]->actor?->id->toString())->toBe($this->regularUser->id->toString());
});

it('does not restore a post-migration request without a usage entry', function (): void {
    $leaveType = $this->em->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Vacation']);
    $request = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->regularUser,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $leaveType,
        startDate: new DateTimeImmutable('+70 days'),
        endDate: new DateTimeImmutable('+71 days'),
        workDays: 2,
    );
    $request->setCreatedAt(new DateTimeImmutable('-1 day'));
    $this->em->persist($request);
    $this->em->flush();

    self::getContainer()->get(UserFacadeInterface::class)->openInitialLeavePeriod($this->regularUser->id->toString());
    addMigrationOpeningEntry($this->em, $this->regularUser, new DateTimeImmutable('-2 days'));
    $balanceBefore = $this->regularUser->currentLeaveBalance;

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', leaveRequestDetailUrl($request->id->toString()));
    $rejectUrl = findActionUrl($crawler, 'reject');
    $this->client->request('POST', $rejectUrl);

    $this->em->clear();
    $updatedUser = $this->em->find(User::class, $this->regularUser->id);
    $restorations = $this->em->getRepository(BalanceLedgerEntry::class)->findBy([
        'leaveRequest' => $request->id,
        'type' => BalanceLedgerEntryTypeEnum::Restoration,
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($updatedUser->currentLeaveBalance)->toBe($balanceBefore)
        ->and($restorations)->toHaveCount(0);
});

it('allows user to withdraw own approved request', function (): void {
    $userFacade = self::getContainer()->get(UserFacadeInterface::class);
    $userFacade->openInitialLeavePeriod($this->regularUser->id->toString());
    $userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
        userId: $this->regularUser->id->toString(),
        delta: -5,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
        actorUserId: $this->regularUser->id->toString(),
        leaveRequestId: $this->approvedRequest->id->toString(),
    ));
    $usage = $this->em->getRepository(BalanceLedgerEntry::class)->findOneBy([
        'leaveRequest' => $this->approvedRequest->id,
        'type' => BalanceLedgerEntryTypeEnum::Usage,
    ]);

    $this->client->loginUser($this->regularUser);
    $id = $this->approvedRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    $withdrawUrl = findActionUrl($crawler, 'withdraw');
    expect($withdrawUrl)->not->toBeNull();

    $this->client->request('POST', $withdrawUrl);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue()
        ->and($data['status'])->toBe('withdrawn')
        ->and($data['message'])->toBe('Leave request withdrawn.');

    $this->em->clear();
    $updated = $this->em->find(LeaveRequest::class, $id);
    $restoration = $this->em->getRepository(BalanceLedgerEntry::class)->findOneBy([
        'leaveRequest' => $id,
        'type' => BalanceLedgerEntryTypeEnum::Restoration,
    ]);
    $updatedUser = $this->em->find(User::class, $this->regularUser->id);
    expect($updated->status)->toBe(LeaveRequestStatusEnum::Withdrawn)
        ->and($restoration->delta)->toBe(5)
        ->and($restoration->source)->toBe(BalanceLedgerSourceEnum::App)
        ->and($restoration->actor->id->toString())->toBe($this->regularUser->id->toString())
        ->and($updatedUser->currentLeaveBalance)->toBe($usage->balanceBefore);
});

it('restores the balance when a user withdraws a pending request', function (): void {
    $userFacade = self::getContainer()->get(UserFacadeInterface::class);
    $userFacade->openInitialLeavePeriod($this->regularUser->id->toString());
    $userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
        userId: $this->regularUser->id->toString(),
        delta: -5,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
        actorUserId: $this->regularUser->id->toString(),
        leaveRequestId: $this->pendingRequest->id->toString(),
    ));

    $this->client->loginUser($this->regularUser);
    $crawler = $this->client->request('GET', leaveRequestDetailUrl($this->pendingRequest->id->toString()));
    $withdrawUrl = findActionUrl($crawler, 'withdraw');
    $this->client->request('POST', $withdrawUrl);

    $this->em->clear();
    $updated = $this->em->find(LeaveRequest::class, $this->pendingRequest->id);
    $restoration = $this->em->getRepository(BalanceLedgerEntry::class)->findOneBy([
        'leaveRequest' => $this->pendingRequest->id,
        'type' => BalanceLedgerEntryTypeEnum::Restoration,
    ]);
    expect($updated->status)->toBe(LeaveRequestStatusEnum::Withdrawn)
        ->and($restoration->delta)->toBe(5)
        ->and($restoration->actor->id->toString())->toBe($this->regularUser->id->toString());
});

it('returns JSON 403 for self-approval', function (): void {
    $this->client->loginUser($this->admin);
    $id = $this->adminPendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    expect(findActionUrl($crawler, 'approve'))->toBeNull('Approve button should be hidden for own requests');

    $this->client->request('POST', sprintf('/app/leave-request/%s/approve', $id), ['_token' => 'invalid']);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(403);

    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
});

it('does not render approve button for regular user', function (): void {
    $this->client->loginUser($this->regularUser);
    $id = $this->pendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    expect(findActionUrl($crawler, 'approve'))->toBeNull()
        ->and(findActionUrl($crawler, 'reject'))->toBeNull();
});

it('does not render withdraw button for non-owner', function (): void {
    $this->client->loginUser($this->managerUser);
    $id = $this->approvedRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    expect(findActionUrl($crawler, 'withdraw'))->toBeNull();
});

it('returns JSON 403 for invalid CSRF token', function (): void {
    $this->client->loginUser($this->admin);
    $id = $this->pendingRequest->id;

    $this->client->request('POST', sprintf('/app/leave-request/%s/approve', $id), ['_token' => 'invalid']);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(403);

    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse()
        ->and($data['message'])->not->toBeEmpty();
});

it('returns JSON 403 when manager tries to approve other team request', function (): void {
    $this->client->loginUser($this->managerUser);
    $id = $this->otherTeamPendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    expect(findActionUrl($crawler, 'approve'))->toBeNull('Approve button should be hidden for other team requests')
        ->and(findActionUrl($crawler, 'reject'))->toBeNull('Reject button should be hidden for other team requests');

    $this->client->request('POST', sprintf('/app/leave-request/%s/approve', $id), ['_token' => 'invalid']);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(403);

    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
});

it('accepts CSRF token in POST body', function (): void {
    $this->client->loginUser($this->managerUser);
    $id = $this->pendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    $approveUrl = findActionUrl($crawler, 'approve');
    expect($approveUrl)->not->toBeNull();

    parse_str(parse_url($approveUrl, PHP_URL_QUERY) ?? '', $query);
    $token = $query['_token'] ?? '';

    $this->client->request(
        'POST',
        sprintf('/app/leave-request/%s/approve', $id),
        ['_token' => $token],
    );
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue()
        ->and($data['status'])->toBe('approved')
        ->and($data['message'])->toBe('Leave request approved.');
});

function addMigrationOpeningEntry(EntityManagerInterface $entityManager, User $user, DateTimeImmutable $createdAt): void
{
    $period = $entityManager->getRepository(LeavePeriod::class)->findOneBy([
        'user' => $user,
        'closedAt' => null,
    ]);
    if (!$period instanceof LeavePeriod) {
        throw new RuntimeException('No open leave period found.');
    }
    $lastEntry = $entityManager->getRepository(BalanceLedgerEntry::class)->findOneBy(
        ['user' => $user],
        ['sequence' => 'DESC'],
    );
    $sequence = $lastEntry instanceof BalanceLedgerEntry ? $lastEntry->sequence + 1 : 1;

    $entry = new BalanceLedgerEntry(
        delta: $user->currentLeaveBalance,
        balanceBefore: 0,
        balanceAfter: $user->currentLeaveBalance,
        sequence: $sequence,
        id: Uuid::uuid4(),
        type: BalanceLedgerEntryTypeEnum::OpeningBalance,
        source: BalanceLedgerSourceEnum::Migration,
        user: $user,
        leavePeriod: $period,
    );
    $entry->setCreatedAt($createdAt);
    $entry->setUpdatedAt($createdAt);
    $entityManager->persist($entry);
    $entityManager->flush();
}
