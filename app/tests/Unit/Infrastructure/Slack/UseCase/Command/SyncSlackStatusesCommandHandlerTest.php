<?php

declare(strict_types=1);

use App\Infrastructure\Slack\UseCase\Command\ClearSlackStatusCommandHandler;
use App\Infrastructure\Slack\DTO\SlackHolidayStatusCandidateDTO;
use App\Infrastructure\Slack\DTO\SlackHolidayStatusDTO;
use App\Infrastructure\Slack\Repository\SlackHolidayStatusRepositoryInterface;
use App\Infrastructure\Slack\Service\HolidayStatusTextBuilder;
use App\Infrastructure\Slack\UseCase\Command\SetSlackStatusCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\SyncSlackStatusesCommandHandler;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

beforeEach(function (): void {
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->appSettingsFacade = mock(AppSettingsFacadeInterface::class);
    $this->setHandler = mock(SetSlackStatusCommandHandler::class);
    $this->clearHandler = mock(ClearSlackStatusCommandHandler::class);
    $this->holidayStatusRepository = mock(SlackHolidayStatusRepositoryInterface::class);
    $this->holidayStatusRepository->allows('findCandidatesForDay')->andReturn([])->byDefault();
    $this->holidayStatusRepository->allows('findAllTracked')->andReturn([])->byDefault();
    $this->holidayStatusTextBuilder = new HolidayStatusTextBuilder();
    $this->clock = new MockClock(new DateTimeImmutable('2026-12-25 10:00:00'));
    $this->logger = mock(LoggerInterface::class);

    $this->appSettingsFacade->allows('isSlackStatusSyncEnabled')->andReturn(true)->byDefault();
    $this->leaveRequestFacade->allows('findSyncedNeedingClear')->andReturn([])->byDefault();
    $this->leaveRequestFacade->allows('findApprovedActiveNotSynced')->andReturn([])->byDefault();

    $this->handler = new SyncSlackStatusesCommandHandler(
        $this->leaveRequestFacade,
        $this->appSettingsFacade,
        $this->setHandler,
        $this->clearHandler,
        $this->logger,
        $this->holidayStatusRepository,
        $this->holidayStatusTextBuilder,
        $this->clock,
    );
});

it('does nothing when slack status sync is disabled in settings', function (): void {
    $this->appSettingsFacade->expects('isSlackStatusSyncEnabled')->andReturn(false);

    $this->leaveRequestFacade->shouldNotReceive('findApprovedActiveNotSynced');
    $this->leaveRequestFacade->shouldNotReceive('findSyncedNeedingClear');
    $this->setHandler->shouldNotReceive('handle');
    $this->clearHandler->shouldNotReceive('handle');
    $this->holidayStatusRepository->shouldNotReceive('findCandidatesForDay');
    $this->holidayStatusRepository->shouldNotReceive('findAllTracked');

    $this->handler->handle();
});

