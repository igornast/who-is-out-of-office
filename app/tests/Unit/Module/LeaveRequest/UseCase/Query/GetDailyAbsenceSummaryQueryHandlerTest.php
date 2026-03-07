<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetDailyAbsenceSummaryQueryHandler;
use App\Shared\DTO\Dashboard\DailyAbsenceSummaryDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new GetDailyAbsenceSummaryQueryHandler(repository: $this->repository);
});

it('returns 5 business days for the current week', function () {
    $this->repository
        ->expects('findForDates')
        ->once()
        ->withArgs(fn (DateTimeImmutable $start, DateTimeImmutable $end, array $statuses) => '1' === $start->format('N')
                && '5' === $end->format('N')
                && $statuses === [LeaveRequestStatusEnum::Approved])
        ->andReturn([]);

    $result = $this->handler->handle();

    expect($result)->toHaveCount(5)
        ->and($result[0])->toBeInstanceOf(DailyAbsenceSummaryDTO::class)
        ->and($result[0]->dayName)->toBe((new DateTimeImmutable('monday this week'))->format('D'))
        ->and($result[4]->dayName)->toBe((new DateTimeImmutable('friday this week'))->format('D'));
});

it('accepts a custom week start date', function () {
    $customMonday = new DateTimeImmutable('2026-03-02');

    $this->repository
        ->expects('findForDates')
        ->once()
        ->withArgs(fn (DateTimeImmutable $start, DateTimeImmutable $end, array $statuses) => '2026-03-02' === $start->format('Y-m-d')
                && '2026-03-06' === $end->format('Y-m-d'))
        ->andReturn([]);

    $result = $this->handler->handle($customMonday);

    expect($result)->toHaveCount(5)
        ->and($result[0]->dayNumber)->toBe(2)
        ->and($result[4]->dayNumber)->toBe(6);
});

it('marks today correctly', function () {
    $this->repository
        ->expects('findForDates')
        ->andReturn([]);

    $result = $this->handler->handle();

    $todayDow = (int) (new DateTimeImmutable('today'))->format('N');
    foreach ($result as $i => $day) {
        if ($todayDow >= 1 && $todayDow <= 5 && $i === $todayDow - 1) {
            expect($day->isToday)->toBeTrue();
        } else {
            expect($day->isToday)->toBeFalse();
        }
    }
});

it('counts absences per day and deduplicates by user', function () {
    $monday = new DateTimeImmutable('monday this week');

    $lr1 = LeaveRequestDTOFixture::create([
        'startDate' => $monday,
        'endDate' => $monday->modify('+2 days'),
    ]);

    $lr2 = LeaveRequestDTOFixture::create([
        'startDate' => $monday,
        'endDate' => $monday,
    ]);

    $this->repository
        ->expects('findForDates')
        ->andReturn([$lr1, $lr2]);

    $result = $this->handler->handle();

    expect($result[0]->absenceCount)->toBe(2)
        ->and($result[0]->avatars)->toHaveCount(2)
        ->and($result[2]->absenceCount)->toBe(1)
        ->and($result[4]->absenceCount)->toBe(0);
});

it('includes leave type background color in avatars', function () {
    $monday = new DateTimeImmutable('monday this week');

    $lr = LeaveRequestDTOFixture::create([
        'startDate' => $monday,
        'endDate' => $monday,
    ]);

    $this->repository
        ->expects('findForDates')
        ->andReturn([$lr]);

    $result = $this->handler->handle();

    expect($result[0]->avatars[0])->toHaveKey('leaveTypeBackgroundColor');
});

it('returns empty avatars for days with no absences', function () {
    $this->repository
        ->expects('findForDates')
        ->andReturn([]);

    $result = $this->handler->handle();

    foreach ($result as $day) {
        expect($day->absenceCount)->toBe(0)
            ->and($day->avatars)->toBeEmpty();
    }
});
