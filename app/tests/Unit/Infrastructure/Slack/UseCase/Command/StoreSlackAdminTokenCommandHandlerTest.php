<?php

declare(strict_types=1);

use App\Infrastructure\Security\SlackTokenEncryptor;
use App\Infrastructure\Slack\Http\SlackProfileApiClient;
use App\Infrastructure\Slack\Repository\SlackAdminTokenRepositoryInterface;
use App\Infrastructure\Slack\UseCase\Command\StoreSlackAdminTokenCommandHandler;

beforeEach(function (): void {
    $this->client = mock(SlackProfileApiClient::class);
    $this->encryptor = mock(SlackTokenEncryptor::class);
    $this->repository = mock(SlackAdminTokenRepositoryInterface::class);

    $this->handler = new StoreSlackAdminTokenCommandHandler(
        'client-id',
        'client-secret',
        $this->client,
        $this->encryptor,
        $this->repository,
    );
});

it('exchanges the code, encrypts, and persists the token', function (): void {
    $this->client
        ->expects('exchangeOauthCode')
        ->once()
        ->with('the-code', 'https://app.test/app/settings/slack-status-sync/oauth/callback', 'client-id', 'client-secret')
        ->andReturn(['access_token' => 'xoxp-new', 'user_id' => 'U42']);

    $this->encryptor
        ->expects('encrypt')
        ->once()
        ->with('xoxp-new')
        ->andReturn('encrypted-blob');

    $this->repository
        ->expects('save')
        ->once()
        ->with('encrypted-blob', 'U42');

    $this->handler->handle('the-code', 'https://app.test/app/settings/slack-status-sync/oauth/callback');
});
