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
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->holidayFacade = mock(HolidayFacadeInterface::class);
    $this->security = mock(Security::class);

    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->urlGenerator->allows('generate')->andReturn('/app/dashboard/leave-request/123');

    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $key) => $key);

    $this->user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@whoisooo.app',
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
        translator: $this->translator,
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

it('shows all leave requests when no person filter is set', function (): void {
    $currentUserId = $this->user->id->toString();
    $otherPersonId = Uuid::uuid4()->toString();

    $currentUserLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $currentUserId, 'firstName' => 'John']),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);
    $otherPersonLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $otherPersonId, 'firstName' => 'Other']),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([$currentUserLR, $otherPersonLR]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = new SetDataEvent(new DateTime('2026-04-01'), new DateTime('2026-04-30'), []);
    $this->subscriber->onCalendarSetData($event);

    $leaveEvents = array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'leave');
    expect($leaveEvents)->toHaveCount(2);
});

it('filters leave requests to current user and selected persons', function (): void {
    $currentUserId = $this->user->id->toString();
    $selectedId = Uuid::uuid4()->toString();
    $otherId = Uuid::uuid4()->toString();

    $currentUserLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $currentUserId, 'firstName' => 'Current', 'lastName' => 'Person']),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);
    $selectedLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $selectedId, 'firstName' => 'Selected', 'lastName' => 'Person']),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);
    $otherLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $otherId, 'firstName' => 'Other', 'lastName' => 'Person']),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([$currentUserLR, $selectedLR, $otherLR]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = new SetDataEvent(
        new DateTime('2026-04-01'),
        new DateTime('2026-04-30'),
        ['personIds' => [$selectedId]],
    );
    $this->subscriber->onCalendarSetData($event);

    $leaveEvents = array_values(array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'leave'));
    $names = array_map(fn ($e) => $e->getOptions()['extendedProps']['employeeName'], $leaveEvents);

    expect($leaveEvents)->toHaveCount(2)
        ->and($names)->toContain('Current Person')
        ->and($names)->toContain('Selected Person');
});

it('always includes current user absences even when not in person selection', function (): void {
    $currentUserId = $this->user->id->toString();
    $selectedId = Uuid::uuid4()->toString();

    $currentUserLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $currentUserId, 'firstName' => 'Current']),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);
    $selectedLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $selectedId, 'firstName' => 'Selected']),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([$currentUserLR, $selectedLR]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = new SetDataEvent(
        new DateTime('2026-04-01'),
        new DateTime('2026-04-30'),
        ['personIds' => [$selectedId]],
    );
    $this->subscriber->onCalendarSetData($event);

    $leaveEvents = array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'leave');
    expect($leaveEvents)->toHaveCount(2);
});

it('ignores invalid UUIDs in personIds filter', function (): void {
    $currentUserId = $this->user->id->toString();
    $validId = Uuid::uuid4()->toString();

    $currentUserLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $currentUserId, 'firstName' => 'Current', 'lastName' => 'Person']),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);
    $validLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $validId, 'firstName' => 'Valid', 'lastName' => 'Person']),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([$currentUserLR, $validLR]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = new SetDataEvent(
        new DateTime('2026-04-01'),
        new DateTime('2026-04-30'),
        ['personIds' => [$validId, 'not-a-uuid', '<script>alert(1)</script>']],
    );
    $this->subscriber->onCalendarSetData($event);

    $leaveEvents = array_values(array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'leave'));
    $names = array_map(fn ($e) => $e->getOptions()['extendedProps']['employeeName'], $leaveEvents);

    expect($leaveEvents)->toHaveCount(2)
        ->and($names)->toContain('Current Person')
        ->and($names)->toContain('Valid Person');
});

it('treats all invalid personIds as no filter (shows everyone)', function (): void {
    $currentUserId = $this->user->id->toString();
    $otherId = Uuid::uuid4()->toString();

    $currentUserLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $currentUserId]),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);
    $otherLR = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['id' => $otherId]),
        'status' => LeaveRequestStatusEnum::Approved,
    ]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([$currentUserLR, $otherLR]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = new SetDataEvent(
        new DateTime('2026-04-01'),
        new DateTime('2026-04-30'),
        ['personIds' => ['garbage', '123', "'; DROP TABLE--"]],
    );
    $this->subscriber->onCalendarSetData($event);

    $leaveEvents = array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'leave');
    expect($leaveEvents)->toHaveCount(2);
});

it('ignores invalid UUID in leaveTypeId filter', function (): void {
    $leaveRequest = LeaveRequestDTOFixture::create([
        'status' => LeaveRequestStatusEnum::Approved,
    ]);

    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')->andReturn([$leaveRequest]);
    $this->userFacade->expects('getUsersWithBirthdaysForDates')->andReturn([]);
    $this->holidayFacade->expects('getHolidayCalendarForCountry')->never();

    $event = new SetDataEvent(
        new DateTime('2026-04-01'),
        new DateTime('2026-04-30'),
        ['leaveTypeId' => 'not-a-valid-uuid'],
    );
    $this->subscriber->onCalendarSetData($event);

    $leaveEvents = array_filter($event->getEvents(), fn ($e) => ($e->getOptions()['extendedProps']['type'] ?? null) === 'leave');
    expect($leaveEvents)->toHaveCount(1);
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
