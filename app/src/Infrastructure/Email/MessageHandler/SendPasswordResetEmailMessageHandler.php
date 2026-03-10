<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\MessageHandler;

use App\Infrastructure\Email\Message\SendPasswordResetEmailMessage;
use App\Infrastructure\Email\UseCase\Command\SendPasswordResetEmailCommandHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendPasswordResetEmailMessageHandler
{
    public function __construct(
        private readonly SendPasswordResetEmailCommandHandler $handler,
    ) {
    }

    public function __invoke(SendPasswordResetEmailMessage $message): void
    {
        $this->handler->handle($message->email, $message->token);
    }
}