it('sets status and marks synced for active not-synced leaves', function (): void {
    $lr1 = LeaveRequestDTOFixture::create();
    $lr2 = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->once()
        ->andReturn([$lr1, $lr2]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->once()
        ->andReturn([]);

    $this->setHandler
        ->expects('handle')
        ->twice()
        ->andReturn(true);

    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->twice();

    $this->handler->handle();
});

it('clears status and marks unsynced for ended or rejected leaves', function (): void {
    $lr1 = LeaveRequestDTOFixture::create();
    $lr2 = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->once()
        ->andReturn([]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->once()
        ->andReturn([$lr1, $lr2]);

    $this->clearHandler
        ->expects('handle')
        ->twice()
        ->andReturn(true);

    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->twice();

    $this->setHandler->shouldNotReceive('handle');

    $this->handler->handle();
});

it('does not mark synced when set handler returns false', function (): void {
    $lr = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([$lr]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([]);

    $this->setHandler
        ->expects('handle')
        ->once()
        ->andReturn(false);

    $this->leaveRequestFacade->shouldNotReceive('markExternalStatusSynced');

    $this->handler->handle();
});

it('does not mark unsynced when clear handler returns false', function (): void {
    $lr = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([$lr]);

    $this->clearHandler
        ->expects('handle')
        ->once()
        ->andReturn(false);

    $this->leaveRequestFacade->shouldNotReceive('markExternalStatusSynced');

    $this->handler->handle();
});

it('builds leave status text and emoji from the leave type', function (): void {
    $leave = LeaveRequestDTOFixture::create([
        'leaveType' => LeaveRequestTypeDTOFixture::create([
            'name' => 'Vacation',
            'slackStatusEmoji' => ':palm_tree:',
        ]),
        'endDate' => new DateTimeImmutable('2026-03-24'),
    ]);
    $defaultEmojiLeave = LeaveRequestDTOFixture::create([
        'leaveType' => LeaveRequestTypeDTOFixture::create([
            'name' => 'Training',
            'slackStatusEmoji' => null,
        ]),
        'endDate' => new DateTimeImmutable('2026-03-25'),
    ]);

    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->once()
        ->andReturn([]);
    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->once()
        ->andReturn([$leave, $defaultEmojiLeave]);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($leave->user, 'Vacation until Mar 24', ':palm_tree:', $leave->endDate)
        ->andReturn(true);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($defaultEmojiLeave->user, 'Training until Mar 25', ':calendar:', $defaultEmojiLeave->endDate)
        ->andReturn(true);
    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->twice();

    $this->handler->handle();
});

it('clears leave statuses before setting new ones', function (): void {
    $toClear = LeaveRequestDTOFixture::create();
    $toSet = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->once()
        ->andReturn([$toClear])
        ->globally()
        ->ordered();
    $this->clearHandler
        ->expects('handle')
        ->once()
        ->with($toClear->user)
        ->andReturn(true)
        ->globally()
        ->ordered();
    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->once()
        ->andReturn([$toSet])
        ->globally()
        ->ordered();
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with(
            $toSet->user,
            sprintf('%s until %s', $toSet->leaveType->name, $toSet->endDate->format('M j')),
            $toSet->leaveType->slackStatusEmoji ?? LeaveRequestTypeDTO::DEFAULT_SLACK_STATUS_EMOJI,
            $toSet->endDate,
        )
        ->andReturn(true)
        ->globally()
        ->ordered();
    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->twice();

    $this->handler->handle();
});

it('formats single-digit end days without zero padding', function (): void {
    $leave = LeaveRequestDTOFixture::create([
        'leaveType' => LeaveRequestTypeDTOFixture::create([
            'name' => 'Vacation',
            'slackStatusEmoji' => ':palm_tree:',
        ]),
        'endDate' => new DateTimeImmutable('2026-03-04'),
    ]);

    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([]);
    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([$leave]);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($leave->user, 'Vacation until Mar 4', ':palm_tree:', $leave->endDate)
        ->andReturn(true);
    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->once();

    $this->handler->handle();
});

it('continues clear batch when one clear throws', function (): void {
    $lr1 = LeaveRequestDTOFixture::create();
    $lr2 = LeaveRequestDTOFixture::create();
    $lr3 = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([$lr1, $lr2, $lr3]);
    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([]);

    $this->clearHandler->expects('handle')->with($lr1->user)->andReturn(true);
    $this->clearHandler->expects('handle')->with($lr2->user)->andThrow(new RuntimeException('boom'));
    $this->clearHandler->expects('handle')->with($lr3->user)->andReturn(true);

    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->twice();
    $this->setHandler->shouldNotReceive('handle');
    $this->logger->expects('error')->once();

    $this->handler->handle();
});

it('continues batch when one set throws', function (): void {
    $lr1 = LeaveRequestDTOFixture::create();
    $lr2 = LeaveRequestDTOFixture::create();
    $lr3 = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([$lr1, $lr2, $lr3]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([]);

    $this->setHandler
        ->expects('handle')
        ->times(3)
        ->andReturnUsing(function () {
            static $call = 0;
            ++$call;
            if (2 === $call) {
                throw new RuntimeException('boom');
            }

            return true;
        });

    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->twice();

    $this->logger->expects('error')->once();

    $this->handler->handle();
});

it('handles both sets and clears in one pass', function (): void {
    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([LeaveRequestDTOFixture::create()]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([LeaveRequestDTOFixture::create(), LeaveRequestDTOFixture::create()]);

    $this->setHandler->expects('handle')->once()->andReturn(true);
    $this->clearHandler->expects('handle')->twice()->andReturn(true);

    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->times(3);

    $this->handler->handle();
});

it('sets a holiday status with the country flag and stores the row', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Christmas Day',
        'countryCode' => 'DE',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $candidate = new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user);
    $isToday = Mockery::on(fn (DateTimeImmutable $date): bool => '2026-12-25' === $date->format('Y-m-d'));

    $this->holidayStatusRepository->expects('findCandidatesForDay')->once()->andReturn([$candidate]);
    $this->holidayStatusRepository->expects('findAllTracked')->once()->andReturn([]);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($user, 'Christmas Day', ':flag-de:', $isToday, ':crossed_flags:')
        ->andReturn(true);
    $this->holidayStatusRepository
        ->expects('upsert')
        ->once()
        ->with($user->id, 'holiday-1', $isToday, 'Christmas Day');

    $this->handler->handle();
});

it('passes no fallback when the flag is already the generic emoji', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Holiday',
        'countryCode' => 'X1',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $candidate = new SlackHolidayStatusCandidateDTO('X1', [$holiday], $user);
    $isToday = Mockery::on(fn (DateTimeImmutable $date): bool => '2026-12-25' === $date->format('Y-m-d'));

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([$candidate]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([]);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($user, 'Holiday', ':crossed_flags:', $isToday, null)
        ->andReturn(true);
    $this->holidayStatusRepository->expects('upsert')->once();

    $this->handler->handle();
});

it('stores nothing when the status is not set', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Holiday',
        'countryCode' => 'DE',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $candidate = new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user);

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([$candidate]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([]);
    $this->setHandler->expects('handle')->once()->andReturn(false);
    $this->holidayStatusRepository->shouldNotReceive('upsert');
    $this->logger->shouldNotReceive('warning');

    $this->handler->handle();
});

it('skips users for whom today is not a working day', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1', 'workingDays' => [1, 2, 3, 4]]);
    $holiday = PublicHolidayDTOFixture::create(['date' => new DateTimeImmutable('2026-12-25')]);

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([]);
    $this->setHandler->shouldNotReceive('handle');

    $this->handler->handle();
});

it('combines several holidays into one status', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $date = new DateTimeImmutable('2026-12-25');
    $first = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Ευαγγελισμός της Θεοτόκου',
        'date' => $date,
    ]);
    $second = PublicHolidayDTOFixture::create([
        'id' => 'holiday-2',
        'description' => 'Εικοστή Πέμπτη Μαρτίου',
        'date' => $date,
    ]);

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('GR', [$first, $second], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([]);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($user, 'Εικοστή Πέμπτη Μαρτίου, Ευαγγελισμός της Θεοτόκου', ':flag-gr:', Mockery::type(DateTimeImmutable::class), ':crossed_flags:')
        ->andReturn(true);
    $this->holidayStatusRepository->expects('upsert')->once()->with($user->id, 'holiday-2', Mockery::type(DateTimeImmutable::class), 'Εικοστή Πέμπτη Μαρτίου, Ευαγγελισμός της Θεοτόκου');

    $this->handler->handle();
});

