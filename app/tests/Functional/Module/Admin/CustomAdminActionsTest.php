<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get(EntityManagerInterface::class);
    $this->admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $this->manager = $this->em->getRepository(User::class)->findOneBy(['email' => 'elena.novak@whoisooo.app']);
    $this->regularUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $leaveType = $this->em->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Sick Leave']);

    $this->pendingRequest = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->regularUser,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $leaveType,
        startDate: new DateTimeImmutable('+30 days'),
        endDate: new DateTimeImmutable('+34 days'),
        workDays: 5,
    );
    $this->em->persist($this->pendingRequest);
    $this->em->flush();
});

function holidayToggleUrl(string $id): string
{
    return sprintf('/app/dashboard/settings/public-holidays/%s/toggle-active', $id);
}

function batchActionForm(Crawler $crawler, string $action, string $entityId): array
{
    $button = $crawler->filter(sprintf('[data-action-batch="true"][data-action-url*="%s"]', $action));
    $actionName = lcfirst(str_replace('-', '', ucwords($action, '-')));

    return [
        'batchActionName' => $actionName,
        'batchActionEntityIds' => [$entityId],
        'entityFqcn' => $button->attr('data-entity-fqcn'),
        'batchActionCsrfToken' => $button->attr('data-action-csrf-token'),
        '_url' => $button->attr('data-action-url'),
    ];
}

it('allows an admin to toggle a holiday calendar only through POST', function (): void {
    $calendar = $this->em->getRepository(HolidayCalendar::class)->findOneBy(['countryCode' => 'DE']);
    $initialState = $calendar->isActive;
    $this->client->loginUser($this->admin);

    $this->client->request('POST', holidayToggleUrl($calendar->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(302);
    $this->em->clear();
    $updated = $this->em->find(HolidayCalendar::class, $calendar->id);
    expect($updated->isActive)->toBe(!$initialState);

    $this->client->request('POST', holidayToggleUrl($calendar->id->toString()));
    $this->em->clear();
    expect($this->em->find(HolidayCalendar::class, $calendar->id)->isActive)->toBe($initialState);
});

it('rejects GET and regular users on the holiday toggle route', function (): void {
    $calendar = $this->em->getRepository(HolidayCalendar::class)->findOneBy(['countryCode' => 'DE']);
    $url = holidayToggleUrl($calendar->id->toString());

    $this->client->loginUser($this->admin);
    $this->client->request('GET', $url);
    expect($this->client->getResponse()->getStatusCode())->toBe(405);

    $this->client = static::createClient();
    $this->client->loginUser($this->regularUser);
    $this->client->request('POST', $url);
    expect($this->client->getResponse()->getStatusCode())->toBe(403);
});

it('allows a manager to approve a selected team request with the rendered batch token', function (): void {
    $this->client->loginUser($this->manager);
    $crawler = $this->client->request('GET', '/app/dashboard/team/leave-requests');
    $form = batchActionForm($crawler, 'batch-approve', $this->pendingRequest->id->toString());

    $this->client->request('POST', $form['_url'], [
        'batchActionName' => $form['batchActionName'],
        'batchActionEntityIds' => $form['batchActionEntityIds'],
        'entityFqcn' => $form['entityFqcn'],
        'batchActionCsrfToken' => $form['batchActionCsrfToken'],
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(302);
    $this->em->clear();
    expect($this->em->find(LeaveRequest::class, $this->pendingRequest->id)->status)->toBe(LeaveRequestStatusEnum::Approved);
});

it('does not process a team batch approval with a wrong token or entity type', function (): void {
    $this->client->loginUser($this->manager);
    $crawler = $this->client->request('GET', '/app/dashboard/team/leave-requests');
    $form = batchActionForm($crawler, 'batch-approve', $this->pendingRequest->id->toString());

    $this->client->request('POST', $form['_url'], [
        'batchActionName' => $form['batchActionName'],
        'batchActionEntityIds' => $form['batchActionEntityIds'],
        'entityFqcn' => $form['entityFqcn'],
        'batchActionCsrfToken' => 'wrong-token',
    ]);
    $this->em->clear();
    expect($this->em->find(LeaveRequest::class, $this->pendingRequest->id)->status)->toBe(LeaveRequestStatusEnum::Pending);

    $this->client->request('POST', $form['_url'], [
        'batchActionName' => $form['batchActionName'],
        'batchActionEntityIds' => $form['batchActionEntityIds'],
        'entityFqcn' => User::class,
        'batchActionCsrfToken' => $form['batchActionCsrfToken'],
    ]);
    $this->em->clear();
    expect($this->em->find(LeaveRequest::class, $this->pendingRequest->id)->status)->toBe(LeaveRequestStatusEnum::Pending);
});

it('allows a manager to reject a selected team request with the rendered batch token', function (): void {
    $this->client->loginUser($this->manager);
    $crawler = $this->client->request('GET', '/app/dashboard/team/leave-requests');
    $form = batchActionForm($crawler, 'batch-reject', $this->pendingRequest->id->toString());

    $this->client->request('POST', $form['_url'], [
        'batchActionName' => $form['batchActionName'],
        'batchActionEntityIds' => $form['batchActionEntityIds'],
        'entityFqcn' => $form['entityFqcn'],
        'batchActionCsrfToken' => $form['batchActionCsrfToken'],
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(302);
    $this->em->clear();
    expect($this->em->find(LeaveRequest::class, $this->pendingRequest->id)->status)->toBe(LeaveRequestStatusEnum::Rejected);
});
