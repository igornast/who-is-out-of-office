<?php

declare(strict_types=1);

use App\Infrastructure\Ical\Service\IcalLeaveRequestsTransformer;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->transformer = new IcalLeaveRequestsTransformer();
});

it('returns a valid iCal component for an empty list', function (): void {
    $component = $this->transformer->transformToCalendar([]);

    expect((string) $component)->toContain('BEGIN:VCALENDAR');
});

it('includes user name in the event summary', function (): void {
    $leaveRequest = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['firstName' => 'Jane', 'lastName' => 'Smith']),
        'leaveType' => LeaveRequestTypeDTOFixture::create(['icon' => '']),
    ]);

    $output = (string) $this->transformer->transformToCalendar([$leaveRequest]);

    expect($output)->toContain('Jane')->toContain('Smith');
});

it('includes leave type name and comment in the event description', function (): void {
    $leaveRequest = LeaveRequestDTOFixture::create([
        'leaveType' => LeaveRequestTypeDTOFixture::create(['name' => 'Annual Leave', 'icon' => '']),
        'startDate' => new DateTimeImmutable('2025-06-01'),
        'endDate' => new DateTimeImmutable('2025-06-05'),
        'comment' => 'Summer trip',
    ]);

    $output = (string) $this->transformer->transformToCalendar([$leaveRequest]);

    expect($output)
        ->toContain('Annual Leave')
        ->toContain('Summer trip');
});

it('produces one vevent per leave request', function (): void {
    $leaveRequests = [
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
    ];

    $output = (string) $this->transformer->transformToCalendar($leaveRequests);

    expect(substr_count($output, 'BEGIN:VEVENT'))->toBe(3);
});
