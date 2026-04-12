<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Security\SlackTokenEncryptor;
use App\Infrastructure\Slack\Http\SlackProfileApiClient;
use App\Infrastructure\Slack\Repository\SlackAdminTokenRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StoreSlackAdminTokenCommandHandler
{
    public function __construct(
        #[Autowire(env: 'SLACK_CLIENT_ID')]
        private readonly string $clientId,
        #[Autowire(env: 'SLACK_CLIENT_SECRET')]
        private readonly string $clientSecret,
        private readonly SlackProfileApiClient $client,
        private readonly SlackTokenEncryptor $encryptor,
        private readonly SlackAdminTokenRepositoryInterface $repository,
    ) {
    }

    public function handle(string $code, string $redirectUri): void
    {
        $result = $this->client->exchangeOauthCode($code, $redirectUri, $this->clientId, $this->clientSecret);

        $encrypted = $this->encryptor->encrypt($result['access_token']);

        $this->repository->save($encrypted, $result['user_id']);
    }
}
