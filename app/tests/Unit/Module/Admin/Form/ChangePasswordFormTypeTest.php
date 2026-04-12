<?php

declare(strict_types=1);

use App\Module\Admin\DTO\ChangePasswordDTO;
use App\Module\Admin\Form\ChangePasswordFormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $key) => $key);
});

it('configures data_class as ChangePasswordDTO with admin translation domain', function (): void {
    $formType = new ChangePasswordFormType($this->translator);
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);

    $resolved = $resolver->resolve();

    expect($resolved['data_class'])->toBe(ChangePasswordDTO::class)
        ->and($resolved['translation_domain'])->toBe('admin');
});

it('adds currentPassword and newPassword fields with correct types', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = ['type' => $type, 'options' => $options];

            return $builder;
        }
    );

    $formType = new ChangePasswordFormType($this->translator);
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields)->toHaveCount(2)
        ->and($fields['currentPassword']['type'])->toBe(PasswordType::class)
        ->and($fields['newPassword']['type'])->toBe(RepeatedType::class)
        ->and($fields['newPassword']['options']['type'])->toBe(PasswordType::class);
});

it('newPassword uses translated invalid_message', function (): void {
    $fields = [];

    $translator = mock(TranslatorInterface::class);
    $translator->allows('trans')->andReturnUsing(fn (string $key) => match ($key) {
        'settings.account_security.error.passwords_must_match' => 'Passwords must match',
        default => $key,
    });

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = $options;

            return $builder;
        }
    );

    $formType = new ChangePasswordFormType($translator);
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields['newPassword']['invalid_message'])->toBe('Passwords must match');
});
