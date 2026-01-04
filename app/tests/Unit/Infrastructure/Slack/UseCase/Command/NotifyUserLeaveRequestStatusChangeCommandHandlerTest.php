<?php

declare(strict_types=1);

use App\Infrastructure\Slack\UseCase\Command\NotifyUserLeaveRequestStatusChangeCommandHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

beforeEach(function (): void {
    $this->chatter = mock(ChatterInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);

    $this->handler = new NotifyUserLeaveRequestStatusChangeCommandHandler(
        chatter: $this->chatter,
        urlGenerator: $this->urlGenerator
    );
});

it('sends slack notification to user with approved by information', function () {
    $approver = UserDTOFixture::create([
        'firstName' => 'John',
        'lastName' => 'Manager',
    ]);

    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('123e4567-e89b-12d3-a456-426614174000'),
        'status' => LeaveRequestStatusEnum::Approved,
        'approvedBy' => $approver,
    ]);
    $leaveRequestDTO->user->slackMemberId = 'U1234567890';

    $expectedUrl = 'https://example.com/app/dashboard/leave-request/'.$leaveRequestDTO->id->toString();

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
        ->withArgs(function (ChatMessage $message) use ($leaveRequestDTO, $expectedUrl, $approver) {
            $options = $message->getOptions();

            expect($options)->toBeInstanceOf(SlackOptions::class);

            $blocks = $options->toArray()['blocks'];

            expect($blocks[0]['type'])->toBe('header')
                ->and($blocks[0]['text']['type'])->toBe('plain_text')
                ->and($blocks[0]['text']['text'])->toBe('Absence request status change')
                ->and($blocks[0]['text']['emoji'])->toBeTrue()
                ->and($blocks[1]['type'])->toBe('divider')
                ->and($blocks[2]['type'])->toBe('section')
                ->and($blocks[2]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[2]['fields'][0]['text'])->toBe(sprintf('*Type:* %s', $leaveRequestDTO->leaveType->name))
                ->and($blocks[2]['fields'][1]['type'])->toBe('mrkdwn')
                ->and($blocks[2]['fields'][1]['text'])->toBe(sprintf('*Staus:* %s', $leaveRequestDTO->status->name))
                ->and($blocks[3]['type'])->toBe('section')
                ->and($blocks[3]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[3]['fields'][0]['text'])->toBe(sprintf(
                    '*When:* %s - %s',
                    $leaveRequestDTO->startDate->format('M d, Y'),
                    $leaveRequestDTO->endDate->format('M d, Y')
                ))
                ->and($blocks[3]['fields'][1]['type'])->toBe('mrkdwn')
                ->and($blocks[3]['fields'][1]['text'])->toBe(sprintf(
                    '*By:* %s %s',
                    $approver->firstName,
                    $approver->lastName
                ))
                ->and($blocks[4]['type'])->toBe('section')
                ->and($blocks[4]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[4]['fields'][0]['text'])->toBe(sprintf('<%s|View the absence request>', $expectedUrl))
                ->and($options->toArray()['channel'])->toBe($leaveRequestDTO->user->slackMemberId)
                ->and($message->getSubject())->toBe('Absence Request Update');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('sends slack notification to user without approved by information', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('987e6543-e21b-12d3-a456-426614174999'),
        'status' => LeaveRequestStatusEnum::Pending,
        'approvedBy' => null,
    ]);
    $leaveRequestDTO->user->slackMemberId = 'U9876543210';

    $expectedUrl = 'https://example.com/app/dashboard/leave-request/'.$leaveRequestDTO->id->toString();

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

            expect($options)->toBeInstanceOf(SlackOptions::class);

            $blocks = $options->toArray()['blocks'];

            expect($blocks[0]['type'])->toBe('header')
                ->and($blocks[0]['text']['type'])->toBe('plain_text')
                ->and($blocks[0]['text']['text'])->toBe('Absence request status change')
                ->and($blocks[0]['text']['emoji'])->toBeTrue()
                ->and($blocks[1]['type'])->toBe('divider')
                ->and($blocks[2]['type'])->toBe('section')
                ->and($blocks[2]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[2]['fields'][0]['text'])->toBe(sprintf('*Type:* %s', $leaveRequestDTO->leaveType->name))
                ->and($blocks[2]['fields'][1]['type'])->toBe('mrkdwn')
                ->and($blocks[2]['fields'][1]['text'])->toBe(sprintf('*Staus:* %s', $leaveRequestDTO->status->name))
                ->and($blocks[3]['type'])->toBe('section')
                ->and($blocks[3]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[3]['fields'][0]['text'])->toBe(sprintf(
                    '*When:* %s - %s',
                    $leaveRequestDTO->startDate->format('M d, Y'),
                    $leaveRequestDTO->endDate->format('M d, Y')
                ))
                ->and($blocks[3]['fields'][1]['type'])->toBe('mrkdwn')
                ->and($blocks[3]['fields'][1]['text'])->toBe('-')
                ->and($blocks[4]['type'])->toBe('section')
                ->and($blocks[4]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[4]['fields'][0]['text'])->toBe(sprintf('<%s|View the absence request>', $expectedUrl))
                ->and($options->toArray()['channel'])->toBe($leaveRequestDTO->user->slackMemberId)
                ->and($message->getSubject())->toBe('Absence Request Update');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('does not send notification when user has no slack member id', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'status' => LeaveRequestStatusEnum::Approved,
        'approvedBy' => null,
    ]);
    $leaveRequestDTO->user->slackMemberId = null;

    $this->urlGenerator
        ->expects('generate')
        ->never();

    $this->chatter
        ->expects('send')
        ->never();

    $this->handler->handle($leaveRequestDTO);
});

