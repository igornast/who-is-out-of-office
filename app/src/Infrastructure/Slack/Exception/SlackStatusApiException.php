<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Exception;

class SlackStatusApiException extends \RuntimeException
{
    public function __construct(
        public readonly string $slackError,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Slack API error: %s', $slackError), 0, $previous);
    }

    public function isFatalAuthError(): bool
    {
        return in_array($this->slackError, ['invalid_auth', 'token_revoked', 'account_inactive'], true);
    }

    public function isInvalidEmojiError(): bool
    {
        return in_array($this->slackError, [
            'invalid_emoji_not_allowed',
            'profile_status_set_failed_not_valid_emoji',
            'profile_status_set_failed_not_emoji_syntax',
        ], true);
    }
}
