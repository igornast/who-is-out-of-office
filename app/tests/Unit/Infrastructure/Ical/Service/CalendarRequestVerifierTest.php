<?php

declare(strict_types=1);

use App\Infrastructure\Ical\Service\CalendarRequestVerifier;
use App\Shared\Service\Ical\IcalHashGenerator;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->icalSecret = 'test-ical-secret';

    $this->verifier = new CalendarRequestVerifier(icalSecret: $this->icalSecret);
});

it('returns true for valid secret', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:00:00+00:00'),
    ]);

    $validHash = IcalHashGenerator::generateForUser($userDTO, $this->icalSecret);

    expect($this->verifier->isValid($userDTO, $validHash))->toBeTrue();
});

it('returns false for invalid secret', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:00:00+00:00'),
    ]);

    expect($this->verifier->isValid($userDTO, 'invalid-hash'))->toBeFalse();
});

it('returns false when hash is generated with different secret', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:00:00+00:00'),
    ]);

    $hashWithDifferentSecret = IcalHashGenerator::generateForUser($userDTO, 'different-secret');

    expect($this->verifier->isValid($userDTO, $hashWithDifferentSecret))->toBeFalse();
});

it('returns false for different user with same hash', function () {
    $user1 = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:00:00+00:00'),
    ]);
    $user2 = UserDTOFixture::create([
        'id' => '456',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:00:00+00:00'),
    ]);

    $hashForUser1 = IcalHashGenerator::generateForUser($user1, $this->icalSecret);

    expect($this->verifier->isValid($user2, $hashForUser1))->toBeFalse();
});
