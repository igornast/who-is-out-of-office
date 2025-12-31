<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum AppSettingsEnum: string
{
    case AUTO_APPROVE = 'leave_request.auto_approve';
    case AUTO_APPROVE_DELAY = 'leave_request.auto_approve_delay';
}
