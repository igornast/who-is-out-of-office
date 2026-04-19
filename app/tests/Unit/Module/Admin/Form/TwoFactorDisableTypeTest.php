<?php

declare(strict_types=1);

use App\Module\Admin\DTO\TwoFactorDisableDTO;
use App\Module\Admin\Form\TwoFactorDisableType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

it('configures data_class as TwoFactorDisableDTO', function (): void {
    $formType = new TwoFactorDisableType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);

    $resolved = $resolver->resolve();

    expect($resolved['data_class'])->toBe(TwoFactorDisableDTO::class);
});

it('adds password and totpCode fields with correct types and attributes', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = ['type' => $type, 'options' => $options];

            return $builder;
        }
    );

    $formType = new TwoFactorDisableType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields)->toHaveCount(2)
        ->and($fields['password']['type'])->toBe(PasswordType::class)
        ->and($fields['password']['options']['translation_domain'])->toBe('admin')
        ->and($fields['password']['options']['attr']['autocomplete'])->toBe('current-password')
        ->and($fields['totpCode']['type'])->toBe(TextType::class)
        ->and($fields['totpCode']['options']['translation_domain'])->toBe('admin')
        ->and($fields['totpCode']['options']['attr']['autocomplete'])->toBe('one-time-code')
        ->and($fields['totpCode']['options']['attr']['inputmode'])->toBe('numeric')
        ->and($fields['totpCode']['options']['attr']['maxlength'])->toBe(6);
});
