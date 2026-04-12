<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\Exception\SlackStatusApiException;
use App\Infrastructure\Slack\Http\SlackProfileApiClient;
use App\Infrastructure\Slack\UseCase\Query\GetSlackAdminTokenQueryHandler;
use App\Shared\DTO\UserDTO;
use Psr\Log\LoggerInterface;

class ClearSlackStatusCommandHandler
{
    public function __construct(
        private readonly SlackProfileApiClient $client,
        private readonly GetSlackAdminTokenQueryHandler $tokenQuery,
        private readonly RevokeSlackAdminTokenCommandHandler $revokeHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(UserDTO $user): bool
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

        try {
            $this->client->clearProfileStatus($token->plainToken, $user->slackMemberId);

            return true;
        } catch (SlackStatusApiException $e) {
            if ($e->isFatalAuthError()) {
                $this->logger->error('Slack status clear failed with fatal auth error; revoking admin token', ['error' => $e->slackError]);
                $this->revokeHandler->handle();

                return false;
            }

            $this->logger->warning('Slack status clear failed', ['error' => $e->slackError, 'user' => $user->id]);

            return false;
        }
    }
}
