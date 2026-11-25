<?php

declare(strict_types=1);

use App\Module\Settings\UseCase\Command\UpdateAppSettingsValueCommandHandler;
use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Exception\AppSettingsWriteException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'app_settings_test_');

    $this->handler = new UpdateAppSettingsValueCommandHandler(appSettingsFilename: $this->tempFile, filesystem: new Filesystem());
});

afterEach(function (): void {
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

it('writes settings to yaml file', function () {
    $settingsDTO = new AppSettingsDTO(
        autoApprove: true,
        autoApproveDelay: 5,
        defaultAnnualAllowance: 25,
        minNoticeDays: 1,
        maxConsecutiveDays: 0,
        skipWeekendHolidays: false,
        slackStatusSyncEnabled: false,
        organizationName: 'Acme Inc.',
    );

    $this->handler->handle($settingsDTO);

    $content = Yaml::parseFile($this->tempFile);

    expect($content['leave_request']['auto_approve'])->toBeTrue()
        ->and($content['leave_request']['auto_approve_delay'])->toBe(5)
        ->and($content['leave_request']['default_annual_allowance'])->toBe(25)
        ->and($content['leave_request']['min_notice_days'])->toBe(1)
        ->and($content['leave_request']['max_consecutive_days'])->toBe(0)
        ->and($content['notification']['skip_weekend_holidays'])->toBeFalse()
        ->and($content['slack']['status_sync_enabled'])->toBeFalse()
        ->and($content['organization']['name'])->toBe('Acme Inc.');
});

it('overwrites existing settings file', function () {
    file_put_contents($this->tempFile, Yaml::dump(['leave_request' => [
        'auto_approve' => false,
        'auto_approve_delay' => 0,
        'default_annual_allowance' => 20,
        'min_notice_days' => 0,
        'max_consecutive_days' => 5,
    ]]));

    $settingsDTO = new AppSettingsDTO(
        autoApprove: true,
        autoApproveDelay: 10,
        defaultAnnualAllowance: 30,
        minNoticeDays: 2,
        maxConsecutiveDays: 10,
        skipWeekendHolidays: true,
        slackStatusSyncEnabled: true,
        organizationName: 'Acme Inc.',
    );

    $this->handler->handle($settingsDTO);

    $content = Yaml::parseFile($this->tempFile);

    expect($content['leave_request']['auto_approve'])->toBeTrue()
        ->and($content['leave_request']['auto_approve_delay'])->toBe(10)
        ->and($content['leave_request']['default_annual_allowance'])->toBe(30)
        ->and($content['leave_request']['min_notice_days'])->toBe(2)
        ->and($content['leave_request']['max_consecutive_days'])->toBe(10)
        ->and($content['notification']['skip_weekend_holidays'])->toBeTrue()
        ->and($content['slack']['status_sync_enabled'])->toBeTrue()
        ->and($content['organization']['name'])->toBe('Acme Inc.');
});

it('wraps a settings write failure', function (): void {
    $directory = sprintf('%s/app_settings_write_failure_%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
    mkdir($directory);
    $handler = new UpdateAppSettingsValueCommandHandler(appSettingsFilename: $directory, filesystem: new Filesystem());
    $exception = null;

    try {
        $handler->handle(new AppSettingsDTO(
            autoApprove: false,
            autoApproveDelay: 0,
            defaultAnnualAllowance: 20,
            minNoticeDays: 0,
            maxConsecutiveDays: 0,
            skipWeekendHolidays: false,
            slackStatusSyncEnabled: false,
            organizationName: 'New',
        ));
    } catch (AppSettingsWriteException $caughtException) {
        $exception = $caughtException;
    } finally {
        new Filesystem()->remove($directory);
    }

    expect($exception)->toBeInstanceOf(AppSettingsWriteException::class)
        ->and($exception?->getPrevious())->toBeInstanceOf(IOException::class);
});

it('replaces the settings file through a rename and keeps its permissions', function (): void {
    $dir = sprintf('%s/app_settings_atomic_%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
    mkdir($dir);
    $file = sprintf('%s/app_setting.yaml', $dir);
    file_put_contents($file, "organization:\n    name: Old\n");
    chmod($file, 0644);
    $inodeBefore = fileinode($file);

    try {
        new UpdateAppSettingsValueCommandHandler(appSettingsFilename: $file, filesystem: new Filesystem())
            ->handle(new AppSettingsDTO(
                autoApprove: false,
                autoApproveDelay: 0,
                defaultAnnualAllowance: 20,
                minNoticeDays: 0,
                maxConsecutiveDays: 0,
                skipWeekendHolidays: false,
                slackStatusSyncEnabled: false,
                organizationName: 'New',
            ));
        clearstatcache();

        expect(fileinode($file))->not->toBe($inodeBefore)
            ->and(fileperms($file) & 0777)->toBe(0644)
            ->and(Yaml::parseFile($file)['organization']['name'])->toBe('New')
            ->and(glob(sprintf('%s/*', $dir)))->toBe([$file]);
    } finally {
        new Filesystem()->remove($dir);
    }
});
