<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Exception\SlackStatusApiException;
use App\Infrastructure\Slack\Http\SlackProfileApiClient;
use App\Infrastructure\Slack\UseCase\Command\RevokeSlackAdminTokenCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\SetSlackStatusCommandHandler;
use App\Infrastructure\Slack\UseCase\Query\GetSlackAdminTokenQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use App\Tests\_fixtures\Shared\DTO\Slack\SlackAdminTokenDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->client = mock(SlackProfileApiClient::class);
    $this->tokenQuery = mock(GetSlackAdminTokenQueryHandler::class);
    $this->revoke = mock(RevokeSlackAdminTokenCommandHandler::class);
    $this->logger = mock(LoggerInterface::class);

    $this->handler = new SetSlackStatusCommandHandler(
        $this->client,
        $this->tokenQuery,
        $this->revoke,
        $this->logger,
    );

    $this->leaveType = LeaveRequestTypeDTOFixture::create(['name' => 'Vacation', 'slackStatusEmoji' => ':palm_tree:']);
    $this->until = new DateTimeImmutable('2026-03-24');
});

it('skips silently when no admin token is configured', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(null);
    $this->client->shouldNotReceive('setProfileStatus');

    expect($this->handler->handle($user, $this->leaveType, $this->until))->toBeFalse();
});

it('skips silently when user has no slack member id', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => null]);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->shouldNotReceive('setProfileStatus');

    expect($this->handler->handle($user, $this->leaveType, $this->until))->toBeFalse();
});

it('skips silently when user opted out', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123', 'slackStatusSyncEnabled' => false]);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->shouldNotReceive('setProfileStatus');

    expect($this->handler->handle($user, $this->leaveType, $this->until))->toBeFalse();
});

it('calls the client with the expected text, emoji, and end-of-day expiration', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $token = SlackAdminTokenDTOFixture::create(['plainToken' => 'xoxp-admin']);
    $expectedExpires = $this->until->setTime(23, 59, 59)->getTimestamp();

    $this->tokenQuery->expects('handle')->andReturn($token);
    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->with('xoxp-admin', 'U123', 'Vacation until Mar 24', ':palm_tree:', $expectedExpires);

    expect($this->handler->handle($user, $this->leaveType, $this->until))->toBeTrue();
});

it('uses :calendar: fallback when leave type has no slackStatusEmoji', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $leaveType = LeaveRequestTypeDTOFixture::create(['name' => 'Training', 'slackStatusEmoji' => null]);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create(['plainToken' => 'xoxp-admin']));

    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->with('xoxp-admin', 'U123', Mockery::any(), ':calendar:', Mockery::any());

    expect($this->handler->handle($user, $leaveType, $this->until))->toBeTrue();
});

it('revokes the admin token when the API returns a fatal auth error', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->expects('setProfileStatus')->andThrow(new SlackStatusApiException('token_revoked'));
    $this->logger->expects('error')->once();
    $this->revoke->expects('handle')->once();

    expect($this->handler->handle($user, $this->leaveType, $this->until))->toBeFalse();
});

it('logs but does not revoke on non-fatal api errors', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->expects('setProfileStatus')->andThrow(new SlackStatusApiException('user_not_found'));
    $this->logger->expects('warning')->once();
    $this->revoke->shouldNotReceive('handle');

    expect($this->handler->handle($user, $this->leaveType, $this->until))->toBeFalse();
});