it("does not call slack again when today's status text is unchanged", function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Holiday',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $tracked = new SlackHolidayStatusDTO(
        id: 'status-1',
        statusText: 'Holiday',
        isUserOnSyncedLeave: false,
        user: $user,
        date: new DateTimeImmutable('2026-12-25'),
        holidayId: 'holiday-1',
    );

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([$tracked]);
    $this->setHandler->shouldNotReceive('handle');
    $this->holidayStatusRepository->shouldNotReceive('upsert');

    $this->handler->handle();
});

it('updates only the holiday reference when the text is unchanged', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Holiday',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $tracked = new SlackHolidayStatusDTO(
        id: 'status-1',
        statusText: 'Holiday',
        isUserOnSyncedLeave: false,
        user: $user,
        date: new DateTimeImmutable('2026-12-25'),
        holidayId: null,
    );
    $isToday = Mockery::on(fn (DateTimeImmutable $date): bool => '2026-12-25' === $date->format('Y-m-d'));

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([$tracked]);
    $this->setHandler->shouldNotReceive('handle');
    $this->holidayStatusRepository->expects('upsert')->once()->with($user->id, 'holiday-1', $isToday, 'Holiday');

    $this->handler->handle();
});

it('re-sets the status when the holiday text changed', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Renamed Holiday',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $tracked = new SlackHolidayStatusDTO(
        id: 'status-1',
        statusText: 'Old Holiday',
        isUserOnSyncedLeave: false,
        user: $user,
        date: new DateTimeImmutable('2026-12-25'),
        holidayId: 'holiday-1',
    );

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([$tracked]);
    $this->setHandler->expects('handle')->once()->andReturn(true);
    $this->holidayStatusRepository->expects('upsert')->once();

    $this->handler->handle();
});

