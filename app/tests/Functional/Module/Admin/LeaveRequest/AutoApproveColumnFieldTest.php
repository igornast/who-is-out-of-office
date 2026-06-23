<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get('doctrine')->getManager();

    $this->admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $this->sickLeaveType = $this->em->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Sick Leave']);

    $this->pendingRequest = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->admin,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $this->sickLeaveType,
        startDate: new DateTimeImmutable('+30 days'),
        endDate: new DateTimeImmutable('+34 days'),
        workDays: 5,
    );
    $this->em->persist($this->pendingRequest);
    $this->em->flush();
});

function autoApproveColumnDetailUrl(string $entityId): string
{
    return sprintf('/app/dashboard/leave-request/%s', $entityId);
}

function mockAutoApprove(ContainerInterface $container, bool $enabled, int $delay = 115): void
{
    $settings = Mockery::mock(AppSettingsFacadeInterface::class)->shouldIgnoreMissing();
    $settings->shouldReceive('isAutoApprove')->andReturn($enabled);
    $settings->shouldReceive('autoApproveDelay')->andReturn($delay);
    $settings->shouldReceive('organizationName')->andReturn('Your Organization');
    $container->set(AppSettingsFacadeInterface::class, $settings);
}

function persistLeaveRequest(EntityManagerInterface $em, User $user, LeaveRequestType $type, LeaveRequestStatusEnum $status, bool $isAutoApproved = false): LeaveRequest
{
    $request = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $user,
        status: $status,
        leaveType: $type,
        startDate: new DateTimeImmutable('+30 days'),
        endDate: new DateTimeImmutable('+34 days'),
        workDays: 5,
        isAutoApproved: $isAutoApproved,
    );
    $em->persist($request);
    $em->flush();

    return $request;
}

it('renders a live countdown for a pending request when auto-approve is enabled', function (): void {
    mockAutoApprove(static::getContainer(), true);

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', autoApproveColumnDetailUrl($this->pendingRequest->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('.auto-approve-countdown[data-controller="auto-approve-countdown"]')->count())->toBe(1)
        ->and($crawler->filter('.auto-approve-countdown')->attr('data-auto-approve-countdown-target-value'))->not->toBeEmpty();
});

it('emits the countdown target in ATOM format with a timezone offset', function (): void {
    mockAutoApprove(static::getContainer(), true);

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', autoApproveColumnDetailUrl($this->pendingRequest->id->toString()));

    $target = $crawler->filter('.auto-approve-countdown')->attr('data-auto-approve-countdown-target-value');

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($target)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

it('shows an overdue hint for no-JS users when the countdown target is already in the past', function (): void {
    mockAutoApprove(static::getContainer(), true);

    $this->pendingRequest->setCreatedAt(new DateTimeImmutable('-1 day'));
    $this->em->flush();

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', autoApproveColumnDetailUrl($this->pendingRequest->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('.auto-approve-countdown .auto-approve-countdown__overdue')->count())->toBe(1);
});

it('renders a Yes badge for an auto-approved request', function (): void {
    mockAutoApprove(static::getContainer(), true);
    $request = persistLeaveRequest($this->em, $this->admin, $this->sickLeaveType, LeaveRequestStatusEnum::Approved, isAutoApproved: true);

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', autoApproveColumnDetailUrl($request->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('.auto-approve-badge--yes')->count())->toBe(1)
        ->and($crawler->filter('.auto-approve-countdown')->count())->toBe(0);
});

it('renders a No badge for a manually approved request', function (): void {
    mockAutoApprove(static::getContainer(), true);
    $request = persistLeaveRequest($this->em, $this->admin, $this->sickLeaveType, LeaveRequestStatusEnum::Approved);

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', autoApproveColumnDetailUrl($request->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('.auto-approve-badge--no')->count())->toBe(1)
        ->and($crawler->filter('.auto-approve-countdown')->count())->toBe(0);
});

it('renders a dash for a rejected request', function (): void {
    mockAutoApprove(static::getContainer(), true);
    $request = persistLeaveRequest($this->em, $this->admin, $this->sickLeaveType, LeaveRequestStatusEnum::Rejected);

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', autoApproveColumnDetailUrl($request->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('.auto-approve-none')->count())->toBe(1)
        ->and($crawler->filter('.auto-approve-badge')->count())->toBe(0)
        ->and($crawler->filter('.auto-approve-countdown')->count())->toBe(0);
});

it('hides the whole column when auto-approve is disabled', function (): void {
    mockAutoApprove(static::getContainer(), false);

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', autoApproveColumnDetailUrl($this->pendingRequest->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('.auto-approve-countdown')->count())->toBe(0)
        ->and($crawler->filter('.auto-approve-badge')->count())->toBe(0)
        ->and($crawler->filter('.auto-approve-none')->count())->toBe(0);
});
