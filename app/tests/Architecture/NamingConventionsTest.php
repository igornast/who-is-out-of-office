<?php

declare(strict_types=1);

arch('Controller Naming', function (string $module) {
    expect(sprintf('App\Module\%s\Controller', $module))
    ->toHaveSuffix('Controller');
})->with(modules());

arch('UseCase Naming', function (string $module) {
    expect(sprintf('App\Module\%s\UseCase\Command', $module))
        ->toHaveSuffix('CommandHandler')
        ->and(sprintf('App\Module\%s\UseCase\Query', $module))
        ->toHaveSuffix('QueryHandler');

})->with(modules());

arch('Repository Naming', function () {
    expect('App\Infrastructure\Doctrine\Repository')
    ->toHaveSuffix('Repository');
});

arch('Shared DTO Naming', function () {
    expect('App\Shared\DTO')
    ->toHaveSuffix('DTO');
});
