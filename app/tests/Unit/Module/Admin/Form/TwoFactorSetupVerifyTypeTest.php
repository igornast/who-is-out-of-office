<?php

declare(strict_types=1);

use App\Module\Admin\DTO\TwoFactorSetupDTO;
use App\Module\Admin\Form\TwoFactorSetupVerifyType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

it('configures data_class as TwoFactorSetupDTO', function (): void {
    $formType = new TwoFactorSetupVerifyType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);

    $resolved = $resolver->resolve();

    expect($resolved['data_class'])->toBe(TwoFactorSetupDTO::class);
});

it('adds currentPassword and verificationCode fields with correct types and attributes', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = ['type' => $type, 'options' => $options];

            return $builder;
        }
    );

    $formType = new TwoFactorSetupVerifyType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields)->toHaveCount(2)
        ->and($fields['currentPassword']['type'])->toBe(PasswordType::class)
        ->and($fields['currentPassword']['options']['translation_domain'])->toBe('admin')
        ->and($fields['currentPassword']['options']['attr']['autocomplete'])->toBe('current-password')
        ->and($fields['verificationCode']['type'])->toBe(TextType::class)
        ->and($fields['verificationCode']['options']['translation_domain'])->toBe('admin')
        ->and($fields['verificationCode']['options']['attr']['autocomplete'])->toBe('one-time-code')
        ->and($fields['verificationCode']['options']['attr']['inputmode'])->toBe('numeric')
        ->and($fields['verificationCode']['options']['attr']['maxlength'])->toBe(6)
        ->and($fields['verificationCode']['options']['attr']['placeholder'])->toBe('000000');
});
