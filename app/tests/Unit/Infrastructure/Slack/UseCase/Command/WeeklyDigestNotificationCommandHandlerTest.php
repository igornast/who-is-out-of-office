<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Service\UsersEventsProvider;
use App\Infrastructure\Slack\UseCase\Command\WeeklyDigestNotificationCommandHandler;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;
use App\Tests\_fixtures\Shared\DTO\Holiday\UserPublicHolidaysDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

beforeEach(function (): void {
    $this->dailyDigestChannelId = 'C1234567890';
    $this->chatter = mock(ChatterInterface::class);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->usersEventsProvider = mock(UsersEventsProvider::class);

    $this->handler = new WeeklyDigestNotificationCommandHandler(
        dailyDigestChannelId: $this->dailyDigestChannelId,
        chatter: $this->chatter,
        userFacade: $this->userFacade,
        usersEventsProvider: $this->usersEventsProvider
    );
});

it('sends digest with leave requests and birthdays', function () {
    $user1 = UserDTOFixture::create([
        'id' => 'user-1',
        'firstName' => 'John',
        'lastName' => 'Doe',
    ]);

    $user2 = UserDTOFixture::create([
        'id' => 'user-2',
        'firstName' => 'Jane',
        'lastName' => 'Smith',
        'birthDate' => new DateTimeImmutable('1990-01-15'),
    ]);

    $leaveRequest = LeaveRequestDTOFixture::create([
        'user' => $user1,
        'startDate' => new DateTimeImmutable('2025-01-13'),
        'endDate' => new DateTimeImmutable('2025-01-17'),
        'approvedBy' => null,
    ]);
    $leaveRequest->leaveType->icon = '🏖️';
    $leaveRequest->leaveType->name = 'Vacation';

    $mergedEvents = [
        'user-1' => [$leaveRequest],
    ];

    $birthdayUsers = [$user2];

    $this->usersEventsProvider
        ->expects('provideMergedAbsencesPerUser')
        ->once()
        ->andReturn($mergedEvents);

    $this->userFacade
        ->expects('getUsersWithBirthdaysForDates')
        ->once()
        ->andReturn($birthdayUsers);

    $this->chatter
        ->expects('send')
        ->once()
        ->withArgs(function (ChatMessage $message) use ($user1, $user2) {
            $options = $message->getOptions();

            expect($options)->toBeInstanceOf(SlackOptions::class);

            $blocks = $options->toArray()['blocks'];

            expect($blocks[0]['type'])->toBe('header')
                ->and($blocks[0]['text']['text'])->toBe('✨ Weekly digest')
                ->and($blocks[1]['type'])->toBe('context')
                ->and($blocks[2]['type'])->toBe('divider')
                ->and($blocks[3]['type'])->toBe('section')
                ->and($blocks[3]['text']['text'])->toContain('📆 | *Who is out this week? *')
                ->and($blocks[4]['type'])->toBe('section')
                ->and($blocks[4]['text']['text'])->toContain('*John Doe*')
                ->and($blocks[4]['text']['text'])->toContain('🏖️ Vacation')
                ->and($blocks[4]['text']['text'])->toContain('January 13 - January 17')
                ->and($blocks[5]['type'])->toBe('divider')
                ->and($blocks[6]['type'])->toBe('section')
                ->and($blocks[6]['text']['text'])->toContain('🍰 | *Birthdays*')
                ->and($blocks[7]['type'])->toBe('section')
                ->and($blocks[7]['text']['text'])->toContain('*Jane Smith*')
                ->and($blocks[7]['text']['text'])->toContain('January 15')
                ->and($message->getSubject())->toBe('Absences Weekly Digest')
                ->and($options->toArray()['channel'])->toBe($this->dailyDigestChannelId);

            return true;
        });

    $this->handler->handle();
});

