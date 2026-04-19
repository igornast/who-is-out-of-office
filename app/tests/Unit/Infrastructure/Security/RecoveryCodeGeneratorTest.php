<?php

declare(strict_types=1);

use App\Infrastructure\Security\RecoveryCodeGenerator;

it('generates exactly 8 recovery codes', function (): void {
    $generator = new RecoveryCodeGenerator();
    $codes = $generator->generate();

    expect($codes)->toHaveCount(8);
});

it('generates codes in xxxx-xxxx format', function (): void {
    $generator = new RecoveryCodeGenerator();
    $codes = $generator->generate();

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[a-z2-9]{4}-[a-z2-9]{4}$/');
    }
});

it('generates unique codes', function (): void {
    $generator = new RecoveryCodeGenerator();
    $codes = $generator->generate();

    expect(array_unique($codes))->toHaveCount(count($codes));
});

it('excludes ambiguous characters (0, 1, o, l, i)', function (): void {
    $generator = new RecoveryCodeGenerator();

    for ($i = 0; $i < 10; ++$i) {
        $codes = $generator->generate();
        $joined = implode('', $codes);
        expect($joined)->not->toContain('0')
            ->not->toContain('1')
            ->not->toContain('o')
            ->not->toContain('l')
            ->not->toContain('i');
    }
});
