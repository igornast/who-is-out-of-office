<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get('doctrine')->getManager();

    $this->admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@ooo.com']);
    $this->managerUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@ooo.com']);
    $this->regularUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@ooo.com']);
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

    $this->em->flush();
});

function leaveRequestDetailUrl(string $entityId): string
{
    return sprintf('/app/dashboard/leave-request/%s', $entityId);
}

function findActionFormUrl(Crawler $crawler, string $action): ?string
{
    $form = $crawler->filter(sprintf('form[action*="/%s"]', $action));

    return $form->count() > 0 ? $form->attr('action') : null;
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

it('rejects POST without CSRF token', function (): void {
    $this->client->loginUser($this->admin);
    $id = $this->pendingRequest->id;

    $this->client->request('POST', sprintf('/app/leave-request/%s/approve', $id));
    expect($this->client->getResponse()->getStatusCode())->toBe(403);
});

it('allows manager to approve direct report pending request', function (): void {
    $this->client->loginUser($this->managerUser);
    $id = $this->pendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    $approveUrl = findActionFormUrl($crawler, 'approve');
    expect($approveUrl)->not->toBeNull();

    $this->client->request('POST', $approveUrl);
    expect($this->client->getResponse()->getStatusCode())->toBe(302);

    $this->em->clear();
    $updated = $this->em->find(LeaveRequest::class, $id);
    expect($updated->status)->toBe(LeaveRequestStatusEnum::Approved)
        ->and($updated->approvedBy->id->toString())->toBe($this->managerUser->id->toString());
});

it('allows admin to reject a pending request', function (): void {
    $this->client->loginUser($this->admin);
    $id = $this->pendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    $rejectUrl = findActionFormUrl($crawler, 'reject');
    expect($rejectUrl)->not->toBeNull();

    $this->client->request('POST', $rejectUrl);
    expect($this->client->getResponse()->getStatusCode())->toBe(302);

    $this->em->clear();
    $updated = $this->em->find(LeaveRequest::class, $id);
    expect($updated->status)->toBe(LeaveRequestStatusEnum::Rejected);
});

it('allows user to withdraw own approved request', function (): void {
    $this->client->loginUser($this->regularUser);
    $id = $this->approvedRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    $withdrawUrl = findActionFormUrl($crawler, 'withdraw');
    expect($withdrawUrl)->not->toBeNull();

    $this->client->request('POST', $withdrawUrl);
    expect($this->client->getResponse()->getStatusCode())->toBe(302);

    $this->em->clear();
    $updated = $this->em->find(LeaveRequest::class, $id);
    expect($updated->status)->toBe(LeaveRequestStatusEnum::Withdrawn);
});

it('denies self-approval', function (): void {
    $this->client->loginUser($this->admin);
    $id = $this->adminPendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    $approveUrl = findActionFormUrl($crawler, 'approve');
    expect($approveUrl)->not->toBeNull();

    $this->client->request('POST', $approveUrl);
    expect($this->client->getResponse()->getStatusCode())->toBe(403);
});

it('does not render approve button for regular user', function (): void {
    $this->client->loginUser($this->regularUser);
    $id = $this->pendingRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    expect(findActionFormUrl($crawler, 'approve'))->toBeNull()
        ->and(findActionFormUrl($crawler, 'reject'))->toBeNull();
});

it('does not render withdraw button for non-owner', function (): void {
    $this->client->loginUser($this->managerUser);
    $id = $this->approvedRequest->id;

    $crawler = $this->client->request('GET', leaveRequestDetailUrl($id->toString()));
    expect(findActionFormUrl($crawler, 'withdraw'))->toBeNull();
});