it('deletes rows of past days without calling slack', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $tracked = new SlackHolidayStatusDTO(
        id: 'status-1',
        statusText: 'Old Holiday',
        isUserOnSyncedLeave: false,
        user: $user,
        date: new DateTimeImmutable('2026-12-24'),
        holidayId: 'holiday-1',
    );

    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([$tracked]);
    $this->holidayStatusRepository->expects('delete')->once()->with('status-1');
    $this->clearHandler->shouldNotReceive('handle');

    $this->handler->handle();
});

it('deletes rows of future days without calling slack', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $tracked = new SlackHolidayStatusDTO(
        id: 'status-1',
        statusText: 'Future Holiday',
        isUserOnSyncedLeave: false,
        user: $user,
        date: new DateTimeImmutable('2026-12-26'),
        holidayId: 'holiday-1',
    );

    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([$tracked]);
    $this->holidayStatusRepository->expects('delete')->once()->with('status-1');
    $this->clearHandler->shouldNotReceive('handle');

    $this->handler->handle();
});

it("keeps today's row when a holiday still applies", function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Holiday',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $tracked = new SlackHolidayStatusDTO(
        id: 'status-1',
        statusText: 'Holiday',
        isUserOnSyncedLeave: false,
        user: $user,
        date: new DateTimeImmutable('2026-12-25'),
        holidayId: 'holiday-1',
    );

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([$tracked]);
    $this->clearHandler->shouldNotReceive('handle');
    $this->holidayStatusRepository->shouldNotReceive('delete');

    $this->handler->handle();
});

it('deletes the row without calling slack when the user is on synced leave or sync is off', function (): void {
    $onLeave = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $syncOff = UserDTOFixture::create(['slackMemberId' => 'U2', 'slackStatusSyncEnabled' => false]);
    $noMember = UserDTOFixture::create(['slackMemberId' => null]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([
        new SlackHolidayStatusDTO('status-1', 'Holiday', true, $onLeave, new DateTimeImmutable('2026-12-25'), 'holiday-1'),
        new SlackHolidayStatusDTO('status-2', 'Holiday', false, $syncOff, new DateTimeImmutable('2026-12-25'), 'holiday-2'),
        new SlackHolidayStatusDTO('status-3', 'Holiday', false, $noMember, new DateTimeImmutable('2026-12-25'), 'holiday-3'),
    ]);
    $this->holidayStatusRepository->expects('delete')->once()->with('status-1');
    $this->holidayStatusRepository->expects('delete')->once()->with('status-2');
    $this->holidayStatusRepository->expects('delete')->once()->with('status-3');
    $this->clearHandler->shouldNotReceive('handle');

    $this->handler->handle();
});

it('clears the status and deletes the row when no holiday applies any more', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $failedUser = UserDTOFixture::create(['slackMemberId' => 'U2']);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([
        new SlackHolidayStatusDTO('status-1', 'Holiday', false, $user, new DateTimeImmutable('2026-12-25'), 'holiday-1'),
        new SlackHolidayStatusDTO('status-2', 'Holiday', false, $failedUser, new DateTimeImmutable('2026-12-25'), 'holiday-2'),
    ]);
    $this->clearHandler->expects('handle')->with($user)->andReturn(true);
    $this->clearHandler->expects('handle')->with($failedUser)->andReturn(false);
    $this->holidayStatusRepository->expects('delete')->once()->with('status-1');

    $this->handler->handle();
});

