<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum AppSettingsEnum: string
{
    case AUTO_APPROVE = 'leave_request.auto_approve';
    case AUTO_APPROVE_DELAY = 'leave_request.auto_approve_delay';
    case DEFAULT_ANNUAL_ALLOWANCE = 'leave_request.default_annual_allowance';
    case MIN_NOTICE_DAYS = 'leave_request.min_notice_days';
    case MAX_CONSECUTIVE_DAYS = 'leave_request.max_consecutive_days';
}
