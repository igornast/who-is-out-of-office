<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppearanceSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('theme', EnumType::class, [
                'class' => ThemeEnum::class,
                'label' => 'settings.appearance.field.theme',
                'choice_label' => fn (ThemeEnum $theme) => sprintf('settings.appearance.theme.%s', $theme->value),
                'expanded' => true,
            ])
            ->add('palette', EnumType::class, [
                'class' => PaletteEnum::class,
                'label' => 'settings.appearance.field.palette',
                'choice_label' => fn (PaletteEnum $palette) => sprintf('settings.appearance.palette.%s', $palette->value),
                'expanded' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'admin',
        ]);
    }
}
