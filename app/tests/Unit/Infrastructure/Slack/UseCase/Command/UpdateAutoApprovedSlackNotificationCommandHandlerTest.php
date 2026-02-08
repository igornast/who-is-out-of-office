<?php

declare(strict_types=1);

use App\Infrastructure\Slack\DTO\LeaveRequestSlackNotificationDTO;
use App\Infrastructure\Slack\Repository\LeaveRequestSlackNotificationRepositoryInterface;
use App\Infrastructure\Slack\UseCase\Command\UpdateAutoApprovedSlackNotificationCommandHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use Symfony\Component\Notifier\Bridge\Slack\UpdateMessageSlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

beforeEach(function (): void {
    $this->slackNotificationRepository = mock(LeaveRequestSlackNotificationRepositoryInterface::class);
    $this->chatter = mock(ChatterInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);

    $this->handler = new UpdateAutoApprovedSlackNotificationCommandHandler(
        slackNotificationRepository: $this->slackNotificationRepository,
        chatter: $this->chatter,
        urlGenerator: $this->urlGenerator,
    );
});

it('updates the slack message when notification record exists', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create();

    $notificationDTO = new LeaveRequestSlackNotificationDTO(
        channelId: 'C1234567890',
        messageTs: '1234567890.123456',
    );

    $this->slackNotificationRepository
        ->expects('findByLeaveRequestId')
        ->once()
        ->with($leaveRequestDTO->id->toString())
        ->andReturn($notificationDTO);

    $expectedUrl = 'https://example.com/app/dashboard/leave-request/detail/'.$leaveRequestDTO->id->toString();

    $this->urlGenerator
        ->expects('generate')
        ->once()
        ->with(
            'app_dashboard_app_leave_request_detail',
            ['entityId' => $leaveRequestDTO->id->toString()],
            UrlGeneratorInterface::ABSOLUTE_URL
        )
        ->andReturn($expectedUrl);

    $this->chatter
        ->expects('send')
        ->once()
        ->withArgs(function (ChatMessage $message) use ($leaveRequestDTO, $expectedUrl) {
            $options = $message->getOptions();

            expect($options)->toBeInstanceOf(UpdateMessageSlackOptions::class)
                ->and($options->toArray()['channel'])->toBe('C1234567890')
                ->and($options->toArray()['ts'])->toBe('1234567890.123456')
                ->and($message->getSubject())->toBe(sprintf(
                    'Already auto approved - %s %s (%s - %s). <%s|Details>',
                    $leaveRequestDTO->user->firstName,
                    $leaveRequestDTO->user->lastName,
                    $leaveRequestDTO->startDate->format('M d, Y'),
                    $leaveRequestDTO->endDate->format('M d, Y'),
                    $expectedUrl,
                ));

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('does not update when no notification record exists', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create();

    $this->slackNotificationRepository
        ->expects('findByLeaveRequestId')
        ->once()
        ->with($leaveRequestDTO->id->toString())
        ->andReturn(null);

    $this->chatter
        ->expects('send')
        ->never();

    $this->handler->handle($leaveRequestDTO);
});