it('continues with other users and stores nothing when setting a holiday status fails', function (): void {
    $first = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $second = UserDTOFixture::create(['slackMemberId' => 'U2']);
    $holiday1 = PublicHolidayDTOFixture::create(['id' => 'holiday-1', 'description' => 'Holiday 1', 'date' => new DateTimeImmutable('2026-12-25')]);
    $holiday2 = PublicHolidayDTOFixture::create(['id' => 'holiday-2', 'description' => 'Holiday 2', 'date' => new DateTimeImmutable('2026-12-25')]);
    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday1], $first),
        new SlackHolidayStatusCandidateDTO('DE', [$holiday2], $second),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([]);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($first, 'Holiday 1', ':flag-de:', Mockery::type(DateTimeImmutable::class), ':crossed_flags:')
        ->andThrow(new RuntimeException('boom'));
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($second, 'Holiday 2', ':flag-de:', Mockery::type(DateTimeImmutable::class), ':crossed_flags:')
        ->andReturn(true);
    $this->holidayStatusRepository->expects('upsert')->once()->with($second->id, 'holiday-2', Mockery::type(DateTimeImmutable::class), 'Holiday 2');
    $this->logger->expects('error')->once()->with(
        '[SLACK][HOLIDAY-STATUS]: Failed to set slack holiday status',
        Mockery::on(fn (array $context): bool => $first->id === $context['user_id']
            && 'holiday-1' === $context['holiday_id']
            && 'boom' === $context['exception']),
    );

    $this->handler->handle();
});

it('logs and stops holiday sync when loading tracked rows fails', function (): void {
    $this->leaveRequestFacade->expects('findSyncedNeedingClear')->once()->andReturn([]);
    $this->leaveRequestFacade->expects('findApprovedActiveNotSynced')->once()->andReturn([]);
    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([]);
    $this->holidayStatusRepository->expects('findAllTracked')->andThrow(new RuntimeException('load failed'));
    $this->logger->expects('error')->once()->with(
        '[SLACK][HOLIDAY-STATUS]: Failed to load holiday statuses',
        Mockery::on(fn (array $context): bool => 'load failed' === $context['exception']),
    );

    $this->handler->handle();
});

it('logs and stops holiday sync when loading candidates fails', function (): void {
    $this->leaveRequestFacade->expects('findSyncedNeedingClear')->once()->andReturn([]);
    $this->leaveRequestFacade->expects('findApprovedActiveNotSynced')->once()->andReturn([]);
    $this->holidayStatusRepository->expects('findCandidatesForDay')->andThrow(new RuntimeException('candidate load failed'));
    $this->holidayStatusRepository->shouldNotReceive('findAllTracked');
    $this->logger->expects('error')->once()->with(
        '[SLACK][HOLIDAY-STATUS]: Failed to load holiday statuses',
        Mockery::on(fn (array $context): bool => 'candidate load failed' === $context['exception']),
    );

    $this->handler->handle();
});

it('re-sets the holiday status when a leave status was cleared in the same run', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $leave = LeaveRequestDTOFixture::create(['user' => $user]);
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Holiday',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $tracked = new SlackHolidayStatusDTO(
        id: 'status-1',
        statusText: 'Holiday',
        isUserOnSyncedLeave: false,
        user: $user,
        date: new DateTimeImmutable('2026-12-25'),
        holidayId: 'holiday-1',
    );

    $this->leaveRequestFacade->expects('findSyncedNeedingClear')->once()->andReturn([$leave]);
    $this->clearHandler->expects('handle')->once()->with($user)->andReturn(true);
    $this->leaveRequestFacade->expects('markExternalStatusSynced')->once()->with($leave->id->toString(), false);
    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([$tracked]);
    $this->setHandler->expects('handle')->once()->andReturn(true);
    $this->holidayStatusRepository->expects('upsert')->once()->with($user->id, 'holiday-1', Mockery::type(DateTimeImmutable::class), 'Holiday');

    $this->handler->handle();
});

