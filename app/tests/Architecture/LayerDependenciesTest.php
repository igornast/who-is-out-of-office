<?php

declare(strict_types=1);

describe('Shared Layer', function () {
    arch('Shared Enums should be pure')
        ->expect('App\Shared\Enum')
        ->toBeEnums();

    arch('Shared Facade interfaces should not have implementations in Shared')
        ->expect('App\Shared\Facade')
        ->toBeInterfaces();

    arch('Shared services should not depend on specific modules')
        ->expect('App\Shared\Service')
        ->not->toUse('App\Module');
});

arch('Use case handlers should not depend on Symfony framework', function (string $module) {
    expect(sprintf('App\Module\%s\UseCase', $module))
        ->not->toUse([
            'Symfony\Component\HttpFoundation',
            'Symfony\Bundle\FrameworkBundle',
        ]);
})->with(modules());
