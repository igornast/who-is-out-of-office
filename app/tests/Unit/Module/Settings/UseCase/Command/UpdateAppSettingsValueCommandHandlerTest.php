<?php

declare(strict_types=1);

use App\Module\Settings\UseCase\Command\UpdateAppSettingsValueCommandHandler;
use App\Shared\DTO\Settings\AppSettingsDTO;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'app_settings_test_');

    $this->handler = new UpdateAppSettingsValueCommandHandler(appSettingsFilename: $this->tempFile);
});

afterEach(function (): void {
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

it('writes settings to yaml file', function () {
    $settingsDTO = new AppSettingsDTO(
        autoApprove: true,
        autoApproveDelay: 300,
    );

    $this->handler->handle($settingsDTO);

    $content = Yaml::parseFile($this->tempFile);

    expect($content['leave_request']['auto_approve'])->toBeTrue()
        ->and($content['leave_request']['auto_approve_delay'])->toBe(300);
});

it('overwrites existing settings file', function () {
    file_put_contents($this->tempFile, Yaml::dump(['leave_request' => ['auto_approve' => false, 'auto_approve_delay' => 0]]));

    $settingsDTO = new AppSettingsDTO(
        autoApprove: true,
        autoApproveDelay: 600,
    );

    $this->handler->handle($settingsDTO);

    $content = Yaml::parseFile($this->tempFile);

    expect($content['leave_request']['auto_approve'])->toBeTrue()
        ->and($content['leave_request']['auto_approve_delay'])->toBe(600);
});
