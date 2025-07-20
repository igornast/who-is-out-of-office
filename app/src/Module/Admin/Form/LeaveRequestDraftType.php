<?php

declare(strict_types=1);

namespace App\Module\Admin\Form;

use App\Module\Admin\DTO\LeaveRequestDraftDTO;
use App\Module\Admin\Form\DataTransformerm\DateRangeToStartEndTransformer;
use App\Shared\Enum\LeaveRequestTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LeaveRequestDraftType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('leaveType', EnumType::class, [
                'class' => LeaveRequestTypeEnum::class,
                'label' => 'What type of absence',
                'attr' => [
                    'data-live-name' => 'leaveType',
                    'data-action' => 'live#action',
                    'data-live-action-param' => 'updated',
                ],
            ])
            ->add('dateRange', TextType::class, [
                'label' => 'When',
                'attr' => [
                    'placeholder' => 'YYYY-MM-DD to YYYY-MM-DD',
                    'data-controller' => 'flatpickr',
                    'data-live-name' => 'dateRange',
                    'data-action' => 'live#action',
                    'data-live-action-param' => 'updated',
                ],
            ]);

        $builder
            ->get('dateRange')
            ->addModelTransformer(new DateRangeToStartEndTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LeaveRequestDraftDTO::class,
        ]);
    }
}
