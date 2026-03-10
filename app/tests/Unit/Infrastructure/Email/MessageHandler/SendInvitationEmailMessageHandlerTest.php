<?php

declare(strict_types=1);

use App\Infrastructure\Email\Message\SendInvitationEmailMessage;
use App\Infrastructure\Email\MessageHandler\SendInvitationEmailMessageHandler;
use App\Infrastructure\Email\UseCase\Command\SendInvitationEmailCommandHandler;
use App\Tests\_fixtures\Shared\DTO\InvitationDTOFixture;

beforeEach(function (): void {
    $this->commandHandler = mock(SendInvitationEmailCommandHandler::class);

    $this->handler = new SendInvitationEmailMessageHandler(
        handler: $this->commandHandler,
    );
});

it('delegates to invitation command handler', function () {
    $invitationDTO = InvitationDTOFixture::create();
    $message = new SendInvitationEmailMessage($invitationDTO);

    $this->commandHandler
        ->expects('handle')
        ->once()
        ->with($invitationDTO);

    ($this->handler)($message);
});
