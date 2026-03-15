<?php

declare(strict_types=1);

namespace App\Module\User\Form;

use App\Module\User\DTO\UserInvitationRequestDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserInvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'invitation.field.first_name',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'invitation.field.last_name',
            ])
            ->add('birthdate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'invitation.field.birth_date',
                'required' => false,
                'help' => 'invitation.field.birth_date_help',
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Passwords must match.',
                'first_options' => ['label' => 'invitation.field.password'],
                'second_options' => ['label' => 'invitation.field.password_repeat'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserInvitationRequestDTO::class,
            'translation_domain' => 'messages',
        ]);
    }
}
