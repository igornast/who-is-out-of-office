<?php

declare(strict_types=1);

namespace App\Module\Admin\Service;

class AutoApproveColumnState
{
    public const KIND_COUNTDOWN = 'countdown';
    public const KIND_AUTO_APPROVED = 'auto_approved';
    public const KIND_MANUALLY_APPROVED = 'manually_approved';
    public const KIND_NONE = 'none';

    public function __construct(
        public readonly string $kind,
        public readonly ?\DateTimeImmutable $target = null,
    ) {
    }
}
