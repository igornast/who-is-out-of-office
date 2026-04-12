<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Exception\SlackStatusApiException;
use App\Infrastructure\Slack\Http\SlackProfileApiClient;
use App\Infrastructure\Slack\UseCase\Command\ClearSlackStatusCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\RevokeSlackAdminTokenCommandHandler;
use App\Infrastructure\Slack\UseCase\Query\GetSlackAdminTokenQueryHandler;
use App\Tests\_fixtures\Shared\DTO\Slack\SlackAdminTokenDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->slckaClient = mock(SlackProfileApiClient::class);
    $this->tokenQuery = mock(GetSlackAdminTokenQueryHandler::class);
    $this->revoke = mock(RevokeSlackAdminTokenCommandHandler::class);
    $this->logger = mock(LoggerInterface::class);

    $this->handler = new ClearSlackStatusCommandHandler(
        $this->slckaClient,
        $this->tokenQuery,
        $this->revoke,
        $this->logger,
    );
});

it('returns false when no admin token is configured', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(null);
    $this->slckaClient->shouldNotReceive('clearProfileStatus');

    expect($this->handler->handle($user))->toBeFalse();
});

it('returns false when user has no slack member id', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => null]);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->slckaClient->shouldNotReceive('clearProfileStatus');

    expect($this->handler->handle($user))->toBeFalse();
});

it('returns false when user opted out', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123', 'slackStatusSyncEnabled' => false]);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->slckaClient->shouldNotReceive('clearProfileStatus');

    expect($this->handler->handle($user))->toBeFalse();
});

it('returns true after successful clear', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create(['plainToken' => 'xoxp-admin']));
    $this->slckaClient->expects('clearProfileStatus')->once()->with('xoxp-admin', 'U123');

    expect($this->handler->handle($user))->toBeTrue();
});

it('returns false and revokes on fatal auth error', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->slckaClient->expects('clearProfileStatus')->andThrow(new SlackStatusApiException('invalid_auth'));
    $this->logger->expects('error')->once();
    $this->revoke->expects('handle')->once();

    expect($this->handler->handle($user))->toBeFalse();
});

it('returns false and logs warning on non-fatal api error', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->slckaClient->expects('clearProfileStatus')->andThrow(new SlackStatusApiException('user_not_found'));
    $this->logger->expects('warning')->once();
    $this->revoke->shouldNotReceive('handle');

    expect($this->handler->handle($user))->toBeFalse();
});
