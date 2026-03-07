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

    $this->otherTeamUser = $this->em->getRepository(User::class)
        ->createQueryBuilder('u')
        ->where('u.manager = :admin')
        ->andWhere('u.email != :managerEmail')
        ->setParameter('admin', $this->admin)
        ->setParameter('managerEmail', 'manager@ooo.com')
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

it('allows user to withdraw own approved request', function (): void {
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
    expect($updated->status)->toBe(LeaveRequestStatusEnum::Withdrawn);
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
