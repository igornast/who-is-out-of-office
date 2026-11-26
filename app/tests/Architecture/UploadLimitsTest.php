<?php

declare(strict_types=1);

use App\Module\Admin\DTO\UserProfileDTO;
use Symfony\Component\Validator\Constraints as Assert;

function uploadLimitsDockerFile(string $relative): string
{
    $path = dirname(__DIR__, 2).'/../docker/'.$relative;

    if (!is_file($path)) {
        throw new RuntimeException(sprintf('%s not found — in the dev container, recreate php so ./docker is mounted at /var/docker', $path));
    }

    return $path;
}

function uploadLimitsIniBytes(string $relative, string $key): int
{
    $path = uploadLimitsDockerFile($relative);
    $values = parse_ini_file($path, false, INI_SCANNER_RAW);

    if (false === $values || !array_key_exists($key, $values)) {
        throw new RuntimeException(sprintf('%s does not define %s', $path, $key));
    }

    return ini_parse_quantity((string) $values[$key]);
}

function uploadLimitsDtoMaxBytes(): int
{
    $constraint = new ReflectionProperty(UserProfileDTO::class, 'profileImageFile')
        ->getAttributes(Assert\Image::class)[0]
        ->newInstance();

    return $constraint->maxSize;
}

function uploadLimitsNginxClientMaxBodyBytes(): int
{
    $contents = file_get_contents(uploadLimitsDockerFile('nginx/tmpl.nginx.conf'));

    if (false === $contents || 1 !== preg_match('/^\s*client_max_body_size\s+(\d+)([kKmMgG]?)\s*;/m', $contents, $matches)) {
        throw new RuntimeException('client_max_body_size is missing from the nginx template.');
    }

    $multipliers = ['' => 1, 'k' => 1024, 'm' => 1024 ** 2, 'g' => 1024 ** 3];

    return (int) $matches[1] * $multipliers[strtolower($matches[2])];
}

function uploadLimitsDockerfileProdStage(): string
{
    $contents = file_get_contents(uploadLimitsDockerFile('php/Dockerfile'));

    if (false === $contents || 1 !== preg_match('/^FROM app AS prod\s*\n(.*?)(?=^FROM |\z)/ms', $contents, $matches)) {
        throw new RuntimeException('The prod stage is missing from docker/php/Dockerfile.');
    }

    return $matches[1];
}

it('lets PHP accept every file the DTO accepts', function (): void {
    expect(uploadLimitsIniBytes('php/conf/php.prod.ini', 'upload_max_filesize'))->toBeGreaterThanOrEqual(uploadLimitsDtoMaxBytes());
});

it('leaves room in post_max_size for the other form fields', function (): void {
    expect(uploadLimitsIniBytes('php/conf/php.prod.ini', 'post_max_size'))->toBeGreaterThan(uploadLimitsIniBytes('php/conf/php.prod.ini', 'upload_max_filesize'));
});

it('lets oversized requests reach Symfony instead of an nginx 413', function (): void {
    expect(uploadLimitsNginxClientMaxBodyBytes())->toBeGreaterThan(uploadLimitsIniBytes('php/conf/php.prod.ini', 'post_max_size'));
});

it('keeps the dev upload limits equal to production', function (string $key): void {
    expect(uploadLimitsIniBytes('php/conf/php.local.ini', $key))->toBe(uploadLimitsIniBytes('php/conf/php.prod.ini', $key));
})->with(['upload_max_filesize', 'post_max_size']);

it('copies the production ini into the prod image', function (): void {
    expect(uploadLimitsDockerfileProdStage())->toMatch('#^COPY docker/php/conf/php\.prod\.ini /usr/local/etc/php/conf\.d/zz-app\.ini$#m');
});
