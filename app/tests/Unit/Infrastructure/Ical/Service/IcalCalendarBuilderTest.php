<?php

declare(strict_types=1);

use App\Infrastructure\Ical\Service\IcalCalendarBuilder;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->builder = new IcalCalendarBuilder();
});

it('returns a valid empty calendar when both lists are empty', function (): void {
    $output = (string) $this->builder->build([], []);

    expect($output)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR')
        ->and(substr_count($output, 'BEGIN:VEVENT'))->toBe(0);
});

it('keeps the existing leave-request event shape (regression)', function (): void {
    $leaveRequest = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['firstName' => 'Jane', 'lastName' => 'Smith']),
        'leaveType' => LeaveRequestTypeDTOFixture::create(['name' => 'Annual Leave', 'icon' => '']),
        'startDate' => new DateTimeImmutable('2025-06-01'),
        'endDate' => new DateTimeImmutable('2025-06-05'),
        'comment' => 'Summer trip',
    ]);

    $output = (string) $this->builder->build([$leaveRequest], []);

    expect($output)
        ->toContain('Jane')
        ->toContain('Smith')
        ->toContain('Annual Leave')
        ->toContain('Summer trip');
});

it('emits one VEVENT per public holiday prefixed with the country flag', function (): void {
    $holiday = new PublicHolidayDTO(
        id: 'h1',
        description: 'Midsummer Day',
        countryCode: 'SE',
        date: new DateTimeImmutable('2025-06-21'),
    );

    $output = (string) $this->builder->build([], [$holiday]);

    expect(substr_count($output, 'BEGIN:VEVENT'))->toBe(1)
        ->and($output)
        ->toContain('Midsummer Day')
        ->toContain('🇸🇪 Midsummer Day');
});

it('falls back to a white flag for an invalid country code', function (): void {
    $holiday = new PublicHolidayDTO(
        id: 'h1',
        description: 'Unknown Day',
        countryCode: 'X',
        date: new DateTimeImmutable('2025-06-21'),
    );

    $output = (string) $this->builder->build([], [$holiday]);

    expect($output)->toContain('🏳 Unknown Day');
});

it('emits a VEVENT for each item across mixed inputs', function (): void {
    $leaveRequests = [LeaveRequestDTOFixture::create(), LeaveRequestDTOFixture::create()];
    $holidays = [
        new PublicHolidayDTO(id: 'h1', description: 'Holiday A', countryCode: 'DE', date: new DateTimeImmutable('2025-06-01')),
        new PublicHolidayDTO(id: 'h2', description: 'Holiday B', countryCode: 'SE', date: new DateTimeImmutable('2025-06-02')),
        new PublicHolidayDTO(id: 'h3', description: 'Holiday C', countryCode: 'SE', date: new DateTimeImmutable('2025-06-03')),
    ];

    $output = (string) $this->builder->build($leaveRequests, $holidays);

    expect(substr_count($output, 'BEGIN:VEVENT'))->toBe(5);
});
