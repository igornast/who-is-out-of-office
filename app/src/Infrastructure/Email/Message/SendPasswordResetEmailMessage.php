<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\Message;

class SendPasswordResetEmailMessage
{
    public function __construct(
        public readonly string $email,
        public readonly string $token,
    ) {
    }
}
