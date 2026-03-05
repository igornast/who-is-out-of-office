<?php

declare(strict_types=1);

use App\Module\Settings\UseCase\Query\GetAppSettingsValueQueryHandler;
use App\Shared\Enum\AppSettingsEnum;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'app_settings_test_');

    file_put_contents($this->tempFile, Yaml::dump([
        'leave_request' => [
            'auto_approve' => true,
            'auto_approve_delay' => 5,
        ],
    ]));

    $this->handler = new GetAppSettingsValueQueryHandler(appSettingsFilename: $this->tempFile);
});

afterEach(function (): void {
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

it('returns auto approve setting value', function () {
    $result = $this->handler->handle(AppSettingsEnum::AUTO_APPROVE);

    expect($result)->toBeTrue();
});

it('returns auto approve delay setting value', function () {
    $result = $this->handler->handle(AppSettingsEnum::AUTO_APPROVE_DELAY);

    expect($result)->toBe(5);
});

it('returns updated value after file change', function () {
    file_put_contents($this->tempFile, Yaml::dump([
        'leave_request' => [
            'auto_approve' => false,
            'auto_approve_delay' => 10,
        ],
    ]));

    $result = $this->handler->handle(AppSettingsEnum::AUTO_APPROVE);

    expect($result)->toBeFalse();
});

it('returns null for missing nested key', function () {
    $result = $this->handler->handle(AppSettingsEnum::SKIP_WEEKEND_HOLIDAYS);

    expect($result)->toBeNull();
});
