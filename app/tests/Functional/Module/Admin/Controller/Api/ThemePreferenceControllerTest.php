<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get('doctrine')->getManager();

    $this->user = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
});

it('persists a valid theme and palette and returns status ok', function (): void {
    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/theme',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['theme' => ThemeEnum::Dark->value, 'palette' => PaletteEnum::Sage->value]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and(json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR))->toBe(['status' => 'ok']);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($refreshed->themePreference)->toBe(ThemeEnum::Dark->value)
        ->and($refreshed->palettePreference)->toBe(PaletteEnum::Sage->value);
});

it('accepts every theme and palette case the enums define', function (string $theme, string $palette): void {
    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/theme',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['theme' => $theme, 'palette' => $palette]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($refreshed->themePreference)->toBe($theme)
        ->and($refreshed->palettePreference)->toBe($palette);
})->with(function (): array {
    $combinations = [];

    foreach (ThemeEnum::cases() as $theme) {
        foreach (PaletteEnum::cases() as $palette) {
            $combinations[sprintf('%s with %s', $theme->value, $palette->value)] = [$theme->value, $palette->value];
        }
    }

    return $combinations;
});

it('falls back to auto and teal when the body is an empty JSON object', function (): void {
    $this->user->themePreference = ThemeEnum::Dark->value;
    $this->user->palettePreference = PaletteEnum::Sage->value;
    $this->em->flush();

    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/theme',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode([]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($refreshed->themePreference)->toBe(ThemeEnum::Auto->value)
        ->and($refreshed->palettePreference)->toBe(PaletteEnum::Teal->value);
});

it('rejects an unknown theme with 422 and keeps the stored preference', function (): void {
    $this->user->themePreference = ThemeEnum::Light->value;
    $this->user->palettePreference = PaletteEnum::Teal->value;
    $this->em->flush();

    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/theme',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['theme' => 'nope', 'palette' => PaletteEnum::Sage->value]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(422);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($refreshed->themePreference)->toBe(ThemeEnum::Light->value)
        ->and($refreshed->palettePreference)->toBe(PaletteEnum::Teal->value);
});

it('rejects an unknown palette with 422 and keeps the stored preference', function (): void {
    $this->user->themePreference = ThemeEnum::Light->value;
    $this->user->palettePreference = PaletteEnum::Teal->value;
    $this->em->flush();

    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/theme',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['theme' => ThemeEnum::Dark->value, 'palette' => 'neon']),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(422);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($refreshed->themePreference)->toBe(ThemeEnum::Light->value)
        ->and($refreshed->palettePreference)->toBe(PaletteEnum::Teal->value);
});

it('rejects a non-string theme with 422 and keeps the stored preference', function (): void {
    $this->user->themePreference = ThemeEnum::Light->value;
    $this->user->palettePreference = PaletteEnum::Teal->value;
    $this->em->flush();

    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/theme',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['theme' => 1, 'palette' => PaletteEnum::Sage->value]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(422);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($refreshed->themePreference)->toBe(ThemeEnum::Light->value)
        ->and($refreshed->palettePreference)->toBe(PaletteEnum::Teal->value);
});

it('rejects an empty request body without a 500 and keeps the stored preference', function (): void {
    $this->user->themePreference = ThemeEnum::Light->value;
    $this->user->palettePreference = PaletteEnum::Teal->value;
    $this->em->flush();

    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/theme',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        '',
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(400);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($refreshed->themePreference)->toBe(ThemeEnum::Light->value)
        ->and($refreshed->palettePreference)->toBe(PaletteEnum::Teal->value);
});

it('does not reach the controller for an unauthenticated request and redirects to login', function (): void {
    $this->user->themePreference = ThemeEnum::Light->value;
    $this->user->palettePreference = PaletteEnum::Teal->value;
    $this->em->flush();

    $this->client->request(
        'POST',
        '/app/api/user/theme',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['theme' => ThemeEnum::Dark->value, 'palette' => PaletteEnum::Sage->value]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(302)
        ->and($this->client->getResponse()->headers->get('Location'))->toEndWith('/login');

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($refreshed->themePreference)->toBe(ThemeEnum::Light->value)
        ->and($refreshed->palettePreference)->toBe(PaletteEnum::Teal->value);
});
