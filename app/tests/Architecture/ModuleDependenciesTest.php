<?php

declare(strict_types=1);

describe('Module Independence', function () {
    arch('Admin module should not depend on other modules directly')
        ->expect('App\Module\Admin')
        ->not->toUse([
            'App\Module\Holiday',
            'App\Module\LeaveRequest',
        ]);

    arch('User module should not depend on other modules directly')
        ->expect('App\Module\User')
        ->not->toUse([
            'App\Module\Admin',
            'App\Module\Holiday',
            'App\Module\LeaveRequest',
        ]);

    arch('LeaveRequest module should not depend on other modules directly')
        ->expect('App\Module\LeaveRequest')
        ->not->toUse([
            'App\Module\Admin',
            'App\Module\User',
            'App\Module\Holiday',
        ]);

    arch('Holiday module should not depend on other modules directly')
        ->expect('App\Module\Holiday')
        ->not->toUse([
            'App\Module\Admin',
            'App\Module\User',
            'App\Module\LeaveRequest',
        ]);
});

arch('modules should not use Doctrine entities from Infrastructure in business logic', function (string $module) {
    expect(sprintf('App\Module\%s\UseCase', $module))
        ->not->toUse('App\Infrastructure\Doctrine\Entity');
})->with(modules());


describe('Facade Pattern Enforcement', function () {
    arch('facades should implement facade interfaces')
        ->expect('App\Module\LeaveRequest\LeaveRequestFacade')
        ->toImplement('App\Shared\Facade\LeaveRequestFacadeInterface');

    arch('facades should be final')
        ->expect('App\Module\LeaveRequest\LeaveRequestFacade')
        ->toBeFinal();
});
