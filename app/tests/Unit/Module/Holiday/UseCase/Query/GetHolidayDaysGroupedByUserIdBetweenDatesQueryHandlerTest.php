<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;
use App\Module\Holiday\UseCase\Query\GetHolidayDaysGroupedByUserIdBetweenDatesQueryHandler;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;
use App\Tests\_fixtures\Shared\DTO\Holiday\UserPublicHolidaysDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->publicHolidayRepository = mock(PublicHolidayRepositoryInterface::class);

    $this->handler = new GetHolidayDaysGroupedByUserIdBetweenDatesQueryHandler(
        publicHolidayRepository: $this->publicHolidayRepository
    );
});

it('returns holidays grouped by user id between dates', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');

    $user1 = UserDTOFixture::create(['id' => 'user-1']);
    $user2 = UserDTOFixture::create(['id' => 'user-2']);

    $holiday1 = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'New Year',
        'countryCode' => 'US',
        'date' => new DateTimeImmutable('2025-01-01'),
    ]);

    $holiday2 = PublicHolidayDTOFixture::create([
        'id' => 'holiday-2',
        'description' => 'MLK Day',
        'countryCode' => 'US',
        'date' => new DateTimeImmutable('2025-01-20'),
    ]);

    $userHolidays1 = UserPublicHolidaysDTOFixture::create([
        'user' => $user1,
        'holidays' => [$holiday1],
    ]);

    $userHolidays2 = UserPublicHolidaysDTOFixture::create([
        'user' => $user2,
        'holidays' => [$holiday2],
    ]);

    $expectedResult = [
        'user-1' => $userHolidays1,
        'user-2' => $userHolidays2,
    ];

    $this->publicHolidayRepository
        ->expects('findBetweenDatesGroupedByUser')
        ->once()
        ->with($startDate, $endDate)
        ->andReturn($expectedResult);

    $result = $this->handler->handle($startDate, $endDate);

    expect($result)->toBe($expectedResult)
        ->and($result)->toHaveKey('user-1')
        ->and($result)->toHaveKey('user-2')
        ->and($result['user-1'])->toBeInstanceOf(UserPublicHolidaysDTO::class)
        ->and($result['user-2'])->toBeInstanceOf(UserPublicHolidaysDTO::class);
});

it('returns empty array when no holidays found', function () {
    $startDate = new DateTimeImmutable('2025-06-01');
    $endDate = new DateTimeImmutable('2025-06-30');

    $this->publicHolidayRepository
        ->expects('findBetweenDatesGroupedByUser')
        ->once()
        ->with($startDate, $endDate)
        ->andReturn([]);

    $result = $this->handler->handle($startDate, $endDate);

    expect($result)->toBe([])
        ->and($result)->toBeEmpty();
});
