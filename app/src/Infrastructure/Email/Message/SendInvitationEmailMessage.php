<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\Message;

use App\Shared\DTO\InvitationDTO;

class SendInvitationEmailMessage
{
    public function __construct(
        public readonly InvitationDTO $invitationDTO,
    ) {
    }
}
