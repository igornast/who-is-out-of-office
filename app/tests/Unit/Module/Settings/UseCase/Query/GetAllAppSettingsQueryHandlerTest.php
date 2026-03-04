<?php

declare(strict_types=1);

use App\Module\Settings\UseCase\Query\GetAllAppSettingsQueryHandler;
use App\Shared\DTO\Settings\AppSettingsDTO;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'app_settings_test_');

    $this->handler = new GetAllAppSettingsQueryHandler(appSettingsFilename: $this->tempFile);
});

afterEach(function (): void {
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

it('returns app settings dto from yaml file', function () {
    file_put_contents($this->tempFile, Yaml::dump([
        'leave_request' => [
            'auto_approve' => true,
            'auto_approve_delay' => 300,
            'default_annual_allowance' => 25,
            'min_notice_days' => 1,
            'max_consecutive_days' => 0,
        ],
    ]));

    $result = $this->handler->handle();

    expect($result)->toBeInstanceOf(AppSettingsDTO::class)
        ->and($result->autoApprove)->toBeTrue()
        ->and($result->autoApproveDelay)->toBe(300)
        ->and($result->defaultAnnualAllowance)->toBe(25)
        ->and($result->minNoticeDays)->toBe(1)
        ->and($result->maxConsecutiveDays)->toBe(0);
});

it('reads settings with auto approve disabled', function () {
    file_put_contents($this->tempFile, Yaml::dump([
        'leave_request' => [
            'auto_approve' => false,
            'auto_approve_delay' => 0,
            'default_annual_allowance' => 20,
            'min_notice_days' => 3,
            'max_consecutive_days' => 10,
        ],
    ]));

    $result = $this->handler->handle();

    expect($result->autoApprove)->toBeFalse()
        ->and($result->autoApproveDelay)->toBe(0)
        ->and($result->defaultAnnualAllowance)->toBe(20)
        ->and($result->minNoticeDays)->toBe(3)
        ->and($result->maxConsecutiveDays)->toBe(10);
});
