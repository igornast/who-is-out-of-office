<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Repository;

use App\Shared\DTO\Slack\SlackAdminTokenDTO;

interface SlackAdminTokenRepositoryInterface
{
    public function findCurrent(): ?SlackAdminTokenDTO;

    public function save(string $encryptedToken, string $slackUserId): void;

    public function deleteAll(): void;
}
