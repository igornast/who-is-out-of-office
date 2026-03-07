<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\EventSubscriber\CalendarSubscriber;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use CalendarBundle\Event\SetDataEvent;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

beforeEach(function (): void {
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->holidayFacade = mock(HolidayFacadeInterface::class);
    $this->security = mock(Security::class);

    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->urlGenerator->allows('generate')->andReturn('/app/dashboard/leave-request/123');

    $this->user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@ooo.com',
        password: 'password',
        workingDays: [1, 2, 3, 4, 5],
    );

    $this->security->allows('getUser')->andReturn($this->user);

    $this->subscriber = new CalendarSubscriber(
        leaveRequestFacade: $this->leaveRequestFacade,
        userFacade: $this->userFacade,
        holidayFacade: $this->holidayFacade,
        urlGenerator: $this->urlGenerator,
        security: $this->security,
    );
});

function createSetDataEvent(DateTime $start, DateTime $end): SetDataEvent
{
    return new SetDataEvent($start, $end, []);
}

it('subscribes to SetDataEvent', function (): void {
    $events = CalendarSubscriber::getSubscribedEvents();

    expect($events)->toHaveKey(SetDataEvent::class)
        ->and($events[SetDataEvent::class])->toBe('onCalendarSetData');
});

it('adds leave request events with enriched extended props', function (): void {
    $leaveType = LeaveRequestTypeDTOFixture::create([
        'name' => 'Vacation',
        'icon' => '🏖',
        'backgroundColor' => '#d4edda',
        'borderColor' => '#c3e6cb',
        'textColor' => '#155724',
    ]);

    $user = UserDTOFixture::create(['firstName' => 'Jane', 'lastName' => 'Smith']);

    $leaveRequest = LeaveRequestDTOFixture::create([
        'id' => Uuid::uuid4(),
        'status' => LeaveRequestStatusEnum::Approved,
        'leaveType' => $leaveType,
        'user' => $user,
        'startDate' => new DateTimeImmutable('2026-03-10'),
        'endDate' => new DateTimeImmutable('2026-03-14'),
        'workDays' => 5,
        'comment' => 'Family trip',
    ]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([$leaveRequest]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = createSetDataEvent(new DateTime('2026-03-01'), new DateTime('2026-03-31'));
    $this->subscriber->onCalendarSetData($event);

    $events = $event->getEvents();
    $leaveEvents = array_filter($events, fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'leave');
    $leaveEvent = array_values($leaveEvents)[0];

    $options = $leaveEvent->getOptions();
    expect($options['allDay'])->toBeTrue()
        ->and($options['extendedProps']['type'])->toBe('leave')
        ->and($options['extendedProps']['status'])->toBe('approved')
        ->and($options['extendedProps']['leaveTypeName'])->toBe('Vacation')
        ->and($options['extendedProps']['leaveTypeIcon'])->toBe('🏖')
        ->and($options['extendedProps']['employeeName'])->toBe('Jane Smith')
        ->and($options['extendedProps']['workDays'])->toBe(5)
        ->and($options['extendedProps']['comment'])->toBe('Family trip')
        ->and($options['extendedProps']['startDate'])->toBe('Mar 10, 2026')
        ->and($options['extendedProps']['endDate'])->toBe('Mar 14, 2026')
        ->and($options['extendedProps']['detailUrl'])->toBeString()
        ->and($options)->not->toHaveKey('url');
});

it('applies pending style for pending leave requests', function (): void {
    $leaveRequest = LeaveRequestDTOFixture::create([
        'status' => LeaveRequestStatusEnum::Pending,
    ]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([$leaveRequest]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = createSetDataEvent(new DateTime('2026-03-01'), new DateTime('2026-03-31'));
    $this->subscriber->onCalendarSetData($event);

    $leaveEvents = array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'leave');
    $leaveEvent = array_values($leaveEvents)[0];

    expect($leaveEvent->getOptions()['backgroundColor'])->toBe('#fff3cd')
        ->and($leaveEvent->getOptions()['borderColor'])->toBe('#ffeeba')
        ->and($leaveEvent->getOptions()['extendedProps']['status'])->toBe('pending');
});

it('adds birthday events with extended props', function (): void {
    $userDTO = UserDTOFixture::create([
        'firstName' => 'Alice',
        'lastName' => 'Wonder',
        'birthDate' => new DateTimeImmutable('1990-03-15'),
    ]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([$userDTO]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = createSetDataEvent(new DateTime('2026-03-01'), new DateTime('2026-03-31'));
    $this->subscriber->onCalendarSetData($event);

    $birthdayEvents = array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'birthday');
    $birthdayEvent = array_values($birthdayEvents)[0];

    $options = $birthdayEvent->getOptions();
    expect($options['extendedProps']['type'])->toBe('birthday')
        ->and($options['extendedProps']['employeeName'])->toBe('Alice Wonder')
        ->and($options['extendedProps']['date'])->toBe('Mar 15')
        ->and($options['className'])->toBe(['birthday-event'])
        ->and($options['allDay'])->toBeTrue();
});

it('skips birthday events when birthDate is null', function (): void {
    $userDTO = UserDTOFixture::create(['birthDate' => null]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([$userDTO]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = createSetDataEvent(new DateTime('2026-03-01'), new DateTime('2026-03-31'));
    $this->subscriber->onCalendarSetData($event);

    $birthdayEvents = array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'birthday');
    expect($birthdayEvents)->toBeEmpty();
});

it('adds public holiday events with extended props', function (): void {
    $this->user->holidayCalendar = new App\Infrastructure\Doctrine\Entity\HolidayCalendar(
        id: Uuid::uuid4(),
        countryCode: 'DE',
        countryName: 'Germany',
    );

    $holiday = new PublicHolidayDTO(
        id: Uuid::uuid4()->toString(),
        description: 'Good Friday',
        countryCode: 'DE',
        date: new DateTimeImmutable('2026-04-03'),
    );

    $calendar = new PublicHolidayCalendarDTO(
        id: Uuid::uuid4(),
        countryCode: 'DE',
        countryName: 'Germany',
        holidays: [$holiday],
    );

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->with('DE')->andReturn($calendar);

    $event = createSetDataEvent(new DateTime('2026-04-01'), new DateTime('2026-04-30'));
    $this->subscriber->onCalendarSetData($event);

    $holidayEvents = array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'holiday');
    $holidayEvent = array_values($holidayEvents)[0];

    $options = $holidayEvent->getOptions();
    expect($options['extendedProps']['type'])->toBe('holiday')
        ->and($options['extendedProps']['description'])->toBe('Good Friday')
        ->and($options['extendedProps']['date'])->toBe('Apr 3, 2026')
        ->and($options['allDay'])->toBeTrue()
        ->and($options['backgroundColor'])->toBe('#fde2e2');
});

it('skips public holidays when user has no calendarCountryCode', function (): void {
    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = createSetDataEvent(new DateTime('2026-03-01'), new DateTime('2026-03-31'));
    $this->subscriber->onCalendarSetData($event);

    $holidayEvents = array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'holiday');
    expect($holidayEvents)->toBeEmpty();
});

it('marks non-working days as background events', function (): void {
    $this->user->workingDays = [1, 2, 3, 4];

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $start = new DateTime('2026-03-02');
    $end = new DateTime('2026-03-07');
    $event = createSetDataEvent($start, $end);
    $this->subscriber->onCalendarSetData($event);

    $offDayEvents = array_filter($event->getEvents(), fn ($e) => '⛔ Off Day' === $e->getTitle());

    expect(count($offDayEvents))->toBe(1);

    $offDay = array_values($offDayEvents)[0];
    expect($offDay->getOptions()['display'])->toBe('background')
        ->and($offDay->getOptions()['allDay'])->toBeTrue();
});
