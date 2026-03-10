<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Module\Admin\Constants\UserSettings;
use App\Module\Admin\DTO\UserProfileDTO;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'crud.user_profile.field.first_name',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'crud.user_profile.field.last_name',
            ])
            ->add('workingDays', ChoiceType::class, [
                'choices' => UserSettings::WORKING_DAYS,
                'expanded' => true,
                'multiple' => true,
                'label' => 'crud.user_profile.field.working_days',
                'required' => true,
            ])
            ->add('holidayCalendar', EntityType::class, [
                'class' => HolidayCalendar::class,
                'choice_label' => fn (HolidayCalendar $c) => $c->countryName,
                'label' => 'crud.user_profile.field.holiday_calendar',
                'placeholder' => 'crud.user_profile.field.holiday_calendar_placeholder',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('c')
                    ->where('c.isActive = :active')
                    ->setParameter('active', true)
                    ->orderBy('c.countryName', 'ASC'),
            ])

            ->add('birthDate', DateType::class, [
                'label' => 'crud.user_profile.field.birth_date',
                'required' => false,
                'help' => 'crud.user_profile.field.birth_date_help',
            ])

            ->add('contractStartedAt', DateType::class, [
                'label' => 'crud.user_profile.field.contract_started_at',
                'disabled' => true,
                'required' => false,
                'help' => 'crud.user_profile.field.contract_started_at_help',
            ])
            ->add('absenceBalanceResetDay', DateType::class, [
                'label' => 'crud.user_profile.field.absence_balance_reset_day',
                'disabled' => true,
                'required' => false,
                'help' => 'crud.user_profile.field.absence_balance_reset_day_help',
            ])
            ->add('hasCelebrateWorkAnniversary', CheckboxType::class, [
                'label' => 'crud.user_profile.field.has_celebrate_work_anniversary',
                'required' => false,
                'help' => 'crud.user_profile.field.has_celebrate_work_anniversary_help',
            ])
            ->add('isEmailNotificationsEnabled', CheckboxType::class, [
                'label' => 'crud.user_profile.field.is_email_notifications_enabled',
                'required' => false,
                'help' => 'crud.user_profile.field.is_email_notifications_enabled_help',
            ])
            ->add('profileImageFile', FileType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('removeProfileImage', HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserProfileDTO::class,
            'translation_domain' => 'admin',
        ]);
    }
}
