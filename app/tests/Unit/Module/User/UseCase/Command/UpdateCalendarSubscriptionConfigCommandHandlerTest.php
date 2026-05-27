<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\UpdateCalendarSubscriptionConfigCommandHandler;
use App\Module\User\UseCase\Query\GetCalendarSubscriptionConfigQueryHandler;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionConfigDTO;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
    $this->configHandler = Mockery::mock(GetCalendarSubscriptionConfigQueryHandler::class);
    $this->handler = new UpdateCalendarSubscriptionConfigCommandHandler($this->userRepository, $this->configHandler);
});

it('persists nulls (auto) without consulting the candidate set', function (): void {
    $userId = Uuid::uuid4()->toString();

    $this->configHandler->shouldNotReceive('handle');
    $this->userRepository
        ->shouldReceive('updateCalendarSubscriptionConfig')
        ->once()
        ->with($userId, null, null);

    $this->handler->handle($userId, null, null);
});

it('filters team-member ids that are not in the candidate set', function (): void {
    $userId = Uuid::uuid4()->toString();
    $validId = Uuid::uuid4()->toString();
    $invalidId = Uuid::uuid4()->toString();
    $candidate = UserDTOFixture::create(['id' => $validId]);

    $this->configHandler
        ->shouldReceive('handle')
        ->with($userId)
        ->andReturn(new CalendarSubscriptionConfigDTO(
            candidateTeamMembers: [$candidate],
            candidateHolidayCalendars: [],
            selectedTeamMemberIds: null,
            selectedHolidayCalendarIds: null,
        ));

    $this->userRepository
        ->shouldReceive('updateCalendarSubscriptionConfig')
        ->once()
        ->with($userId, [$validId], null);

    $this->handler->handle($userId, [$validId, $invalidId], null);
});

it('filters holiday calendar ids that are not in the candidate set', function (): void {
    $userId = Uuid::uuid4()->toString();
    $validCalendar = new PublicHolidayCalendarDTO(id: Uuid::uuid4(), countryCode: 'DE', countryName: 'Germany', holidays: []);
    $validId = $validCalendar->id->toString();
    $invalidId = Uuid::uuid4()->toString();

    $this->configHandler
        ->shouldReceive('handle')
        ->with($userId)
        ->andReturn(new CalendarSubscriptionConfigDTO(
            candidateTeamMembers: [],
            candidateHolidayCalendars: [$validCalendar],
            selectedTeamMemberIds: null,
            selectedHolidayCalendarIds: null,
        ));

    $this->userRepository
        ->shouldReceive('updateCalendarSubscriptionConfig')
        ->once()
        ->with($userId, null, [$validId]);

    $this->handler->handle($userId, null, [$validId, $invalidId]);
});

it('persists empty arrays explicitly', function (): void {
    $userId = Uuid::uuid4()->toString();

    $this->configHandler
        ->shouldReceive('handle')
        ->with($userId)
        ->andReturn(new CalendarSubscriptionConfigDTO([], [], null, null));

    $this->userRepository
        ->shouldReceive('updateCalendarSubscriptionConfig')
        ->once()
        ->with($userId, [], []);

    $this->handler->handle($userId, [], []);
});
