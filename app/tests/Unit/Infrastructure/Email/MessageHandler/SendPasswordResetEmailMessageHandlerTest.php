<?php

declare(strict_types=1);

use App\Infrastructure\Email\Message\SendPasswordResetEmailMessage;
use App\Infrastructure\Email\MessageHandler\SendPasswordResetEmailMessageHandler;
use App\Infrastructure\Email\UseCase\Command\SendPasswordResetEmailCommandHandler;

beforeEach(function (): void {
    $this->commandHandler = mock(SendPasswordResetEmailCommandHandler::class);

    $this->handler = new SendPasswordResetEmailMessageHandler(
        handler: $this->commandHandler,
    );
});

it('delegates to password reset email command handler', function () {
    $message = new SendPasswordResetEmailMessage('user@example.com', 'reset-token-abc');

    $this->commandHandler
        ->expects('handle')
        ->once()
        ->with('user@example.com', 'reset-token-abc');

    ($this->handler)($message);
});
