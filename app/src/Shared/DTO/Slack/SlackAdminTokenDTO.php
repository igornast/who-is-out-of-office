<?php

declare(strict_types=1);

namespace App\Shared\DTO\Slack;

class SlackAdminTokenDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $plainToken,
        public readonly string $slackUserId,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
