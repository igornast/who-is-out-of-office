<?php

declare(strict_types=1);

namespace App\Module\Settings\Exception;

use App\Shared\Enum\AppSettingsEnum;

class InvalidAppSettingTypeException extends \Exception
{
    public function __construct(string $expected, AppSettingsEnum $settingsEnum)
    {
        $message = sprintf(
            'Unexpected app setting type for "%s", expected "%s"',
            $settingsEnum->value,
            $expected,
        );

        parent::__construct($message);
    }
}
