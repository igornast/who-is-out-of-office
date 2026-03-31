<?php

declare(strict_types=1);

use App\Infrastructure\Ical\Controller\CalendarExportController;
use App\Infrastructure\Ical\Service\CalendarRequestVerifier;
use App\Infrastructure\Ical\Service\IcalLeaveRequestsTransformer;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->calendarRequestVerifier = mock(CalendarRequestVerifier::class);
    $this->leaveRequestsTransformer = mock(IcalLeaveRequestsTransformer::class);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);

    $this->controller = new CalendarExportController(
        calendarRequestVerifier: $this->calendarRequestVerifier,
        leaveRequestsTransformer: $this->leaveRequestsTransformer,
        userFacade: $this->userFacade,
        leaveRequestFacade: $this->leaveRequestFacade,
    );
});

it('returns 403 when request verification fails', function (): void {
    $userDTO = UserDTOFixture::create();
    $this->userFacade->expects('getUser')->with('user-id')->andReturn($userDTO);
    $this->calendarRequestVerifier->expects('isValid')->with($userDTO, 'invalid-secret')->andReturn(false);

    $response = ($this->controller)('user-id', 'invalid-secret');

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('returns ical calendar response with correct headers when request is valid', function (): void {
    $userDTO = UserDTOFixture::create();
    $this->userFacade->expects('getUser')->with('user-id')->andReturn($userDTO);
    $this->calendarRequestVerifier->expects('isValid')->andReturn(true);
    $this->leaveRequestFacade->expects('getLeaveRequestsForDates')
        ->withArgs(fn (DateTimeImmutable $start, DateTimeImmutable $end, array $statuses) => [LeaveRequestStatusEnum::Approved] === $statuses)
        ->andReturn([]);
    $this->leaveRequestsTransformer->expects('transformToCalendar')
        ->with([])
        ->andReturn((new CalendarFactory())->createCalendar(new Calendar([])));

    $response = ($this->controller)('user-id', 'valid-secret');

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->headers->get('Content-Type'))->toBe('text/calendar; charset=utf-8')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="cal.ics"')
        ->and($response->headers->get('Cache-Control'))->toBe('max-age=3600, public');
});
