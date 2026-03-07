<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Module\Admin\DTO\ChangePasswordDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChangePasswordFormType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'settings.account_security.field.current_password',
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'settings.account_security.field.new_password',
                ],
                'second_options' => [
                    'label' => 'settings.account_security.field.confirm_password',
                ],
                // Workaround: RepeatedType ignores translation_domain for invalid_message (Symfony bug)
                'invalid_message' => $this->translator->trans('settings.account_security.error.passwords_must_match', domain: 'admin'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ChangePasswordDTO::class,
            'translation_domain' => 'admin',
        ]);
    }
}
