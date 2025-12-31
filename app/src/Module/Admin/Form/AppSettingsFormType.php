<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Shared\DTO\Settings\AppSettingsDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('autoApprove', CheckboxType::class, [
                'label' => 'crud.app_settings.field.auto_approve',
                'required' => false,
            ])
            ->add('autoApproveDelay', IntegerType::class, [
                'label' => 'crud.app_settings.field.auto_approve_delay',
                'help' => 'crud.app_settings.field.auto_approve_delay_help',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AppSettingsDTO::class,
            'translation_domain' => 'admin',
        ]);
    }
}
