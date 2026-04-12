<?php

declare(strict_types=1);

use App\Module\Admin\DTO\AppearanceSettingsDTO;
use App\Module\Admin\Form\AppearanceSettingsFormType;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

it('configures data_class as AppearanceSettingsDTO with admin translation domain', function (): void {
    $formType = new AppearanceSettingsFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);

    $resolved = $resolver->resolve();

    expect($resolved['data_class'])->toBe(AppearanceSettingsDTO::class)
        ->and($resolved['translation_domain'])->toBe('admin');
});

it('adds theme and palette fields as expanded EnumType', function (): void {
    $fields = [];

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function (string $child, string $type, array $options = []) use (&$fields, $builder) {
            $fields[$child] = ['type' => $type, 'options' => $options];

            return $builder;
        }
    );

    $formType = new AppearanceSettingsFormType();
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($fields)->toHaveCount(2)
        ->and($fields['theme']['type'])->toBe(EnumType::class)
        ->and($fields['theme']['options']['class'])->toBe(ThemeEnum::class)
        ->and($fields['theme']['options']['expanded'])->toBeTrue()
        ->and($fields['palette']['type'])->toBe(EnumType::class)
        ->and($fields['palette']['options']['class'])->toBe(PaletteEnum::class)
        ->and($fields['palette']['options']['expanded'])->toBeTrue();
});
