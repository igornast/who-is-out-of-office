<?php

declare(strict_types=1);

namespace App\Tests\_fixtures;

class ProfilePhotoFixture
{
    private static array $paths = [];

    public static function jpegOfSize(int $bytes): string
    {
        $fixture = __DIR__.'/files/profile-photo.jpg';
        $path = self::createPath();

        $fixtureSize = filesize($fixture);
        if (false === $fixtureSize) {
            throw new \RuntimeException('Unable to read the profile photo fixture.');
        }

        if (!copy($fixture, $path)) {
            throw new \RuntimeException('Unable to copy the profile photo fixture.');
        }

        if (false === file_put_contents($path, str_repeat("\0", $bytes - $fixtureSize), FILE_APPEND)) {
            throw new \RuntimeException('Unable to enlarge the temporary profile photo.');
        }

        return $path;
    }

    public static function fromContents(string $contents, int $bytes): string
    {
        $path = self::createPath();

        if (false === file_put_contents($path, str_pad($contents, $bytes, "\0"))) {
            throw new \RuntimeException('Unable to create photo fixture.');
        }

        return $path;
    }

    public static function cleanup(): void
    {
        foreach (self::$paths as $path) {
            if (is_file($path) && !unlink($path)) {
                throw new \RuntimeException('Unable to remove temporary profile photo.');
            }
        }

        self::$paths = [];
    }

    private static function createPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'profile-photo-');

        if (false === $path) {
            throw new \RuntimeException('Unable to create a temporary profile photo.');
        }

        self::$paths[] = $path;

        return $path;
    }
}
