<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Module\Admin\DTO\HolidayCalendarImportDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HolidayCalendarImportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('country', ChoiceType::class, [
                'choices' => $options['country_choices'],
                'label' => 'crud.holiday_import.field.country',
                'placeholder' => '—',
            ])
            ->add('year', IntegerType::class, [
                'label' => 'crud.holiday_import.field.year',
                'attr' => [
                    'min' => 2000,
                    'max' => 2100,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HolidayCalendarImportDTO::class,
            'translation_domain' => 'admin',
            'country_choices' => [],
        ]);

        $resolver->setAllowedTypes('country_choices', 'array');
    }
}
