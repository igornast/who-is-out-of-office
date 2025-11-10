<?php

declare(strict_types=1);

use App\Infrastructure\Slack\UseCase\Command\NotifyNewLeaveRequestCommandHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

beforeEach(function (): void {
    $this->requestsApproveChannelId = 'C1234567890';
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->chatter = mock(ChatterInterface::class);

    $this->handler = new NotifyNewLeaveRequestCommandHandler(
        requestsApproveChannelId: $this->requestsApproveChannelId,
        urlGenerator: $this->urlGenerator,
        chatter: $this->chatter
    );
});

it('sends slack notification with correct message structure', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('123e4567-e89b-12d3-a456-426614174000'),
        'status' => LeaveRequestStatusEnum::Pending,
        'approvedBy' => null,
    ]);

    $expectedUrl = 'https://example.com/app/dashboard/users/detail/'.$leaveRequestDTO->user->id;

    $this->urlGenerator
        ->expects('generate')
        ->once()
        ->with(
            'app_dashboard_app_users_detail',
            ['entityId' => $leaveRequestDTO->user->id],
            UrlGeneratorInterface::ABSOLUTE_URL
        )
        ->andReturn($expectedUrl);

    $this->chatter
        ->expects('send')
        ->once()
        ->withArgs(function (ChatMessage $message) use ($leaveRequestDTO, $expectedUrl) {
            $options = $message->getOptions();

            expect($options)->toBeInstanceOf(SlackOptions::class);

            $blocks = $options->toArray()['blocks'];

            expect($blocks[0]['type'])->toBe('header')
                ->and($blocks[0]['text']['type'])->toBe('plain_text')
                ->and($blocks[0]['text']['text'])->toBe(sprintf(
                    'New absence request from %s %s',
                    $leaveRequestDTO->user->firstName,
                    $leaveRequestDTO->user->lastName
                ))
                ->and($blocks[0]['text']['emoji'])->toBeTrue()
                ->and($blocks[1]['type'])->toBe('divider')
                ->and($blocks[2]['type'])->toBe('section')
                ->and($blocks[2]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[2]['fields'][0]['text'])->toBe(sprintf('*Type:* %s', $leaveRequestDTO->leaveType->name))
                ->and($blocks[2]['fields'][1]['type'])->toBe('mrkdwn')
                ->and($blocks[2]['fields'][1]['text'])->toBe(sprintf(
                    '*Created by:* <%s|%s %s>',
                    $expectedUrl,
                    $leaveRequestDTO->user->firstName,
                    $leaveRequestDTO->user->lastName
                ))
                ->and($blocks[3]['type'])->toBe('section')
                ->and($blocks[3]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[3]['fields'][0]['text'])->toBe(sprintf(
                    '*When:* %s - %s',
                    $leaveRequestDTO->startDate->format('M d, Y'),
                    $leaveRequestDTO->endDate->format('M d, Y')
                ))
                ->and($blocks[4]['type'])->toBe('actions')
                ->and($blocks[4]['elements'][0]['type'])->toBe('button')
                ->and($blocks[4]['elements'][0]['text']['text'])->toBe(' ✅ Approve ')
                ->and($blocks[4]['elements'][0]['style'])->toBe('primary')
                ->and($blocks[4]['elements'][0]['value'])->toBe(sprintf(
                    'leave-request_%s_%s',
                    LeaveRequestStatusEnum::Approved->value,
                    $leaveRequestDTO->id->toString()
                ))
                ->and($blocks[4]['elements'][1]['type'])->toBe('button')
                ->and($blocks[4]['elements'][1]['text']['text'])->toBe(' 🚫 Reject ')
                ->and($blocks[4]['elements'][1]['value'])->toBe(sprintf(
                    'leave-request_%s_%s',
                    LeaveRequestStatusEnum::Rejected->value,
                    $leaveRequestDTO->id->toString()
                ))
                ->and($options->toArray()['channel'])->toBe($this->requestsApproveChannelId)
                ->and($message->getSubject())->toBe('Absence Approval Request');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});
