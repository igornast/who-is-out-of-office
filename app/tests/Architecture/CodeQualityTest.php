<?php

declare(strict_types=1);

describe('Strict Types', function () {
    arch('all PHP files should declare strict types')
        ->expect('App')
        ->toUseStrictTypes();
});

describe('Global State', function () {
    arch('should not use global variables')
        ->expect('App')
        ->not->toUse('global');

    arch('should not use superglobals directly')
        ->expect('App\Module')
        ->not->toUse([
            '$_GET',
            '$_POST',
            '$_REQUEST',
            '$_SERVER',
            '$_SESSION',
            '$_COOKIE',
        ]);
});

describe('Dependency Injection', function () {
    arch('facades should have constructor', function (string $module) {
        $facadeClass = sprintf('App\Module\%s\%sFacade', $module, $module);
        if (!class_exists($facadeClass)) {
            $this->markTestSkipped(sprintf('Module %s has no facade', $module));
        }
        expect($facadeClass)->toHaveConstructor();
    })->with(modules());
});

describe('Framework Decoupling', function () {
    arch('use cases should not depend on Symfony HTTP', function (string $module) {
        expect(sprintf('App\Module\%s\UseCase', $module))
            ->not->toUse([
                'Symfony\Component\HttpFoundation\Request',
                'Symfony\Component\HttpFoundation\Response',
            ]);
    })->with(modules());

    arch('DTOs should not depend on Symfony')
        ->expect('App\Shared\DTO')
        ->not->toUse('Symfony')
        ->ignoring('App\Shared\DTO\DataNager'); // External DTOs may vary

    arch('command handlers should not depend on Doctrine entities', function (string $module) {
        expect(sprintf('App\Module\%s\UseCase\Command', $module))
            ->not->toUse('App\Infrastructure\Doctrine\Entity');
    })->with(modules());
});

describe('Exception Handling', function () {
    arch('should not catch generic Exception')
        ->expect('App\Module')
        ->not->toUse('catch (Exception');

    arch('should not throw generic Exception')
        ->expect('App\Module')
        ->not->toUse('throw new Exception');
});

describe('Debugging Code', function () {
    arch('should not contain var_dump')
        ->expect('App')
        ->not->toUse('var_dump');

    arch('should not contain dd() helper')
        ->expect('App')
        ->not->toUse('dd');

    arch('should not contain dump() in production code')
        ->expect('App')
        ->not->toUse('dump')
        ->ignoring([
            'App\Tests',
        ]);
});

describe('Code Organization', function () {
    arch('interfaces should be abstract')
        ->expect('App\Shared\Facade')
        ->toBeInterfaces();

    arch('facades should be final', function (string $module) {
        $facadeClass = sprintf('App\Module\%s\%sFacade', $module, $module);
        if (!class_exists($facadeClass)) {
            $this->markTestSkipped(sprintf('Module %s has no facade', $module));
        }
        expect($facadeClass)->toBeFinal();
    })->with(modules());
});
