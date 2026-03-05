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
            ])
            ->add('defaultAnnualAllowance', IntegerType::class, [
                'label' => 'crud.app_settings.field.default_annual_allowance',
                'help' => 'crud.app_settings.field.default_annual_allowance_help',
                'required' => true,
            ])
            ->add('minNoticeDays', IntegerType::class, [
                'label' => 'crud.app_settings.field.min_notice_days',
                'help' => 'crud.app_settings.field.min_notice_days_help',
                'required' => true,
            ])
            ->add('maxConsecutiveDays', IntegerType::class, [
                'label' => 'crud.app_settings.field.max_consecutive_days',
                'help' => 'crud.app_settings.field.max_consecutive_days_help',
                'required' => true,
            ])
            ->add('skipWeekendHolidays', CheckboxType::class, [
                'label' => 'crud.app_settings.field.skip_weekend_holidays',
                'help' => 'crud.app_settings.field.skip_weekend_holidays_help',
                'required' => false,
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
