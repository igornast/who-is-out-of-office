<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;
use App\Module\Holiday\UseCase\Query\GetSubdivisionsGroupedByCalendarQueryHandler;

beforeEach(function (): void {
    $this->publicHolidayRepository = mock(PublicHolidayRepositoryInterface::class);

    $this->handler = new GetSubdivisionsGroupedByCalendarQueryHandler(
        publicHolidayRepository: $this->publicHolidayRepository,
    );
});

it('returns subdivisions grouped by calendar id', function () {
    $expected = [
        'cal-de-id' => ['DE-BW', 'DE-BY', 'DE-ST'],
        'cal-ch-id' => ['CH-AG', 'CH-BE'],
    ];

    $this->publicHolidayRepository
        ->expects('findDistinctSubdivisionsGroupedByCalendar')
        ->once()
        ->andReturn($expected);

    $result = $this->handler->handle();

    expect($result)->toBe($expected)
        ->and($result)->toHaveCount(2)
        ->and($result['cal-de-id'])->toBe(['DE-BW', 'DE-BY', 'DE-ST'])
        ->and($result['cal-ch-id'])->toBe(['CH-AG', 'CH-BE']);
});

it('returns empty array when no calendars have subdivisions', function () {
    $this->publicHolidayRepository
        ->expects('findDistinctSubdivisionsGroupedByCalendar')
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle();

    expect($result)->toBe([])
        ->and($result)->toBeEmpty();
});
