<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Enum\WeeklyDigestDayEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
            ])
            ->add('slackStatusSyncEnabled', CheckboxType::class, [
                'label' => 'crud.app_settings.field.slack_status_sync_enabled',
                'help' => 'crud.app_settings.field.slack_status_sync_enabled_help',
                'required' => false,
            ])
            ->add('organizationName', TextType::class, [
                'label' => 'crud.app_settings.field.organization_name',
                'help' => 'crud.app_settings.field.organization_name_help',
                'required' => true,
            ])
            ->add('weeklyDigestDay', EnumType::class, [
                'class' => WeeklyDigestDayEnum::class,
                'choice_label' => fn (WeeklyDigestDayEnum $case): string => 'crud.app_settings.day.'.$case->value,
                'label' => 'crud.app_settings.field.weekly_digest_day',
                'help' => 'crud.app_settings.field.weekly_digest_day_help',
                'required' => true,
            ])
            ->add('weeklyDigestTime', TimeType::class, [
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
                'with_seconds' => false,
                'label' => 'crud.app_settings.field.weekly_digest_time',
                'help' => 'crud.app_settings.field.weekly_digest_time_help',
                'required' => true,
            ])
            ->add('weeklyDigestTimezone', TimezoneType::class, [
                'label' => 'crud.app_settings.field.weekly_digest_timezone',
                'help' => 'crud.app_settings.field.weekly_digest_timezone_help',
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