it('does not re-set the holiday status when the leave clear failed', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $leave = LeaveRequestDTOFixture::create(['user' => $user]);
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Holiday',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $tracked = new SlackHolidayStatusDTO(
        id: 'status-1',
        statusText: 'Holiday',
        isUserOnSyncedLeave: false,
        user: $user,
        date: new DateTimeImmutable('2026-12-25'),
        holidayId: 'holiday-1',
    );

    $this->leaveRequestFacade->expects('findSyncedNeedingClear')->once()->andReturn([$leave]);
    $this->clearHandler->expects('handle')->once()->with($user)->andReturn(false);
    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([$tracked]);
    $this->setHandler->shouldNotReceive('handle');

    $this->handler->handle();
});

it('runs holiday steps after leave steps', function (): void {
    $this->leaveRequestFacade->expects('findSyncedNeedingClear')->once()->andReturn([])->globally()->ordered();
    $this->leaveRequestFacade->expects('findApprovedActiveNotSynced')->once()->andReturn([])->globally()->ordered();
    $this->holidayStatusRepository->expects('findCandidatesForDay')->once()->andReturn([])->globally()->ordered();
    $this->holidayStatusRepository->expects('findAllTracked')->once()->andReturn([])->globally()->ordered();

    $this->handler->handle();
});

it('sets only the renamed holiday, clears the stale row and leaves the unchanged one alone', function (): void {
    $unchangedUser = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $renamedUser = UserDTOFixture::create(['slackMemberId' => 'U2']);
    $staleUser = UserDTOFixture::create(['slackMemberId' => 'U3']);
    $unchangedHoliday = PublicHolidayDTOFixture::create(['id' => 'holiday-1', 'description' => 'Holiday', 'date' => new DateTimeImmutable('2026-12-25')]);
    $renamedHoliday = PublicHolidayDTOFixture::create(['id' => 'holiday-2', 'description' => 'Renamed Holiday', 'date' => new DateTimeImmutable('2026-12-25')]);
    $isToday = Mockery::on(fn (DateTimeImmutable $date): bool => '2026-12-25' === $date->format('Y-m-d'));

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$unchangedHoliday], $unchangedUser),
        new SlackHolidayStatusCandidateDTO('DE', [$renamedHoliday], $renamedUser),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([
        new SlackHolidayStatusDTO('status-1', 'Holiday', false, $unchangedUser, new DateTimeImmutable('2026-12-25'), 'holiday-1'),
        new SlackHolidayStatusDTO('status-2', 'Old Holiday', false, $renamedUser, new DateTimeImmutable('2026-12-25'), 'holiday-2'),
        new SlackHolidayStatusDTO('status-3', 'Stale Holiday', false, $staleUser, new DateTimeImmutable('2026-12-25'), 'holiday-3'),
    ]);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($renamedUser, 'Renamed Holiday', ':flag-de:', $isToday, ':crossed_flags:')
        ->andReturn(true);
    $this->clearHandler->expects('handle')->once()->with($staleUser)->andReturn(true);
    $this->holidayStatusRepository->expects('upsert')->once()->with($renamedUser->id, 'holiday-2', $isToday, 'Renamed Holiday');
    $this->holidayStatusRepository->expects('delete')->once()->with('status-3');

    $this->handler->handle();
});

it("deletes yesterday's row without a slack call and sets today's status for the same user", function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $holiday = PublicHolidayDTOFixture::create(['id' => 'holiday-1', 'description' => 'Holiday', 'date' => new DateTimeImmutable('2026-12-25')]);
    $isToday = Mockery::on(fn (DateTimeImmutable $date): bool => '2026-12-25' === $date->format('Y-m-d'));

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([
        new SlackHolidayStatusDTO('status-yesterday', 'Old Holiday', false, $user, new DateTimeImmutable('2026-12-24'), 'holiday-0'),
    ]);
    $this->holidayStatusRepository->expects('delete')->once()->with('status-yesterday');
    $this->clearHandler->shouldNotReceive('handle');
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($user, 'Holiday', ':flag-de:', $isToday, ':crossed_flags:')
        ->andReturn(true);
    $this->holidayStatusRepository->expects('upsert')->once()->with($user->id, 'holiday-1', $isToday, 'Holiday');

    $this->handler->handle();
});

