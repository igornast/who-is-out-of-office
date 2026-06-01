<?php

declare(strict_types=1);

use App\Module\Admin\Service\CalendarAvatarUrlResolver;
use Symfony\Component\Asset\Packages;

beforeEach(function (): void {
    $this->packages = Mockery::mock(Packages::class);
    $this->resolver = new CalendarAvatarUrlResolver($this->packages, 'uploads/profile_images');
});

it('returns null when the raw value is null', function (): void {
    expect($this->resolver->resolve(null))->toBeNull();
});

it('passes through an absolute http(s) url unchanged', function (): void {
    expect($this->resolver->resolve('https://cdn.example/a.png'))->toBe('https://cdn.example/a.png');
    expect($this->resolver->resolve('http://cdn.example/b.png'))->toBe('http://cdn.example/b.png');
});

it('resolves a relative path against the profile images base path via asset packages', function (): void {
    $this->packages->shouldReceive('getUrl')
        ->with('uploads/profile_images/avatar.png')
        ->andReturn('/build/uploads/profile_images/avatar.png');

    expect($this->resolver->resolve('avatar.png'))->toBe('/build/uploads/profile_images/avatar.png');
});
