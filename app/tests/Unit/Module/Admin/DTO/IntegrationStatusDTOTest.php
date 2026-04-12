<?php

declare(strict_types=1);

use App\Module\Admin\DTO\IntegrationStatusDTO;

it('returns active status when all env vars are non-empty strings', function (): void {
    $dto = IntegrationStatusDTO::fromEnvVars([
        'SLACK_DSN' => 'slack://token@default',
        'SLACK_SIGNING_SECRET' => 'abc123',
        'SLACK_AR_APPROVE_CHANNEL_ID' => 'C123',
        'SLACK_AR_HR_DIGEST_CHANNEL_ID' => 'C456',
    ]);

    expect($dto->status)->toBe(IntegrationStatusDTO::STATUS_ACTIVE)
        ->and($dto->missingVars)->toBe([]);
});

it('returns incomplete status when some env vars are set and lists missing ones', function (): void {
    $dto = IntegrationStatusDTO::fromEnvVars([
        'SLACK_DSN' => 'slack://token@default',
        'SLACK_SIGNING_SECRET' => '',
        'SLACK_AR_APPROVE_CHANNEL_ID' => 'C123',
        'SLACK_AR_HR_DIGEST_CHANNEL_ID' => '',
    ]);

    expect($dto->status)->toBe(IntegrationStatusDTO::STATUS_INCOMPLETE)
        ->and($dto->missingVars)->toBe(['SLACK_SIGNING_SECRET', 'SLACK_AR_HR_DIGEST_CHANNEL_ID']);
});

it('returns disabled status when no env vars are set', function (): void {
    $dto = IntegrationStatusDTO::fromEnvVars([
        'SLACK_CLIENT_ID' => '',
        'SLACK_CLIENT_SECRET' => '',
        'SLACK_TOKEN_ENCRYPTION_KEY' => '',
    ]);

    expect($dto->status)->toBe(IntegrationStatusDTO::STATUS_DISABLED)
        ->and($dto->missingVars)->toBe(['SLACK_CLIENT_ID', 'SLACK_CLIENT_SECRET', 'SLACK_TOKEN_ENCRYPTION_KEY']);
});

it('treats non-string values as missing', function (): void {
    $dto = IntegrationStatusDTO::fromEnvVars([
        'VAR_A' => 'value',
        'VAR_B' => null,
        'VAR_C' => 123,
    ]);

    expect($dto->status)->toBe(IntegrationStatusDTO::STATUS_INCOMPLETE)
        ->and($dto->missingVars)->toBe(['VAR_B', 'VAR_C']);
});

it('returns active for single var when set', function (): void {
    $dto = IntegrationStatusDTO::fromEnvVars([
        'ICAL_SECRET' => 'some-secret',
    ]);

    expect($dto->status)->toBe(IntegrationStatusDTO::STATUS_ACTIVE)
        ->and($dto->missingVars)->toBe([]);
});

it('returns disabled for single var when empty', function (): void {
    $dto = IntegrationStatusDTO::fromEnvVars([
        'ICAL_SECRET' => '',
    ]);

    expect($dto->status)->toBe(IntegrationStatusDTO::STATUS_DISABLED)
        ->and($dto->missingVars)->toBe(['ICAL_SECRET']);
});
