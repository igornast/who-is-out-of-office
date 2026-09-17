<?php

declare(strict_types=1);

namespace App\Shared\Exception;

class ConcurrentInvitationException extends \RuntimeException
{
    public function __construct(
        public readonly string $userId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('An invitation for user "%s" was issued concurrently.', $userId), 0, $previous);
    }
}