it('logs a failed holiday clear and keeps processing the other rows and the set step', function (): void {
    $failingUser = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $clearingUser = UserDTOFixture::create(['slackMemberId' => 'U2']);
    $candidateUser = UserDTOFixture::create(['slackMemberId' => 'U3']);
    $holiday = PublicHolidayDTOFixture::create(['id' => 'holiday-3', 'description' => 'Holiday 3', 'date' => new DateTimeImmutable('2026-12-25')]);
    $isToday = Mockery::on(fn (DateTimeImmutable $date): bool => '2026-12-25' === $date->format('Y-m-d'));

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $candidateUser),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([
        new SlackHolidayStatusDTO('status-1', 'Holiday 1', false, $failingUser, new DateTimeImmutable('2026-12-25'), 'holiday-1'),
        new SlackHolidayStatusDTO('status-2', 'Holiday 2', false, $clearingUser, new DateTimeImmutable('2026-12-25'), 'holiday-2'),
    ]);
    $this->clearHandler->expects('handle')->once()->with($failingUser)->andThrow(new RuntimeException('clear boom'));
    $this->clearHandler->expects('handle')->once()->with($clearingUser)->andReturn(true);
    $this->holidayStatusRepository->expects('delete')->once()->with('status-2');
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($candidateUser, 'Holiday 3', ':flag-de:', $isToday, ':crossed_flags:')
        ->andReturn(true);
    $this->holidayStatusRepository->expects('upsert')->once()->with($candidateUser->id, 'holiday-3', $isToday, 'Holiday 3');
    $this->logger->expects('error')->once()->with(
        '[SLACK][HOLIDAY-STATUS]: Failed to clear slack holiday status',
        Mockery::on(fn (array $context): bool => $failingUser->id === $context['user_id']
            && 'holiday-1' === $context['holiday_id']
            && 'clear boom' === $context['exception']),
    );

    $this->handler->handle();
});

it('logs a failed upsert after a successful set and still processes the next user', function (): void {
    $first = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $second = UserDTOFixture::create(['slackMemberId' => 'U2']);
    $holiday1 = PublicHolidayDTOFixture::create(['id' => 'holiday-1', 'description' => 'Holiday 1', 'date' => new DateTimeImmutable('2026-12-25')]);
    $holiday2 = PublicHolidayDTOFixture::create(['id' => 'holiday-2', 'description' => 'Holiday 2', 'date' => new DateTimeImmutable('2026-12-25')]);
    $isToday = Mockery::on(fn (DateTimeImmutable $date): bool => '2026-12-25' === $date->format('Y-m-d'));

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday1], $first),
        new SlackHolidayStatusCandidateDTO('DE', [$holiday2], $second),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([]);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($first, 'Holiday 1', ':flag-de:', $isToday, ':crossed_flags:')
        ->andReturn(true);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($second, 'Holiday 2', ':flag-de:', $isToday, ':crossed_flags:')
        ->andReturn(true);
    $this->holidayStatusRepository
        ->expects('upsert')
        ->once()
        ->with($first->id, 'holiday-1', $isToday, 'Holiday 1')
        ->andThrow(new RuntimeException('upsert boom'));
    $this->holidayStatusRepository->expects('upsert')->once()->with($second->id, 'holiday-2', $isToday, 'Holiday 2');
    $this->logger->expects('error')->once()->with(
        '[SLACK][HOLIDAY-STATUS]: Failed to set slack holiday status',
        Mockery::on(fn (array $context): bool => $first->id === $context['user_id']
            && 'holiday-1' === $context['holiday_id']
            && 'upsert boom' === $context['exception']),
    );

    $this->handler->handle();
});

