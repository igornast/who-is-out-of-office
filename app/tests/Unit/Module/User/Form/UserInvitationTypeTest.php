<?php

declare(strict_types=1);

use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Module\User\Form\UserInvitationType;
use Symfony\Component\Form\Test\TypeTestCase;

uses(TypeTestCase::class);

it('submits valid data and populates DTO', function () {
    $dto = new UserInvitationRequestDTO(firstName: '', lastName: '', password: '');
    $form = $this->factory->create(UserInvitationType::class, $dto);

    $form->submit([
        'firstName' => 'John',
        'lastName' => 'Doe',
        'birthdate' => '1990-05-15',
        'password' => ['first' => 'securepass', 'second' => 'securepass'],
    ]);

    expect($form->isSynchronized())->toBeTrue()
        ->and($dto->firstName)->toBe('John')
        ->and($dto->lastName)->toBe('Doe')
        ->and($dto->password)->toBe('securepass')
        ->and($dto->birthdate)->toBeInstanceOf(DateTimeImmutable::class);
});

it('submits without optional birthdate', function () {
    $dto = new UserInvitationRequestDTO(firstName: '', lastName: '', password: '');
    $form = $this->factory->create(UserInvitationType::class, $dto);

    $form->submit([
        'firstName' => 'Jane',
        'lastName' => 'Smith',
        'password' => ['first' => 'securepass', 'second' => 'securepass'],
    ]);

    expect($form->isSynchronized())->toBeTrue()
        ->and($dto->firstName)->toBe('Jane')
        ->and($dto->birthdate)->toBeNull();
});

it('fails when passwords do not match', function () {
    $dto = new UserInvitationRequestDTO(firstName: '', lastName: '', password: '');
    $form = $this->factory->create(UserInvitationType::class, $dto);

    $form->submit([
        'firstName' => 'John',
        'lastName' => 'Doe',
        'password' => ['first' => 'securepass', 'second' => 'different'],
    ]);

    expect($form->isValid())->toBeFalse();
});

it('has all expected fields', function () {
    $form = $this->factory->create(UserInvitationType::class);

    expect($form->has('firstName'))->toBeTrue()
        ->and($form->has('lastName'))->toBeTrue()
        ->and($form->has('birthdate'))->toBeTrue()
        ->and($form->has('password'))->toBeTrue();
});

it('sets correct data class', function () {
    $form = $this->factory->create(UserInvitationType::class);

    expect($form->getConfig()->getOption('data_class'))->toBe(UserInvitationRequestDTO::class);
});
