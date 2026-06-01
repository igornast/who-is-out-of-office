<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Service\UsersEventsProvider;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;
use App\Tests\_fixtures\Shared\DTO\Holiday\UserPublicHolidaysDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->holidayFacade = mock(HolidayFacadeInterface::class);
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);

    $this->provider = new UsersEventsProvider(
        holidayFacade: $this->holidayFacade,
        leaveRequestFacade: $this->leaveRequestFacade,
    );
});

it('filters out holidays falling on weekends', function () {
    $user = UserDTOFixture::create(['id' => 'user-1']);

    $mondayHoliday = PublicHolidayDTOFixture::create([
        'description' => 'Monday Holiday',
        'date' => new DateTimeImmutable('2026-03-09'),
    ]);

    $saturdayHoliday = PublicHolidayDTOFixture::create([
        'description' => 'Saturday Holiday',
        'date' => new DateTimeImmutable('2026-03-07'),
    ]);

    $sundayHoliday = PublicHolidayDTOFixture::create([
        'description' => 'Sunday Holiday',
        'date' => new DateTimeImmutable('2026-03-08'),
    ]);

    $dto = UserPublicHolidaysDTOFixture::create([
        'user' => $user,
        'holidays' => [$mondayHoliday, $saturdayHoliday, $sundayHoliday],
    ]);

    $result = $this->provider->filterWeekendHolidays(['user-1' => $dto]);

    expect($result)->toHaveCount(1)
        ->and($result['user-1']->holidays)->toHaveCount(1)
        ->and($result['user-1']->holidays[0]->description)->toBe('Monday Holiday');
});

it('removes user entry entirely when all holidays fall on weekends', function () {
    $user = UserDTOFixture::create(['id' => 'user-1']);

    $saturdayHoliday = PublicHolidayDTOFixture::create([
        'description' => 'Saturday Holiday',
        'date' => new DateTimeImmutable('2026-03-07'),
    ]);

    $sundayHoliday = PublicHolidayDTOFixture::create([
        'description' => 'Sunday Holiday',
        'date' => new DateTimeImmutable('2026-03-08'),
    ]);

    $dto = UserPublicHolidaysDTOFixture::create([
        'user' => $user,
        'holidays' => [$saturdayHoliday, $sundayHoliday],
    ]);

    $result = $this->provider->filterWeekendHolidays(['user-1' => $dto]);

    expect($result)->toBeEmpty();
});

it('keeps all holidays when none fall on weekends', function () {
    $user = UserDTOFixture::create(['id' => 'user-1']);

    $tuesdayHoliday = PublicHolidayDTOFixture::create([
        'description' => 'Tuesday Holiday',
        'date' => new DateTimeImmutable('2026-03-10'),
    ]);

    $wednesdayHoliday = PublicHolidayDTOFixture::create([
        'description' => 'Wednesday Holiday',
        'date' => new DateTimeImmutable('2026-03-11'),
    ]);

    $dto = UserPublicHolidaysDTOFixture::create([
        'user' => $user,
        'holidays' => [$tuesdayHoliday, $wednesdayHoliday],
    ]);

    $result = $this->provider->filterWeekendHolidays(['user-1' => $dto]);

    expect($result)->toHaveCount(1)
        ->and($result['user-1']->holidays)->toHaveCount(2);
});

it('returns empty array when input is empty', function () {
    $result = $this->provider->filterWeekendHolidays([]);

    expect($result)->toBeEmpty();
});

it('merges approved leave requests and public holidays for the same user', function () {
    $startDate = new DateTimeImmutable('2026-03-01');
    $endDate = new DateTimeImmutable('2026-03-31');

    $leaveRequest = LeaveRequestDTOFixture::create(['status' => LeaveRequestStatusEnum::Approved]);
    $holidaysDto = UserPublicHolidaysDTOFixture::create();

    $this->leaveRequestFacade
        ->shouldReceive('getLeaveRequestsForDatesGroupedByUserId')
        ->once()
        ->with($startDate, $endDate, [LeaveRequestStatusEnum::Approved])
        ->andReturn(['user-1' => [$leaveRequest]]);

    $this->holidayFacade
        ->shouldReceive('getHolidaysForDatesGroupedByUserId')
        ->once()
        ->with($startDate, $endDate)
        ->andReturn(['user-1' => $holidaysDto]);

    $result = $this->provider->provideMergedAbsencesPerUser($startDate, $endDate);

    expect($result)->toHaveKey('user-1')
        ->and($result['user-1'])->toHaveCount(2)
        ->and($result['user-1'][0])->toBeInstanceOf(LeaveRequestDTO::class)
        ->and($result['user-1'][1])->toBeInstanceOf(UserPublicHolidaysDTO::class);
});

it('returns only leave requests for users without public holidays', function () {
    $startDate = new DateTimeImmutable('2026-03-01');
    $endDate = new DateTimeImmutable('2026-03-31');

    $leaveRequest = LeaveRequestDTOFixture::create(['status' => LeaveRequestStatusEnum::Approved]);

    $this->leaveRequestFacade
        ->shouldReceive('getLeaveRequestsForDatesGroupedByUserId')
        ->once()
        ->andReturn(['user-1' => [$leaveRequest]]);

    $this->holidayFacade
        ->shouldReceive('getHolidaysForDatesGroupedByUserId')
        ->once()
        ->andReturn([]);

    $result = $this->provider->provideMergedAbsencesPerUser($startDate, $endDate);

    expect($result)->toHaveKey('user-1')
        ->and($result['user-1'])->toHaveCount(1)
        ->and($result['user-1'][0])->toBeInstanceOf(LeaveRequestDTO::class);
});

it('returns only public holidays for users without leave requests', function () {
    $startDate = new DateTimeImmutable('2026-03-01');
    $endDate = new DateTimeImmutable('2026-03-31');

    $holidaysDto = UserPublicHolidaysDTOFixture::create();

    $this->leaveRequestFacade
        ->shouldReceive('getLeaveRequestsForDatesGroupedByUserId')
        ->once()
        ->andReturn([]);

    $this->holidayFacade
        ->shouldReceive('getHolidaysForDatesGroupedByUserId')
        ->once()
        ->andReturn(['user-1' => $holidaysDto]);

    $result = $this->provider->provideMergedAbsencesPerUser($startDate, $endDate);

    expect($result)->toHaveKey('user-1')
        ->and($result['user-1'])->toHaveCount(1)
        ->and($result['user-1'][0])->toBeInstanceOf(UserPublicHolidaysDTO::class);
});

it('returns an empty array when there are no absences', function () {
    $startDate = new DateTimeImmutable('2026-03-01');
    $endDate = new DateTimeImmutable('2026-03-31');

    $this->leaveRequestFacade
        ->shouldReceive('getLeaveRequestsForDatesGroupedByUserId')
        ->once()
        ->andReturn([]);

    $this->holidayFacade
        ->shouldReceive('getHolidaysForDatesGroupedByUserId')
        ->once()
        ->andReturn([]);

    $result = $this->provider->provideMergedAbsencesPerUser($startDate, $endDate);

    expect($result)->toBeEmpty();
});
