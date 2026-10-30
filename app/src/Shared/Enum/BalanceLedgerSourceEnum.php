<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum BalanceLedgerSourceEnum: string
{
    case App = 'app';
    case Slack = 'slack';
    case Scheduler = 'scheduler';
    case Migration = 'migration';
}
