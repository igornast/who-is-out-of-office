<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Ramsey\Uuid\UuidInterface;

class SlackAdminToken
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public string $encryptedToken,
        public string $slackUserId,
    ) {
        $this->initializeTimestamps();
    }
}
