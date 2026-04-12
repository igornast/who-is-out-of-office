<?php

declare(strict_types=1);

use App\Module\Admin\Form\AppSettingsFormType;
use App\Shared\DTO\Settings\AppSettingsDTO;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

it('configures data_class as AppSettingsDTO with admin translation domain', function (): void {
    $formType = new AppSettingsFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);

    $resolved = $resolver->resolve();

    expect($resolved['data_class'])->toBe(AppSettingsDTO::class)
        ->and($resolved['translation_domain'])->toBe('admin');
});

it('adds all expected fields with correct types', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = ['type' => $type, 'options' => $options];

            return $builder;
        }
    );

    $formType = new AppSettingsFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields)->toHaveCount(7)
        ->and($fields['autoApprove']['type'])->toBe(CheckboxType::class)
        ->and($fields['autoApproveDelay']['type'])->toBe(IntegerType::class)
        ->and($fields['defaultAnnualAllowance']['type'])->toBe(IntegerType::class)
        ->and($fields['minNoticeDays']['type'])->toBe(IntegerType::class)
        ->and($fields['maxConsecutiveDays']['type'])->toBe(IntegerType::class)
        ->and($fields['skipWeekendHolidays']['type'])->toBe(CheckboxType::class)
        ->and($fields['slackStatusSyncEnabled']['type'])->toBe(CheckboxType::class);
});

it('checkbox fields are not required', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = $options;

            return $builder;
        }
    );

    $formType = new AppSettingsFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields['autoApprove']['required'])->toBeFalse()
        ->and($fields['skipWeekendHolidays']['required'])->toBeFalse()
        ->and($fields['slackStatusSyncEnabled']['required'])->toBeFalse();
});

it('integer fields are required', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = $options;

            return $builder;
        }
    );

    $formType = new AppSettingsFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields['autoApproveDelay']['required'])->toBeTrue()
        ->and($fields['defaultAnnualAllowance']['required'])->toBeTrue()
        ->and($fields['minNoticeDays']['required'])->toBeTrue()
        ->and($fields['maxConsecutiveDays']['required'])->toBeTrue();
});
