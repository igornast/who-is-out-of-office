<?php

declare(strict_types=1);

use App\Module\User\UseCase\Command\RemoveProfileImageCommandHandler;

beforeEach(function (): void {
    $this->uploadDirectory = sys_get_temp_dir().'/profile_images_test_'.uniqid();
    mkdir($this->uploadDirectory, 0777, true);

    $this->handler = new RemoveProfileImageCommandHandler(
        uploadDirectory: $this->uploadDirectory,
    );
});

afterEach(function (): void {
    $files = glob($this->uploadDirectory.'/*');
    foreach ($files as $file) {
        unlink($file);
    }
    if (is_dir($this->uploadDirectory)) {
        rmdir($this->uploadDirectory);
    }
});

it('does nothing when current profile image url is null', function () {
    $this->handler->handle(null);

    expect(glob($this->uploadDirectory.'/*'))->toBeEmpty();
});

it('deletes local file from disk', function () {
    $filePath = sprintf('%s/avatar.jpg', $this->uploadDirectory);
    file_put_contents($filePath, 'image-content');

    $this->handler->handle('avatar.jpg');

    expect(file_exists($filePath))->toBeFalse();
});

it('handles missing file gracefully', function () {
    $this->handler->handle('nonexistent.jpg');

    expect(glob($this->uploadDirectory.'/*'))->toBeEmpty();
});

it('does not attempt to delete external url images', function () {
    $localFile = sprintf('%s/avatar.jpg', $this->uploadDirectory);
    file_put_contents($localFile, 'content');

    $this->handler->handle('https://example.com/avatar.jpg');

    expect(file_exists($localFile))->toBeTrue();
});

it('does not attempt to delete http url images', function () {
    $localFile = sprintf('%s/avatar.jpg', $this->uploadDirectory);
    file_put_contents($localFile, 'content');

    $this->handler->handle('http://example.com/avatar.jpg');

    expect(file_exists($localFile))->toBeTrue();
});

it('prevents path traversal by using basename', function () {
    $filePath = sprintf('%s/safe.jpg', $this->uploadDirectory);
    file_put_contents($filePath, 'content');

    $this->handler->handle('../../etc/safe.jpg');

    expect(file_exists($filePath))->toBeFalse();
});
