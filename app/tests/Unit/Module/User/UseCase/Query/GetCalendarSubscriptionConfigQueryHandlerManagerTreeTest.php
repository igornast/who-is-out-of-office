<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetCalendarSubscriptionConfigQueryHandler;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionCandidateDTO;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
    $this->holidayFacade = Mockery::mock(HolidayFacadeInterface::class);
    $this->handler = new GetCalendarSubscriptionConfigQueryHandler($this->userRepository, $this->holidayFacade);
});

it('marks candidates with isManager and reportIds and emits topLevelIds', function (): void {
    $userId = Uuid::uuid4()->toString();
    $user = UserDTOFixture::create(['id' => $userId, 'calendarCountryCode' => 'DE']);

    $peer = UserDTOFixture::create(['calendarCountryCode' => 'DE']);
    $subManager = UserDTOFixture::create(['managerId' => $userId, 'calendarCountryCode' => 'DE']);
    $leafReport = UserDTOFixture::create(['managerId' => $subManager->id, 'calendarCountryCode' => 'DE']);

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn($user);
    $this->userRepository->shouldReceive('findTeammatesOf')->with($userId)->andReturn([$peer, $subManager]);
    $this->userRepository->shouldReceive('findManagementDescendants')->with($userId)->andReturn([$subManager, $leafReport]);
    $this->holidayFacade->shouldReceive('getActiveCalendarsForCountryCodes')->andReturn([]);

    $config = $this->handler->handle($userId);

    $byId = [];
    foreach ($config->candidateTeamMembers as $c) {
        $byId[$c->id] = $c;
    }

    expect($config->candidateTeamMembers)->toHaveCount(3)
        ->and($byId[$subManager->id])->toBeInstanceOf(CalendarSubscriptionCandidateDTO::class)
        ->and($byId[$subManager->id]->isManager)->toBeTrue()
        ->and($byId[$subManager->id]->reportIds)->toEqual([$leafReport->id])
        ->and($byId[$leafReport->id]->isManager)->toBeFalse()
        ->and($byId[$leafReport->id]->reportIds)->toBe([])
        ->and($byId[$peer->id]->isManager)->toBeFalse()
        ->and($config->topLevelTeamMemberIds)->toEqualCanonicalizing([$peer->id, $subManager->id]);
});

it('does not expose the current user own manager as expandable for a regular member', function (): void {
    $userId = Uuid::uuid4()->toString();
    $managerId = Uuid::uuid4()->toString();

    $user = UserDTOFixture::create(['id' => $userId, 'managerId' => $managerId, 'calendarCountryCode' => 'DE']);

    $manager = UserDTOFixture::create(['id' => $managerId, 'calendarCountryCode' => 'DE']);
    $peerA = UserDTOFixture::create(['managerId' => $managerId, 'calendarCountryCode' => 'DE']);
    $peerB = UserDTOFixture::create(['managerId' => $managerId, 'calendarCountryCode' => 'DE']);

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn($user);
    $this->userRepository->shouldReceive('findTeammatesOf')->with($userId)->andReturn([$manager, $peerA, $peerB]);
    $this->userRepository->shouldReceive('findManagementDescendants')->with($userId)->andReturn([]);
    $this->holidayFacade->shouldReceive('getActiveCalendarsForCountryCodes')->andReturn([]);

    $config = $this->handler->handle($userId);

    $byId = [];
    foreach ($config->candidateTeamMembers as $c) {
        $byId[$c->id] = $c;
    }

    expect($byId[$managerId]->isManager)->toBeFalse()
        ->and($byId[$managerId]->reportIds)->toBe([])
        ->and($byId[$peerA->id]->isManager)->toBeFalse()
        ->and($byId[$peerB->id]->isManager)->toBeFalse()
        ->and($config->topLevelTeamMemberIds)->toEqualCanonicalizing([$managerId, $peerA->id, $peerB->id]);
});

it('deduplicates a user appearing in both teammates and descendants', function (): void {
    $userId = Uuid::uuid4()->toString();
    $user = UserDTOFixture::create(['id' => $userId]);
    $report = UserDTOFixture::create(['managerId' => $userId]);

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn($user);
    $this->userRepository->shouldReceive('findTeammatesOf')->with($userId)->andReturn([$report]);
    $this->userRepository->shouldReceive('findManagementDescendants')->with($userId)->andReturn([$report]);
    $this->holidayFacade->shouldReceive('getActiveCalendarsForCountryCodes')->andReturn([]);

    $config = $this->handler->handle($userId);

    expect($config->candidateTeamMembers)->toHaveCount(1)
        ->and($config->topLevelTeamMemberIds)->toBe([$report->id]);
});

it('computes myTeamMemberIds as the current users direct reports only', function (): void {
    $userId = Uuid::uuid4()->toString();
    $user = UserDTOFixture::create(['id' => $userId, 'calendarCountryCode' => 'DE']);

    $peer = UserDTOFixture::create(['calendarCountryCode' => 'DE']);
    $directReportA = UserDTOFixture::create(['managerId' => $userId, 'calendarCountryCode' => 'DE']);
    $directReportB = UserDTOFixture::create(['managerId' => $userId, 'calendarCountryCode' => 'DE']);

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn($user);
    $this->userRepository->shouldReceive('findTeammatesOf')->with($userId)->andReturn([$peer, $directReportA, $directReportB]);
    $this->userRepository->shouldReceive('findManagementDescendants')->with($userId)->andReturn([$directReportA, $directReportB]);
    $this->holidayFacade->shouldReceive('getActiveCalendarsForCountryCodes')->andReturn([]);

    $config = $this->handler->handle($userId);

    expect($config->myTeamMemberIds)->toEqualCanonicalizing([$directReportA->id, $directReportB->id])
        ->and($config->myTeamMemberIds)->not->toContain($peer->id)
        ->and($config->topLevelTeamMemberIds)->toEqualCanonicalizing([$peer->id, $directReportA->id, $directReportB->id]);
});

it('returns an empty myTeamMemberIds for a member with no direct reports', function (): void {
    $userId = Uuid::uuid4()->toString();
    $managerId = Uuid::uuid4()->toString();

    $user = UserDTOFixture::create(['id' => $userId, 'managerId' => $managerId, 'calendarCountryCode' => 'DE']);

    $manager = UserDTOFixture::create(['id' => $managerId, 'calendarCountryCode' => 'DE']);
    $peer = UserDTOFixture::create(['managerId' => $managerId, 'calendarCountryCode' => 'DE']);

    $this->userRepository->shouldReceive('findOneById')->with($userId)->andReturn($user);
    $this->userRepository->shouldReceive('findTeammatesOf')->with($userId)->andReturn([$manager, $peer]);
    $this->userRepository->shouldReceive('findManagementDescendants')->with($userId)->andReturn([]);
    $this->holidayFacade->shouldReceive('getActiveCalendarsForCountryCodes')->andReturn([]);

    $config = $this->handler->handle($userId);

    expect($config->myTeamMemberIds)->toBe([])
        ->and($config->topLevelTeamMemberIds)->toEqualCanonicalizing([$managerId, $peer->id]);
});
