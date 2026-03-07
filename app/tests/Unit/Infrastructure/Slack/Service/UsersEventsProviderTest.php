<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Service\UsersEventsProvider;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;
use App\Tests\_fixtures\Shared\DTO\Holiday\UserPublicHolidaysDTOFixture;
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
