<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Controller\PostInteractiveRequestController;
use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Infrastructure\Slack\Service\RequestVerifier;
use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->requestVerifier = mock(RequestVerifier::class);
    $this->slackFacade = mock(SlackFacadeInterface::class);

    $this->requestController = new PostInteractiveRequestController(
        requestVerifier: $this->requestVerifier,
        slackFacade: $this->slackFacade,
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

it('handles valid interactive notification and returns ok', function () {
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
            && 'U12345' === $dto->memberId);

    $response = ($this->requestController)($request);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});
