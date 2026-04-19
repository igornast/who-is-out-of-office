<?php

declare(strict_types=1);

use App\Module\Admin\Twig\Components\AbsenceWeekView;
use App\Shared\DTO\Dashboard\DailyAbsenceSummaryDTO;
use App\Shared\Facade\LeaveRequestFacadeInterface;

beforeEach(function (): void {
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->component = new AbsenceWeekView($this->leaveRequestFacade);
});

it('defaults to current monday when weekStart is null', function (): void {
    $expectedMonday = new DateTimeImmutable('monday this week');

    $this->leaveRequestFacade->expects('getDailyAbsenceSummary')
        ->withArgs(fn (DateTimeImmutable $date) => $date->format('Y-m-d') === $expectedMonday->format('Y-m-d'))
        ->andReturn([]);

    $this->component->getDays();

    expect($this->component->isCurrentWeek())->toBeTrue();
});

it('uses provided weekStart when set', function (): void {
    $this->component->weekStart = '2026-04-13';

    $this->leaveRequestFacade->expects('getDailyAbsenceSummary')
        ->withArgs(fn (DateTimeImmutable $date) => '2026-04-13' === $date->format('Y-m-d'))
        ->andReturn([]);

    $this->component->getDays();
});

it('moves to previous week on prev()', function (): void {
    $this->component->weekStart = '2026-04-13';

    $this->component->prev();

    expect($this->component->weekStart)->toBe('2026-04-06');
});

it('moves to next week on next()', function (): void {
    $this->component->weekStart = '2026-04-13';

    $this->component->next();

    expect($this->component->weekStart)->toBe('2026-04-20');
});

it('returns absence summary from the facade', function (): void {
    $summary = new DailyAbsenceSummaryDTO(
        date: new DateTimeImmutable('2026-04-13'),
        dayName: 'Mon',
        dayNumber: 13,
        isToday: false,
        absenceCount: 2,
        avatars: [],
    );

    $this->component->weekStart = '2026-04-13';
    $this->leaveRequestFacade->expects('getDailyAbsenceSummary')->andReturn([$summary]);

    expect($this->component->getDays())->toBe([$summary]);
});

it('reports isCurrentWeek as false when weekStart is in a different week', function (): void {
    $this->component->weekStart = '2020-01-06';

    expect($this->component->isCurrentWeek())->toBeFalse();
});

it('formats date range within a single month as compact range', function (): void {
    $this->component->weekStart = '2026-04-13';

    expect($this->component->getFormattedDateRange())->toBe('Apr 13–17, 2026');
});

it('formats date range spanning two months with both month labels', function (): void {
    $this->component->weekStart = '2026-03-30';

    expect($this->component->getFormattedDateRange())->toBe('Mar 30 – Apr 3, 2026');
});