it('sends slack notification with auto approved text when request is auto approved', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('abc12345-e89b-12d3-a456-426614174111'),
        'status' => LeaveRequestStatusEnum::Approved,
        'isAutoApproved' => true,
        'approvedBy' => null,
    ]);
    $leaveRequestDTO->user->slackMemberId = 'U1111111111';

    $expectedUrl = 'https://example.com/app/dashboard/leave-request/'.$leaveRequestDTO->id->toString();

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

            expect($options)->toBeInstanceOf(SlackOptions::class);

            $blocks = $options->toArray()['blocks'];

            expect($blocks[0]['type'])->toBe('header')
                ->and($blocks[0]['text']['type'])->toBe('plain_text')
                ->and($blocks[0]['text']['text'])->toBe('Absence request status change')
                ->and($blocks[0]['text']['emoji'])->toBeTrue()
                ->and($blocks[1]['type'])->toBe('divider')
                ->and($blocks[2]['type'])->toBe('section')
                ->and($blocks[2]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[2]['fields'][0]['text'])->toBe(sprintf('*Type:* %s', $leaveRequestDTO->leaveType->name))
                ->and($blocks[2]['fields'][1]['type'])->toBe('mrkdwn')
                ->and($blocks[2]['fields'][1]['text'])->toBe(sprintf('*Staus:* %s', $leaveRequestDTO->status->name))
                ->and($blocks[3]['type'])->toBe('section')
                ->and($blocks[3]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[3]['fields'][0]['text'])->toBe(sprintf(
                    '*When:* %s - %s',
                    $leaveRequestDTO->startDate->format('M d, Y'),
                    $leaveRequestDTO->endDate->format('M d, Y')
                ))
                ->and($blocks[3]['fields'][1]['type'])->toBe('mrkdwn')
                ->and($blocks[3]['fields'][1]['text'])->toBe('*By:* auto approved')
                ->and($blocks[4]['type'])->toBe('section')
                ->and($blocks[4]['fields'][0]['type'])->toBe('mrkdwn')
                ->and($blocks[4]['fields'][0]['text'])->toBe(sprintf('<%s|View the absence request>', $expectedUrl))
                ->and($options->toArray()['channel'])->toBe($leaveRequestDTO->user->slackMemberId)
                ->and($message->getSubject())->toBe('Absence Request Update');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});
