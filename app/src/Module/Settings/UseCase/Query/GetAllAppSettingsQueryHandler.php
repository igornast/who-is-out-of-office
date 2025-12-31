<?php

declare(strict_types=1);

namespace App\Module\Settings\UseCase\Query;

use App\Shared\DTO\Settings\AppSettingsDTO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

class GetAllAppSettingsQueryHandler
{
    public function __construct(
        #[Autowire(env: 'resolve:APP_SETTINGS_FILE')]
        private readonly string $appSettingsFilename,
    ) {
    }

    public function handle(): AppSettingsDTO
    {
        $content = Yaml::parseFile($this->appSettingsFilename);

        return AppSettingsDTO::fromArray($content);
    }
}
