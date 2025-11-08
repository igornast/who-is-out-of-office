<?php

declare(strict_types=1);

use App\Shared\Service\Messaging\EmojisProvider;

it('returns correct flag emoji for valid uppercase country code', function () {
    $result = EmojisProvider::getFlagEmojiCode('US');

    expect($result)->toBe('🇺🇸');
});

it('returns correct flag emoji for Nigeria', function () {
    $result = EmojisProvider::getFlagEmojiCode('NG');

    expect($result)->toBe('🇳🇬');
});

it('handles lowercase country codes', function () {
    $result = EmojisProvider::getFlagEmojiCode('us');

    expect($result)->toBe('🇺🇸');
});

it('handles mixed case country codes', function () {
    $result = EmojisProvider::getFlagEmojiCode('Ng');

    expect($result)->toBe('🇳🇬');
});

it('returns white flag for empty string', function () {
    $result = EmojisProvider::getFlagEmojiCode('');

    expect($result)->toBe('🏳');
});

it('returns white flag for single character', function () {
    $result = EmojisProvider::getFlagEmojiCode('A');

    expect($result)->toBe('🏳');
});

it('returns white flag for three characters', function () {
    $result = EmojisProvider::getFlagEmojiCode('USA');

    expect($result)->toBe('🏳');
});

it('returns white flag for country code with numbers', function () {
    $result = EmojisProvider::getFlagEmojiCode('U2');

    expect($result)->toBe('🏳');
});

it('returns white flag for country code with special characters', function () {
    $result = EmojisProvider::getFlagEmojiCode('U$');

    expect($result)->toBe('🏳');
});

it('returns white flag for country code with space', function () {
    $result = EmojisProvider::getFlagEmojiCode('U ');

    expect($result)->toBe('🏳');
});

it('returns white flag for only numbers', function () {
    $result = EmojisProvider::getFlagEmojiCode('12');

    expect($result)->toBe('🏳');
});

it('returns correct flag emoji for various valid country codes', function (string $countryCode, string $expectedEmoji) {
    $result = EmojisProvider::getFlagEmojiCode($countryCode);

    expect($result)->toBe($expectedEmoji);
})->with([
    ['GB', '🇬🇧'],
    ['DE', '🇩🇪'],
    ['FR', '🇫🇷'],
    ['JP', '🇯🇵'],
    ['CA', '🇨🇦'],
    ['AU', '🇦🇺'],
    ['BR', '🇧🇷'],
    ['IN', '🇮🇳'],
]);
