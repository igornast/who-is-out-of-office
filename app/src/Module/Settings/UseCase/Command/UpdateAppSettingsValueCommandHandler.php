<?php

declare(strict_types=1);

namespace App\Module\Settings\UseCase\Command;

use App\Shared\DTO\Settings\AppSettingsDTO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

class UpdateAppSettingsValueCommandHandler
{
    public function __construct(
        #[Autowire(env: 'resolve:APP_SETTINGS_FILE')]
        private readonly string $appSettingsFilename,
    ) {
    }

    public function handle(AppSettingsDTO $settingsDTO): void
    {
        $yamlContent = Yaml::dump($settingsDTO->toArray());

        file_put_contents($this->appSettingsFilename, $yamlContent);
    }
}