it('sends digest with only leave requests when no birthdays', function () {
    $user = UserDTOFixture::create([
        'id' => 'user-1',
        'firstName' => 'Alice',
        'lastName' => 'Johnson',
    ]);

    $leaveRequest = LeaveRequestDTOFixture::create([
        'user' => $user,
        'startDate' => new DateTimeImmutable('2025-02-10'),
        'endDate' => new DateTimeImmutable('2025-02-14'),
        'approvedBy' => null,
    ]);
    $leaveRequest->leaveType->icon = '🤒';
    $leaveRequest->leaveType->name = 'Sick Leave';

    $mergedEvents = [
        'user-1' => [$leaveRequest],
    ];

    $this->usersEventsProvider
        ->expects('provideMergedAbsencesPerUser')
        ->once()
        ->andReturn($mergedEvents);

    $this->userFacade
        ->expects('getUsersWithBirthdaysForDates')
        ->once()
        ->andReturn([]);

    $this->chatter
        ->expects('send')
        ->once()
        ->withArgs(function (ChatMessage $message) {
            $options = $message->getOptions();
            $blocks = $options->toArray()['blocks'];

            expect($blocks[0]['type'])->toBe('header')
                ->and($blocks[2]['type'])->toBe('divider')
                ->and($blocks[3]['text']['text'])->toContain('📆 | *Who is out this week? *')
                ->and($blocks[4]['text']['text'])->toContain('*Alice Johnson*')
                ->and($blocks[4]['text']['text'])->toContain('🤒 Sick Leave')
                ->and(count($blocks))->toBe(5);

            return true;
        });

    $this->handler->handle();
});

it('sends digest with only birthdays when no absences', function () {
    $user = UserDTOFixture::create([
        'id' => 'user-1',
        'firstName' => 'Bob',
        'lastName' => 'Wilson',
        'birthDate' => new DateTimeImmutable('1985-03-20'),
    ]);

    $this->usersEventsProvider
        ->expects('provideMergedAbsencesPerUser')
        ->once()
        ->andReturn([]);

    $this->userFacade
        ->expects('getUsersWithBirthdaysForDates')
        ->once()
        ->andReturn([$user]);

    $this->chatter
        ->expects('send')
        ->once()
        ->withArgs(function (ChatMessage $message) {
            $options = $message->getOptions();
            $blocks = $options->toArray()['blocks'];

            expect($blocks[0]['type'])->toBe('header')
                ->and($blocks[2]['type'])->toBe('divider')
                ->and($blocks[3]['text']['text'])->toContain('🍰 | *Birthdays*')
                ->and($blocks[4]['text']['text'])->toContain('*Bob Wilson*')
                ->and($blocks[4]['text']['text'])->toContain('March 20')
                ->and(count($blocks))->toBe(5);

            return true;
        });

    $this->handler->handle();
});

it('sends empty digest when no absences and no birthdays', function () {
    $this->usersEventsProvider
        ->expects('provideMergedAbsencesPerUser')
        ->once()
        ->andReturn([]);

    $this->userFacade
        ->expects('getUsersWithBirthdaysForDates')
        ->once()
        ->andReturn([]);

    $this->chatter
        ->expects('send')
        ->once()
        ->withArgs(function (ChatMessage $message) {
            $options = $message->getOptions();
            $blocks = $options->toArray()['blocks'];

            expect($blocks[0]['type'])->toBe('header')
                ->and($blocks[0]['text']['text'])->toBe('✨ Weekly digest')
                ->and($blocks[2]['type'])->toBe('context')
                ->and($blocks[2]['elements']['text']['text'])->toContain('🎉 *Good news!*')
                ->and($blocks[2]['elements']['text']['text'])->toContain('No one is out this week')
                ->and(count($blocks))->toBe(3);

            return true;
        });

    $this->handler->handle();
});

it('sends digest with public holidays', function () {
    $user = UserDTOFixture::create([
        'id' => 'user-1',
        'firstName' => 'Carlos',
        'lastName' => 'Rodriguez',
    ]);

    $holiday1 = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'Independence Day',
        'countryCode' => 'US',
        'date' => new DateTimeImmutable('2025-07-04'),
    ]);

    $userPublicHolidays = UserPublicHolidaysDTOFixture::create([
        'user' => $user,
        'holidays' => [$holiday1],
    ]);

    $mergedEvents = [
        'user-1' => [$userPublicHolidays],
    ];

    $this->usersEventsProvider
        ->expects('provideMergedAbsencesPerUser')
        ->once()
        ->andReturn($mergedEvents);

    $this->userFacade
        ->expects('getUsersWithBirthdaysForDates')
        ->once()
        ->andReturn([]);

    $this->chatter
        ->expects('send')
        ->once()
        ->withArgs(function (ChatMessage $message) {
            $options = $message->getOptions();
            $blocks = $options->toArray()['blocks'];

            expect($blocks[4]['text']['text'])->toContain('*Carlos Rodriguez*')
                ->and($blocks[4]['text']['text'])->toContain('Public holiday:')
                ->and($blocks[4]['text']['text'])->toContain('July 04')
                ->and($blocks[4]['text']['text'])->toContain('Independence Day');

            return true;
        });

    $this->handler->handle();
});

