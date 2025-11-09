<?php

declare(strict_types=1);

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Infrastructure\Slack\UseCase\Command\SendChangeConfirmationToAbsenceChannelCommandHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

beforeEach(function (): void {
    $this->httpClient = mock(HttpClientInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);

    $this->handler = new SendChangeConfirmationToAbsenceChannelCommandHandler(
        httpClient: $this->httpClient,
        urlGenerator: $this->urlGenerator
    );
});

it('sends confirmation message with approved by information', function () {
    $approver = UserDTOFixture::create([
        'firstName' => 'Jane',
        'lastName' => 'Manager',
    ]);

    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('123e4567-e89b-12d3-a456-426614174000'),
        'approvedBy' => $approver,
    ]);

    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Approved,
        type: 'leave-request',
        identifier: $leaveRequestDTO->id->toString(),
        channel: 'C1234567890',
        responseUrl: 'https://hooks.slack.com/actions/T123/456/ABC',
        memberId: 'U123456'
    );

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

    $expectedMessage = sprintf(
        '%s, absence for %s %s (%s - %s). By: %s %s <%s|Details>',
        $notificationDTO->status->name,
        $leaveRequestDTO->user->firstName,
        $leaveRequestDTO->user->lastName,
        $leaveRequestDTO->startDate->format('M d, Y'),
        $leaveRequestDTO->endDate->format('M d, Y'),
        $approver->firstName,
        $approver->lastName,
        $expectedUrl
    );

    $this->httpClient
        ->expects('request')
        ->once()
        ->with(
            Request::METHOD_POST,
            'https://hooks.slack.com/actions/T123/456/ABC',
            [
                'json' => [
                    'text' => $expectedMessage,
                    'replace_original' => true,
                ],
            ]
        );

    $this->handler->handle($leaveRequestDTO, $notificationDTO);
});

it('sends confirmation message without approved by information', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('987e6543-e21b-12d3-a456-426614174999'),
        'approvedBy' => null,
    ]);

    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Rejected,
        type: 'leave-request',
        identifier: $leaveRequestDTO->id->toString(),
        channel: 'C9876543210',
        responseUrl: 'https://hooks.slack.com/actions/T999/888/XYZ',
        memberId: 'U999888'
    );

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

    $expectedMessage = sprintf(
        '%s, absence for %s %s (%s - %s). <%s|Details>',
        $notificationDTO->status->name,
        $leaveRequestDTO->user->firstName,
        $leaveRequestDTO->user->lastName,
        $leaveRequestDTO->startDate->format('M d, Y'),
        $leaveRequestDTO->endDate->format('M d, Y'),
        $expectedUrl
    );

    $this->httpClient
        ->expects('request')
        ->once()
        ->with(
            Request::METHOD_POST,
            'https://hooks.slack.com/actions/T999/888/XYZ',
            [
                'json' => [
                    'text' => $expectedMessage,
                    'replace_original' => true,
                ],
            ]
        );

    $this->handler->handle($leaveRequestDTO, $notificationDTO);
});

it('does not send message when response url is null', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'approvedBy' => null,
    ]);

    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Approved,
        type: 'leave-request',
        identifier: $leaveRequestDTO->id->toString(),
        channel: 'C1234567890',
        responseUrl: null,
        memberId: 'U123456'
    );

    $this->urlGenerator
        ->expects('generate')
        ->never();

    $this->httpClient
        ->expects('request')
        ->never();

    $this->handler->handle($leaveRequestDTO, $notificationDTO);
});