it('logs a failed holiday row delete after a clear and keeps deleting the other rows', function (): void {
    $cleared = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $pastUser = UserDTOFixture::create(['slackMemberId' => 'U2']);

    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([
        new SlackHolidayStatusDTO('status-1', 'Holiday 1', false, $cleared, new DateTimeImmutable('2026-12-25'), 'holiday-1'),
        new SlackHolidayStatusDTO('status-2', 'Old Holiday', false, $pastUser, new DateTimeImmutable('2026-12-24'), 'holiday-2'),
    ]);
    $this->clearHandler->expects('handle')->once()->with($cleared)->andReturn(true);
    $this->holidayStatusRepository->expects('delete')->once()->with('status-1')->andThrow(new RuntimeException('delete boom'));
    $this->holidayStatusRepository->expects('delete')->once()->with('status-2');
    $this->logger->expects('error')->once()->with(
        '[SLACK][HOLIDAY-STATUS]: Failed to clear slack holiday status',
        Mockery::on(fn (array $context): bool => $cleared->id === $context['user_id']
            && 'holiday-1' === $context['holiday_id']
            && 'delete boom' === $context['exception']),
    );

    $this->handler->handle();
});

it('matches today against the iso weekday of the working days', function (array $workingDays, bool $expectSet): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1', 'workingDays' => $workingDays]);
    $holiday = PublicHolidayDTOFixture::create(['id' => 'holiday-1', 'description' => 'Holiday', 'date' => new DateTimeImmutable('2026-12-25')]);

    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([]);
    if ($expectSet) {
        $this->setHandler->expects('handle')->once()->andReturn(true);
        $this->holidayStatusRepository->expects('upsert')->once();
    } else {
        $this->setHandler->shouldNotReceive('handle');
        $this->holidayStatusRepository->shouldNotReceive('upsert');
    }

    $this->handler->handle();
})->with([
    'friday as iso int' => [[5], true],
    'friday as numeric string' => [['5'], true],
    'weekend only' => [[6, 7], false],
]);

it('does not touch holiday rows for a user whose leave was cleared and who has no holiday today', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $leave = LeaveRequestDTOFixture::create(['user' => $user]);

    $this->leaveRequestFacade->expects('findSyncedNeedingClear')->once()->andReturn([$leave]);
    $this->clearHandler->expects('handle')->once()->with($user)->andReturn(true);
    $this->leaveRequestFacade->expects('markExternalStatusSynced')->once()->with($leave->id->toString(), false);
    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([]);
    $this->setHandler->shouldNotReceive('handle');
    $this->holidayStatusRepository->shouldNotReceive('upsert');
    $this->holidayStatusRepository->shouldNotReceive('delete');

    $this->handler->handle();
});

it('sets the holiday status once for a user whose leave was cleared and who has no tracked row', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U1']);
    $leave = LeaveRequestDTOFixture::create(['user' => $user]);
    $holiday = PublicHolidayDTOFixture::create(['id' => 'holiday-1', 'description' => 'Holiday', 'date' => new DateTimeImmutable('2026-12-25')]);
    $isToday = Mockery::on(fn (DateTimeImmutable $date): bool => '2026-12-25' === $date->format('Y-m-d'));

    $this->leaveRequestFacade->expects('findSyncedNeedingClear')->once()->andReturn([$leave]);
    $this->clearHandler->expects('handle')->once()->with($user)->andReturn(true);
    $this->leaveRequestFacade->expects('markExternalStatusSynced')->once()->with($leave->id->toString(), false);
    $this->holidayStatusRepository->expects('findCandidatesForDay')->andReturn([
        new SlackHolidayStatusCandidateDTO('DE', [$holiday], $user),
    ]);
    $this->holidayStatusRepository->expects('findAllTracked')->andReturn([]);
    $this->setHandler
        ->expects('handle')
        ->once()
        ->with($user, 'Holiday', ':flag-de:', $isToday, ':crossed_flags:')
        ->andReturn(true);
    $this->holidayStatusRepository->expects('upsert')->once()->with($user->id, 'holiday-1', $isToday, 'Holiday');

    $this->handler->handle();
});
