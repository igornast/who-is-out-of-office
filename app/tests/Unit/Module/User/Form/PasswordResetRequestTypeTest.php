<?php

declare(strict_types=1);

use App\Module\User\DTO\PasswordResetRequestDTO;
use App\Module\User\Form\PasswordResetRequestType;
use Symfony\Component\Form\Test\TypeTestCase;

uses(TypeTestCase::class);

it('submits valid email and populates DTO', function () {
    $dto = new PasswordResetRequestDTO();
    $form = $this->factory->create(PasswordResetRequestType::class, $dto);

    $form->submit(['email' => 'user@example.com']);

    expect($form->isSynchronized())->toBeTrue()
        ->and($dto->email)->toBe('user@example.com');
});

it('has email field', function () {
    $form = $this->factory->create(PasswordResetRequestType::class);

    expect($form->has('email'))->toBeTrue();
});

it('sets correct data class', function () {
    $form = $this->factory->create(PasswordResetRequestType::class);

    expect($form->getConfig()->getOption('data_class'))->toBe(PasswordResetRequestDTO::class);
});
