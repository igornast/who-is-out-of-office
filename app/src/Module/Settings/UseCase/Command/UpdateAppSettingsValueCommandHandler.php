<?php

declare(strict_types=1);

namespace App\Module\Settings\UseCase\Command;

use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Exception\AppSettingsWriteException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class UpdateAppSettingsValueCommandHandler
{
    public function __construct(
        #[Autowire(env: 'resolve:APP_SETTINGS_FILE')]
        private readonly string $appSettingsFilename,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function handle(AppSettingsDTO $settingsDTO): void
    {
        $yamlContent = Yaml::dump($settingsDTO->toArray());

        try {
            $this->filesystem->dumpFile($this->appSettingsFilename, $yamlContent);
        } catch (IOException $exception) {
            throw new AppSettingsWriteException($exception->getMessage(), previous: $exception);
        }
    }
}
