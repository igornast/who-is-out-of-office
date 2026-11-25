<?php

declare(strict_types=1);

use App\Module\Settings\UseCase\Command\UpdateAppSettingsValueCommandHandler;
use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Exception\AppSettingsWriteException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

function fileSafetySettings(string $organizationName): AppSettingsDTO
{
    return new AppSettingsDTO(
        autoApprove: false,
        autoApproveDelay: 0,
        defaultAnnualAllowance: 20,
        minNoticeDays: 0,
        maxConsecutiveDays: 0,
        skipWeekendHolidays: false,
        slackStatusSyncEnabled: false,
        organizationName: $organizationName,
    );
}

function fileSafetyDirectory(): string
{
    $directory = sprintf('%s/app_settings_file_safety_%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
    mkdir($directory);

    return $directory;
}

it('keeps a restrictive file mode through the atomic replace', function (): void {
    $directory = fileSafetyDirectory();
    $file = sprintf('%s/app_setting.yaml', $directory);
    file_put_contents($file, "organization:\n    name: Old\n");
    chmod($file, 0600);

    try {
        new UpdateAppSettingsValueCommandHandler(appSettingsFilename: $file, filesystem: new Filesystem())
            ->handle(fileSafetySettings('New'));
        clearstatcache();

        expect(fileperms($file) & 0777)->toBe(0600)
            ->and(Yaml::parseFile($file)['organization']['name'])->toBe('New');
    } finally {
        new Filesystem()->remove($directory);
    }
});

it('leaves no temporary file behind when the rename onto the settings path fails', function (): void {
    $directory = fileSafetyDirectory();
    $target = sprintf('%s/app_setting.yaml', $directory);
    mkdir($target);
    file_put_contents(sprintf('%s/keep.txt', $target), 'keep');

    try {
        $handler = new UpdateAppSettingsValueCommandHandler(appSettingsFilename: $target, filesystem: new Filesystem());

        expect(static fn () => $handler->handle(fileSafetySettings('New')))
            ->toThrow(AppSettingsWriteException::class)
            ->and(glob(sprintf('%s/*', $directory)))->toBe([$target]);
    } finally {
        new Filesystem()->remove($directory);
    }
});

it('writes through a symlinked settings file and keeps the link', function (): void {
    $directory = fileSafetyDirectory();
    $real = sprintf('%s/real_setting.yaml', $directory);
    $link = sprintf('%s/app_setting.yaml', $directory);
    file_put_contents($real, "organization:\n    name: Old\n");
    symlink($real, $link);

    try {
        new UpdateAppSettingsValueCommandHandler(appSettingsFilename: $link, filesystem: new Filesystem())
            ->handle(fileSafetySettings('Linked'));
        clearstatcache();

        expect(is_link($link))->toBeTrue()
            ->and(Yaml::parseFile($real)['organization']['name'])->toBe('Linked');
    } finally {
        new Filesystem()->remove($directory);
    }
});
