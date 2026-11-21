<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\Exception\SlackStatusApiException;
use App\Infrastructure\Slack\Http\SlackProfileApiClient;
use App\Infrastructure\Slack\UseCase\Query\GetSlackAdminTokenQueryHandler;
use App\Shared\DTO\UserDTO;
use Psr\Log\LoggerInterface;

class SetSlackStatusCommandHandler
{
    public function __construct(
        private readonly SlackProfileApiClient $client,
        private readonly GetSlackAdminTokenQueryHandler $tokenQuery,
        private readonly RevokeSlackAdminTokenCommandHandler $revokeHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(UserDTO $user, string $text, string $emoji, \DateTimeImmutable $until, ?string $fallbackEmoji = null): bool
    {
        $token = $this->tokenQuery->handle();
        if (null === $token) {
            return false;
        }

        if (null === $user->slackMemberId) {
            return false;
        }

        if (false === $user->slackStatusSyncEnabled) {
            return false;
        }

        $expiresAt = $until->setTime(23, 59, 59)->getTimestamp();

        try {
            $this->client->setProfileStatus($token->plainToken, $user->slackMemberId, $text, $emoji, $expiresAt);

            return true;
        } catch (SlackStatusApiException $e) {
            if ($e->isInvalidEmojiError() && null !== $fallbackEmoji && $fallbackEmoji !== $emoji) {
                try {
                    $this->client->setProfileStatus($token->plainToken, $user->slackMemberId, $text, $fallbackEmoji, $expiresAt);

                    return true;
                } catch (SlackStatusApiException $retryException) {
                    return $this->handleApiError($retryException, $user);
                }
            }

            return $this->handleApiError($e, $user);
        }
    }

    private function handleApiError(SlackStatusApiException $exception, UserDTO $user): bool
    {
        if ($exception->isFatalAuthError()) {
            $this->logger->error('Slack status set failed with fatal auth error; revoking admin token', ['error' => $exception->slackError]);
            $this->revokeHandler->handle();

            return false;
        }

        $this->logger->warning('Slack status set failed', ['error' => $exception->slackError, 'user' => $user->id]);

        return false;
    }
}
