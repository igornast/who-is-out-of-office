<?php

declare(strict_types=1);

use App\Infrastructure\Ical\Controller\CalendarExportController;
use App\Infrastructure\Ical\Service\CalendarRequestVerifier;
use App\Infrastructure\Ical\Service\IcalCalendarBuilder;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionConfigDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->calendarRequestVerifier = mock(CalendarRequestVerifier::class);
    $this->calendarBuilder = mock(IcalCalendarBuilder::class);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->holidayFacade = mock(HolidayFacadeInterface::class);

    $this->controller = new CalendarExportController(
        calendarRequestVerifier: $this->calendarRequestVerifier,
        calendarBuilder: $this->calendarBuilder,
        userFacade: $this->userFacade,
        leaveRequestFacade: $this->leaveRequestFacade,
        holidayFacade: $this->holidayFacade,
    );
});

it('returns 403 when request verification fails', function (): void {
    $userDTO = UserDTOFixture::create();
    $this->userFacade->expects('getUser')->with('user-id')->andReturn($userDTO);
    $this->calendarRequestVerifier->expects('isValid')->with($userDTO, 'invalid-secret')->andReturn(false);

    $response = ($this->controller)('user-id', 'invalid-secret');

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('returns 403 when user is not found', function (): void {
    $this->userFacade->expects('getUser')->with('unknown-id')->andReturn(null);

    $response = ($this->controller)('unknown-id', 'any-secret');

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('returns ical calendar response with correct headers when request is valid', function (): void {
    $userDTO = UserDTOFixture::create(['id' => 'user-id']);
    $this->userFacade->expects('getUser')->with('user-id')->andReturn($userDTO);
    $this->calendarRequestVerifier->expects('isValid')->andReturn(true);
    $this->userFacade->expects('getCalendarSubscriptionConfig')
        ->with('user-id')
        ->andReturn(new CalendarSubscriptionConfigDTO([], [], [], [], null, null));
    $this->leaveRequestFacade->expects('getLeaveRequestsForUsersBetween')
        ->withArgs(fn (array $userIds, array $statuses, DateTimeImmutable $start, DateTimeImmutable $end) => ['user-id'] === $userIds
            && [LeaveRequestStatusEnum::Approved] === $statuses)
        ->andReturn([]);
    $this->holidayFacade->shouldNotReceive('getHolidaysForCalendarsBetween');
    $this->calendarBuilder->expects('build')
        ->withArgs(fn (array $leaveRequests, array $holidays) => [] === $leaveRequests && [] === $holidays)
        ->andReturn((new CalendarFactory())->createCalendar(new Calendar([])));

    $response = ($this->controller)('user-id', 'valid-secret');

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->headers->get('Content-Type'))->toBe('text/calendar; charset=utf-8')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="cal.ics"')
        ->and($response->headers->get('Cache-Control'))->toBe('max-age=3600, public');
});

it('fetches holidays when the config selects holiday calendars', function (): void {
    $userDTO = UserDTOFixture::create(['id' => 'user-id']);
    $this->userFacade->expects('getUser')->with('user-id')->andReturn($userDTO);
    $this->calendarRequestVerifier->expects('isValid')->andReturn(true);
    $this->userFacade->expects('getCalendarSubscriptionConfig')
        ->with('user-id')
        ->andReturn(new CalendarSubscriptionConfigDTO([], [], [], [], null, ['cal-1']));
    $this->leaveRequestFacade->expects('getLeaveRequestsForUsersBetween')->andReturn([]);
    $this->holidayFacade->expects('getHolidaysForCalendarsBetween')
        ->with(['cal-1'], Mockery::type(DateTimeImmutable::class), Mockery::type(DateTimeImmutable::class))
        ->andReturn([]);
    $this->calendarBuilder->expects('build')
        ->andReturn((new CalendarFactory())->createCalendar(new Calendar([])));

    $response = ($this->controller)('user-id', 'valid-secret');

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});
