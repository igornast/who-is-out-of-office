<?php

declare(strict_types=1);

namespace App\Module\Settings\Exception;

class AppSettingsDisabledException extends \RuntimeException
{
    public function __construct(string $feature)
    {
        parent::__construct(sprintf('Feature "%s" is disabled in application settings', $feature));
    }
}
