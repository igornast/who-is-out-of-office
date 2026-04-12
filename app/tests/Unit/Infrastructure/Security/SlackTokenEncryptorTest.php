<?php

declare(strict_types=1);

use App\Infrastructure\Security\SlackTokenEncryptor;
use App\Module\Settings\Exception\AppSettingsDisabledException;
use App\Shared\Facade\AppSettingsFacadeInterface;

beforeEach(function (): void {
    $this->key = base64_encode(sodium_crypto_secretbox_keygen());
    $this->settingsFacade = mock(AppSettingsFacadeInterface::class);
    $this->settingsFacade->allows('isSlackStatusSyncEnabled')->andReturn(true)->byDefault();

    $this->encryptor = new SlackTokenEncryptor($this->key, $this->settingsFacade);
});

it('round-trips a plain token', function (): void {
    $plain = 'xoxp-1234-5678-abcdef';

    $encrypted = $this->encryptor->encrypt($plain);

    expect($encrypted)->not->toBe($plain)
        ->and($this->encryptor->decrypt($encrypted))->toBe($plain);
});

it('produces different ciphertext for the same plaintext (random nonce)', function (): void {
    $plain = 'xoxp-same-input';

    $a = $this->encryptor->encrypt($plain);
    $b = $this->encryptor->encrypt($plain);

    expect($a)->not->toBe($b);
});

it('throws on an invalid base64 key when feature is enabled', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('isSlackStatusSyncEnabled')->andReturn(true);

    new SlackTokenEncryptor('not-base64!!!', $facade);
})->throws(InvalidArgumentException::class);

it('throws on a correctly base64-encoded but wrong-length key when feature is enabled', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('isSlackStatusSyncEnabled')->andReturn(true);

    new SlackTokenEncryptor(base64_encode('too-short'), $facade);
})->throws(InvalidArgumentException::class);

it('throws when decrypting garbage', function (): void {
    $this->encryptor->decrypt(base64_encode('nope'));
})->throws(RuntimeException::class);

it('skips key validation in constructor when feature is disabled', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('isSlackStatusSyncEnabled')->andReturn(false);

    $encryptor = new SlackTokenEncryptor('', $facade);

    expect($encryptor)->toBeInstanceOf(SlackTokenEncryptor::class);
});

it('skips key validation for invalid key when feature is disabled', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('isSlackStatusSyncEnabled')->andReturn(false);

    $encryptor = new SlackTokenEncryptor('not-base64!!!', $facade);

    expect($encryptor)->toBeInstanceOf(SlackTokenEncryptor::class);
});

it('throws AppSettingsDisabledException on encrypt when feature is disabled', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('isSlackStatusSyncEnabled')->andReturn(false);

    $encryptor = new SlackTokenEncryptor('', $facade);
    $encryptor->encrypt('xoxp-test');
})->throws(AppSettingsDisabledException::class);

it('throws AppSettingsDisabledException on decrypt when feature is disabled', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('isSlackStatusSyncEnabled')->andReturn(false);

    $encryptor = new SlackTokenEncryptor('', $facade);
    $encryptor->decrypt('some-encrypted-value');
})->throws(AppSettingsDisabledException::class);
