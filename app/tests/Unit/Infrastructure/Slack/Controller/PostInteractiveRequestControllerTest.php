<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Controller\PostInteractiveRequestController;
use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Infrastructure\Slack\Service\RequestVerifier;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\EmailFacadeInterface;
use App\Shared\Facade\SlackFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->requestVerifier = mock(RequestVerifier::class);
    $this->slackFacade = mock(SlackFacadeInterface::class);
    $this->emailFacade = mock(EmailFacadeInterface::class);

    $this->requestController = new PostInteractiveRequestController(
        requestVerifier: $this->requestVerifier,
        slackFacade: $this->slackFacade,
        emailFacade: $this->emailFacade,
    );
});

it('returns bad request when signature is invalid', function () {
    $request = Request::create('/api/slack/interactive-endpoint', 'POST');

    $this->requestVerifier
        ->expects('isValid')
        ->once()
        ->with($request)
        ->andReturn(false);

    $this->slackFacade
        ->expects('handleInteractiveNotification')
        ->never();

    $response = ($this->requestController)($request);

    expect($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);
});

it('handles valid interactive notification and sends approved email', function () {
    $payload = json_encode([
        'actions' => [
            ['value' => 'lr_approved_abc-123'],
        ],
        'channel' => ['id' => 'C12345'],
        'response_url' => 'https://hooks.slack.com/actions/response',
        'user' => ['id' => 'U12345'],
    ]);

    $request = Request::create(
        '/api/slack/interactive-endpoint',
        'POST',
        ['payload' => $payload],
    );

    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'status' => LeaveRequestStatusEnum::Approved,
        'isAutoApproved' => false,
    ]);

    $this->requestVerifier
        ->expects('isValid')
        ->once()
        ->with($request)
        ->andReturn(true);

    $this->slackFacade
        ->expects('handleInteractiveNotification')
        ->once()
        ->withArgs(fn (InteractiveNotificationDTO $dto) => 'abc-123' === $dto->identifier
            && 'lr' === $dto->type
            && 'C12345' === $dto->channel
            && 'U12345' === $dto->memberId)
        ->andReturn($leaveRequestDTO);

    $this->emailFacade
        ->expects('sendLeaveRequestApprovedEmail')
        ->once()
        ->with($leaveRequestDTO);

    $response = ($this->requestController)($request);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

it('handles valid interactive notification and sends rejected email', function () {
    $payload = json_encode([
        'actions' => [
            ['value' => 'lr_rejected_abc-456'],
        ],
        'channel' => ['id' => 'C12345'],
        'response_url' => 'https://hooks.slack.com/actions/response',
        'user' => ['id' => 'U12345'],
    ]);

    $request = Request::create(
        '/api/slack/interactive-endpoint',
        'POST',
        ['payload' => $payload],
    );

    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'status' => LeaveRequestStatusEnum::Rejected,
        'isAutoApproved' => false,
    ]);

    $this->requestVerifier
        ->expects('isValid')
        ->andReturn(true);

    $this->slackFacade
        ->expects('handleInteractiveNotification')
        ->once()
        ->andReturn($leaveRequestDTO);

    $this->emailFacade
        ->expects('sendLeaveRequestRejectedEmail')
        ->once()
        ->with($leaveRequestDTO);

    $response = ($this->requestController)($request);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

it('skips email when leave request is auto approved', function () {
    $payload = json_encode([
        'actions' => [
            ['value' => 'lr_approved_abc-789'],
        ],
        'channel' => ['id' => 'C12345'],
        'response_url' => 'https://hooks.slack.com/actions/response',
        'user' => ['id' => 'U12345'],
    ]);

    $request = Request::create(
        '/api/slack/interactive-endpoint',
        'POST',
        ['payload' => $payload],
    );

    $leaveRequestDTO = LeaveRequestDTOFixture::create(['isAutoApproved' => true]);

    $this->requestVerifier
        ->expects('isValid')
        ->andReturn(true);

    $this->slackFacade
        ->expects('handleInteractiveNotification')
        ->once()
        ->andReturn($leaveRequestDTO);

    $this->emailFacade
        ->shouldNotReceive('sendLeaveRequestApprovedEmail');

    $this->emailFacade
        ->shouldNotReceive('sendLeaveRequestRejectedEmail');

    $response = ($this->requestController)($request);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});
