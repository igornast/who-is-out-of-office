<?php

declare(strict_types=1);

use App\Infrastructure\Security\TotpSecretEncryptor;

it('encrypts and decrypts a TOTP secret round-trip', function (): void {
    $key = sodium_crypto_secretbox_keygen();
    $encryptor = new TotpSecretEncryptor(base64_encode($key));

    $plainSecret = 'JBSWY3DPEHPK3PXP';
    $encrypted = $encryptor->encrypt($plainSecret);

    expect($encrypted)->not->toBe($plainSecret)
        ->and($encryptor->decrypt($encrypted))->toBe($plainSecret);
});

it('produces different ciphertext for the same plaintext (random nonce)', function (): void {
    $key = sodium_crypto_secretbox_keygen();
    $encryptor = new TotpSecretEncryptor(base64_encode($key));

    $plainSecret = 'JBSWY3DPEHPK3PXP';
    $encrypted1 = $encryptor->encrypt($plainSecret);
    $encrypted2 = $encryptor->encrypt($plainSecret);

    expect($encrypted1)->not->toBe($encrypted2);
});

it('throws on decrypt with wrong key', function (): void {
    $key1 = sodium_crypto_secretbox_keygen();
    $key2 = sodium_crypto_secretbox_keygen();

    $encryptor1 = new TotpSecretEncryptor(base64_encode($key1));
    $encryptor2 = new TotpSecretEncryptor(base64_encode($key2));

    $encrypted = $encryptor1->encrypt('JBSWY3DPEHPK3PXP');
    $encryptor2->decrypt($encrypted);
})->throws(RuntimeException::class, 'Failed to decrypt TOTP secret');

it('throws on decrypt with corrupted data', function (): void {
    $key = sodium_crypto_secretbox_keygen();
    $encryptor = new TotpSecretEncryptor(base64_encode($key));

    $encryptor->decrypt('not-valid-base64-ciphertext!!!');
})->throws(RuntimeException::class);

it('throws when the encryption key is not valid base64', function (): void {
    new TotpSecretEncryptor('@@@not-base64@@@');
})->throws(InvalidArgumentException::class, 'Invalid TOTP encryption key');

it('throws when the encryption key does not decode to 32 bytes', function (): void {
    new TotpSecretEncryptor(base64_encode('too-short-key'));
})->throws(InvalidArgumentException::class, 'Invalid TOTP encryption key');

it('throws on decrypt when ciphertext is shorter than the nonce', function (): void {
    $key = sodium_crypto_secretbox_keygen();
    $encryptor = new TotpSecretEncryptor(base64_encode($key));

    $encryptor->decrypt(base64_encode('short'));
})->throws(RuntimeException::class, 'invalid ciphertext');
