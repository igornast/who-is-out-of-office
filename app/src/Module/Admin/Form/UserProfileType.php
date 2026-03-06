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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

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
            ->add('absenceBalanceResetDay', DateType::class, [
                'label' => 'Next Absence Balance Reset',
                'disabled' => true,
                'required' => false,
                'help' => 'The next date when your absence balance will be reset. This is set by HR.',
            ])
            ->add('hasCelebrateWorkAnniversary', CheckboxType::class, [
                'label' => 'Celebrate my work anniversary',
                'required' => false,
                'help' => 'Tick this box if you want the OOO Slackbot to send a friendly reminder to your teammates each year.',
            ])
            ->add('profileImageFile', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                    ),
                ],
            ])
            ->add('removeProfileImage', HiddenType::class, [
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
