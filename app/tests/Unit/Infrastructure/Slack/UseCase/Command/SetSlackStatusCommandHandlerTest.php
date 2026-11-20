<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Exception\SlackStatusApiException;
use App\Infrastructure\Slack\Http\SlackProfileApiClient;
use App\Infrastructure\Slack\UseCase\Command\RevokeSlackAdminTokenCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\SetSlackStatusCommandHandler;
use App\Infrastructure\Slack\UseCase\Query\GetSlackAdminTokenQueryHandler;
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

    $this->until = new DateTimeImmutable('2026-03-24');
});

it('skips silently when no admin token is configured', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(null);
    $this->client->shouldNotReceive('setProfileStatus');

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':palm_tree:', $this->until))->toBeFalse();
});

it('skips silently when user has no slack member id', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => null]);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->shouldNotReceive('setProfileStatus');

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':palm_tree:', $this->until))->toBeFalse();
});

it('skips silently when user opted out', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123', 'slackStatusSyncEnabled' => false]);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->shouldNotReceive('setProfileStatus');

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':palm_tree:', $this->until))->toBeFalse();
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

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':palm_tree:', $this->until))->toBeTrue();
});

it('retries once with the fallback emoji when slack rejects the emoji', function (string $error): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create(['plainToken' => 'xoxp-admin']));

    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->ordered()
        ->with('xoxp-admin', 'U123', 'Vacation until Mar 24', ':flag-xx:', $this->until->setTime(23, 59, 59)->getTimestamp())
        ->andThrow(new SlackStatusApiException($error));
    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->ordered()
        ->with('xoxp-admin', 'U123', 'Vacation until Mar 24', ':crossed_flags:', $this->until->setTime(23, 59, 59)->getTimestamp());

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':flag-xx:', $this->until, ':crossed_flags:'))->toBeTrue();
})->with([
    'invalid_emoji_not_allowed',
    'profile_status_set_failed_not_valid_emoji',
    'profile_status_set_failed_not_emoji_syntax',
]);

it('does not retry with the fallback emoji on other non-fatal errors', function (string $error): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->expects('setProfileStatus')->once()->andThrow(new SlackStatusApiException($error));
    $this->logger->expects('warning')->once();

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':flag-xx:', $this->until, ':crossed_flags:'))->toBeFalse();
})->with(['ratelimited', 'http_error']);

it('handles a fatal auth error from the fallback attempt like the initial attempt', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create(['plainToken' => 'xoxp-admin']));
    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->ordered()
        ->with('xoxp-admin', 'U123', 'Vacation until Mar 24', ':flag-xx:', $this->until->setTime(23, 59, 59)->getTimestamp())
        ->andThrow(new SlackStatusApiException('invalid_emoji_not_allowed'));
    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->ordered()
        ->with('xoxp-admin', 'U123', 'Vacation until Mar 24', ':crossed_flags:', $this->until->setTime(23, 59, 59)->getTimestamp())
        ->andThrow(new SlackStatusApiException('token_revoked'));
    $this->logger->expects('error')->once();
    $this->revoke->expects('handle')->once();

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':flag-xx:', $this->until, ':crossed_flags:'))->toBeFalse();
});

it('does not retry when no fallback emoji is given', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->expects('setProfileStatus')->once()->andThrow(new SlackStatusApiException('invalid_emoji_not_allowed'));
    $this->logger->expects('warning')->once();

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':flag-xx:', $this->until))->toBeFalse();
});

it('does not retry when the fallback emoji is the same as the rejected emoji', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->expects('setProfileStatus')->once()->andThrow(new SlackStatusApiException('invalid_emoji_not_allowed'));
    $this->logger->expects('warning')->once();

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':flag-xx:', $this->until, ':flag-xx:'))->toBeFalse();
});

it('returns false when the fallback retry also fails with a non-fatal error', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create(['plainToken' => 'xoxp-admin']));
    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->ordered()
        ->with('xoxp-admin', 'U123', 'Vacation until Mar 24', ':flag-xx:', $this->until->setTime(23, 59, 59)->getTimestamp())
        ->andThrow(new SlackStatusApiException('invalid_emoji_not_allowed'));
    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->ordered()
        ->with('xoxp-admin', 'U123', 'Vacation until Mar 24', ':crossed_flags:', $this->until->setTime(23, 59, 59)->getTimestamp())
        ->andThrow(new SlackStatusApiException('ratelimited'));
    $this->logger->expects('warning')->once();
    $this->revoke->shouldNotReceive('handle');

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':flag-xx:', $this->until, ':crossed_flags:'))->toBeFalse();
});

it('does not retry on a fatal auth error from the first attempt and revokes the token', function (string $error): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->expects('setProfileStatus')->once()->andThrow(new SlackStatusApiException($error));
    $this->logger->expects('error')->once();
    $this->revoke->expects('handle')->once();

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':flag-xx:', $this->until, ':crossed_flags:'))->toBeFalse();
})->with(['invalid_auth', 'token_revoked', 'account_inactive']);

it('does not retry again when the fallback emoji is also rejected', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create(['plainToken' => 'xoxp-admin']));
    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->ordered()
        ->with('xoxp-admin', 'U123', 'Vacation until Mar 24', ':flag-xx:', $this->until->setTime(23, 59, 59)->getTimestamp())
        ->andThrow(new SlackStatusApiException('invalid_emoji_not_allowed'));
    $this->client
        ->expects('setProfileStatus')
        ->once()
        ->ordered()
        ->with('xoxp-admin', 'U123', 'Vacation until Mar 24', ':crossed_flags:', $this->until->setTime(23, 59, 59)->getTimestamp())
        ->andThrow(new SlackStatusApiException('profile_status_set_failed_not_valid_emoji'));
    $this->logger->expects('warning')->once();
    $this->revoke->shouldNotReceive('handle');

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':flag-xx:', $this->until, ':crossed_flags:'))->toBeFalse();
});

it('propagates unexpected client exceptions without retrying', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->expects('setProfileStatus')->once()->andThrow(new RuntimeException('boom'));
    $this->revoke->shouldNotReceive('handle');

    expect(fn () => $this->handler->handle($user, 'Vacation until Mar 24', ':flag-xx:', $this->until, ':crossed_flags:'))
        ->toThrow(RuntimeException::class, 'boom');
});

it('revokes the admin token when the API returns a fatal auth error', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->expects('setProfileStatus')->andThrow(new SlackStatusApiException('token_revoked'));
    $this->logger->expects('error')->once();
    $this->revoke->expects('handle')->once();

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':palm_tree:', $this->until))->toBeFalse();
});

it('logs but does not revoke on non-fatal api errors', function (): void {
    $user = UserDTOFixture::create(['slackMemberId' => 'U123']);
    $this->tokenQuery->expects('handle')->andReturn(SlackAdminTokenDTOFixture::create());
    $this->client->expects('setProfileStatus')->andThrow(new SlackStatusApiException('user_not_found'));
    $this->logger->expects('warning')->once();
    $this->revoke->shouldNotReceive('handle');

    expect($this->handler->handle($user, 'Vacation until Mar 24', ':palm_tree:', $this->until))->toBeFalse();
});

it('identifies documented and runtime invalid emoji errors', function (string $error, bool $expected): void {
    expect((new SlackStatusApiException($error))->isInvalidEmojiError())->toBe($expected);
})->with([
    ['invalid_emoji_not_allowed', true],
    ['profile_status_set_failed_not_valid_emoji', true],
    ['profile_status_set_failed_not_emoji_syntax', true],
    ['ratelimited', false],
    ['http_error', false],
    ['invalid_auth', false],
    ['too_long', false],
]);
