<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Constants\UserSettings;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('workingDays', ChoiceType::class, [
                'choices' => UserSettings::WORKING_DAYS,
                'expanded' => true,
                'multiple' => true,
                'label' => 'Working Days',
                'required' => true,
            ])
            ->add('holidayCalendar', EntityType::class, [
                'class' => HolidayCalendar::class,
                'choice_label' => fn (HolidayCalendar $c) => $c->countryName,
                'placeholder' => 'Select your calendar',
            ])

            ->add('birthDate', DateType::class, [
                'label' => 'Birth Date',
                'required' => false,
                'help' => 'If you enter a birthdate, our OOO Slackbot will automatically send a friendly reminder to your teammates before your birthday. This helps everyone celebrate together.',
            ])

            ->add('contractStartedAt', DateType::class, [
                'label' => 'Contract Start Date',
                'disabled' => true,
                'required' => false,
                'help' => 'Your contract start date is set by HR.',
            ])
            ->add('hasCelebrateWorkAnniversary', CheckboxType::class, [
                'label' => 'Celebrate my work anniversary',
                'required' => false,
                'help' => 'Tick this box if you want the OOO Slackbot to send a friendly reminder to your teammates each year.',
            ])

            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => false,
                'mapped' => false,
                'invalid_message' => 'Passwords must match.',
                'first_options' => ['label' => 'New Password'],
                'second_options' => ['label' => 'Repeat Password'],
            ])
            ->add('profileImageFile', FileType::class, [
                'label' => 'Profile Image',
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
