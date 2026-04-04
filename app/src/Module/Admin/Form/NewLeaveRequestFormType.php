<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Module\Admin\DTO\NewLeaveRequestDTO;
use App\Module\Admin\Form\DataTransformerm\DateRangeToStartEndTransformer;
use App\Module\Admin\Validator\HasWorkdaysAndBalance;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewLeaveRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('leaveType', EntityType::class, [
                'class' => LeaveRequestType::class,
                'label' => 'What type of absence',
                'choice_label' => 'name',
                'attr' => [
                    'data-action' => 'live#action',
                    'data-live-action-param' => 'updated',
                    'data-model' => 'leaveType',
                ],
            ])
            ->add('dateRange', TextType::class, [
                'label' => 'When',
                'attr' => [
                    'data-action' => 'live#action',
                    'data-live-action-param' => 'updated',
                ],
                'constraints' => [
                    new HasWorkdaysAndBalance(),
                ],
            ]);

        $builder
            ->get('dateRange')
            ->addModelTransformer(new DateRangeToStartEndTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewLeaveRequestDTO::class,
        ]);
    }
}
