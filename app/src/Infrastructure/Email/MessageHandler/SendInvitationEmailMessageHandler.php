<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\MessageHandler;

use App\Infrastructure\Email\Message\SendInvitationEmailMessage;
use App\Infrastructure\Email\UseCase\Command\SendInvitationEmailCommandHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendInvitationEmailMessageHandler
{
    public function __construct(
        private readonly SendInvitationEmailCommandHandler $handler,
    ) {
    }

    public function __invoke(SendInvitationEmailMessage $message): void
    {
        $this->handler->handle($message->invitationDTO);
    }
}
