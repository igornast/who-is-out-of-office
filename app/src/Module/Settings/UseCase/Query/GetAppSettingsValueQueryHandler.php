<?php

declare(strict_types=1);

namespace App\Module\Settings\UseCase\Query;

use App\Shared\Enum\AppSettingsEnum;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

class GetAppSettingsValueQueryHandler
{
    public function __construct(
        #[Autowire(env: 'resolve:APP_SETTINGS_FILE')]
        private readonly string $appSettingsFilename,
    ) {
    }

    public function handle(AppSettingsEnum $settingsEnum): mixed
    {
        $content = Yaml::parseFile(filename: $this->appSettingsFilename);

        $settingsValue = $content;
        foreach (explode('.', $settingsEnum->value) as $key) {
            $settingsValue = &$settingsValue[$key];
        }

        return $settingsValue;
    }
}
