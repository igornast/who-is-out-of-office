<?php

declare(strict_types=1);

use App\Module\User\DTO\PasswordResetDTO;
use App\Module\User\Form\PasswordResetType;
use Symfony\Component\Form\Test\TypeTestCase;

uses(TypeTestCase::class);

it('submits matching passwords and populates DTO', function () {
    $dto = new PasswordResetDTO();
    $form = $this->factory->create(PasswordResetType::class, $dto);

    $form->submit(['password' => ['first' => 'securepass', 'second' => 'securepass']]);

    expect($form->isSynchronized())->toBeTrue()
        ->and($dto->password)->toBe('securepass');
});

it('fails when passwords do not match', function () {
    $form = $this->factory->create(PasswordResetType::class);

    $form->submit(['password' => ['first' => 'securepass', 'second' => 'different']]);

    expect($form->isValid())->toBeFalse();
});

it('has password field', function () {
    $form = $this->factory->create(PasswordResetType::class);

    expect($form->has('password'))->toBeTrue();
});

it('sets correct data class', function () {
    $form = $this->factory->create(PasswordResetType::class);

    expect($form->getConfig()->getOption('data_class'))->toBe(PasswordResetDTO::class);
});