it('sends digest with mixed leave requests and public holidays for same user', function () {
    $user = UserDTOFixture::create([
        'id' => 'user-1',
        'firstName' => 'Maria',
        'lastName' => 'Garcia',
    ]);

    $leaveRequest = LeaveRequestDTOFixture::create([
        'user' => $user,
        'startDate' => new DateTimeImmutable('2025-12-20'),
        'endDate' => new DateTimeImmutable('2025-12-24'),
        'approvedBy' => null,
    ]);
    $leaveRequest->leaveType->icon = '🎄';
    $leaveRequest->leaveType->name = 'Holiday Leave';

    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-2',
        'description' => 'Christmas Day',
        'countryCode' => 'GB',
        'date' => new DateTimeImmutable('2025-12-25'),
    ]);

    $userPublicHolidays = UserPublicHolidaysDTOFixture::create([
        'user' => $user,
        'holidays' => [$holiday],
    ]);

    $mergedEvents = [
        'user-1' => [$leaveRequest, $userPublicHolidays],
    ];

    $this->usersEventsProvider
        ->expects('provideMergedAbsencesPerUser')
        ->once()
        ->andReturn($mergedEvents);

    $this->userFacade
        ->expects('getUsersWithBirthdaysForDates')
        ->once()
        ->andReturn([]);

    $this->chatter
        ->expects('send')
        ->once()
        ->withArgs(function (ChatMessage $message) {
            $options = $message->getOptions();
            $blocks = $options->toArray()['blocks'];

            expect($blocks[4]['text']['text'])->toContain('*Maria Garcia*')
                ->and($blocks[4]['text']['text'])->toContain('🎄 Holiday Leave')
                ->and($blocks[4]['text']['text'])->toContain('December 20 - December 24')
                ->and($blocks[4]['text']['text'])->toContain('Public holiday:')
                ->and($blocks[4]['text']['text'])->toContain('December 25')
                ->and($blocks[4]['text']['text'])->toContain('Christmas Day');

            return true;
        });

    $this->handler->handle();
});

it('sends digest with multiple users having different events', function () {
    $user1 = UserDTOFixture::create([
        'id' => 'user-1',
        'firstName' => 'Tom',
        'lastName' => 'Brown',
    ]);

    $user2 = UserDTOFixture::create([
        'id' => 'user-2',
        'firstName' => 'Lisa',
        'lastName' => 'White',
        'birthDate' => new DateTimeImmutable('1992-06-10'),
    ]);

    $leaveRequest1 = LeaveRequestDTOFixture::create([
        'user' => $user1,
        'startDate' => new DateTimeImmutable('2025-06-05'),
        'endDate' => new DateTimeImmutable('2025-06-08'),
        'approvedBy' => null,
    ]);
    $leaveRequest1->leaveType->icon = '🌴';
    $leaveRequest1->leaveType->name = 'Annual Leave';

    $leaveRequest2 = LeaveRequestDTOFixture::create([
        'user' => $user2,
        'startDate' => new DateTimeImmutable('2025-06-09'),
        'endDate' => new DateTimeImmutable('2025-06-11'),
        'approvedBy' => null,
    ]);
    $leaveRequest2->leaveType->icon = '🏠';
    $leaveRequest2->leaveType->name = 'Work From Home';

    $mergedEvents = [
        'user-1' => [$leaveRequest1],
        'user-2' => [$leaveRequest2],
    ];

    $this->usersEventsProvider
        ->expects('provideMergedAbsencesPerUser')
        ->once()
        ->andReturn($mergedEvents);

    $this->userFacade
        ->expects('getUsersWithBirthdaysForDates')
        ->once()
        ->andReturn([$user2]);

    $this->chatter
        ->expects('send')
        ->once()
        ->withArgs(function (ChatMessage $message) {
            $options = $message->getOptions();
            $blocks = $options->toArray()['blocks'];

            expect($blocks[4]['text']['text'])->toContain('*Tom Brown*')
                ->and($blocks[4]['text']['text'])->toContain('🌴 Annual Leave')
                ->and($blocks[4]['text']['text'])->toContain('*Lisa White*')
                ->and($blocks[4]['text']['text'])->toContain('🏠 Work From Home')
                ->and($blocks[7]['text']['text'])->toContain('*Lisa White*')
                ->and($blocks[7]['text']['text'])->toContain('June 10');

            return true;
        });

    $this->handler->handle();
});
