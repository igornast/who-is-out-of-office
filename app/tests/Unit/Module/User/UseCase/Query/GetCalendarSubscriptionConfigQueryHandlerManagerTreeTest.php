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
