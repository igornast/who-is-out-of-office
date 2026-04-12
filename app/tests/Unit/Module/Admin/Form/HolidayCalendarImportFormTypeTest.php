<?php

declare(strict_types=1);

use App\Module\Admin\DTO\HolidayCalendarImportDTO;
use App\Module\Admin\Form\HolidayCalendarImportFormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

it('configures data_class as HolidayCalendarImportDTO with admin translation domain', function (): void {
    $formType = new HolidayCalendarImportFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);

    $resolved = $resolver->resolve();

    expect($resolved['data_class'])->toBe(HolidayCalendarImportDTO::class)
        ->and($resolved['translation_domain'])->toBe('admin')
        ->and($resolved['country_choices'])->toBe([]);
});

it('adds country and year fields with correct types', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = ['type' => $type, 'options' => $options];

            return $builder;
        }
    );

    $formType = new HolidayCalendarImportFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields)->toHaveCount(2)
        ->and($fields['country']['type'])->toBe(ChoiceType::class)
        ->and($fields['year']['type'])->toBe(IntegerType::class);
});

it('passes country_choices option to country field', function (): void {
    $fields = [];
    $choices = ['Germany' => 'DE', 'Poland' => 'PL'];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = $options;

            return $builder;
        }
    );

    $formType = new HolidayCalendarImportFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve(['country_choices' => $choices]));

    expect($fields['country']['choices'])->toBe($choices)
        ->and($fields['country']['placeholder'])->toBe('—');
});

it('year field has min and max attributes', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = $options;

            return $builder;
        }
    );

    $formType = new HolidayCalendarImportFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields['year']['attr']['min'])->toBe(2000)
        ->and($fields['year']['attr']['max'])->toBe(2100);
});

it('rejects non-array country_choices option', function (): void {
    $formType = new HolidayCalendarImportFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);

    expect(fn () => $resolver->resolve(['country_choices' => 'invalid']))
        ->toThrow(Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
});
