<?php

declare(strict_types=1);

namespace App\Shared\Facade;

interface AppSettingsFacadeInterface
{
    public function isAutoApprove(): bool;

    public function autoApproveDelay(): int;
}
