<?php

declare(strict_types=1);

use App\Module\Admin\DTO\NewLeaveRequestDTO;
use App\Module\Admin\Form\NewLeaveRequestFormType;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

beforeEach(function (): void {
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->leaveRequestFacade->allows('getAllLeaveTypes')->andReturn([
        LeaveRequestTypeDTOFixture::create(['name' => 'Vacation']),
        LeaveRequestTypeDTOFixture::create(['name' => 'Remote']),
    ]);
});

it('configures data_class as NewLeaveRequestDTO', function (): void {
    $formType = new NewLeaveRequestFormType($this->leaveRequestFacade);
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);

    expect($resolver->resolve()['data_class'])->toBe(NewLeaveRequestDTO::class);
});

it('dateRange field has live action attributes for LiveComponent integration', function (): void {
    $capturedAttrs = [];

    $dateRangeBuilder = mock(FormBuilderInterface::class);
    $dateRangeBuilder->allows('addModelTransformer');

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function () use (&$capturedAttrs, $builder) {
            [$child, , $options] = array_pad(func_get_args(), 3, []);
            if ('dateRange' === $child) {
                $capturedAttrs = $options['attr'] ?? [];
            }

            return $builder;
        }
    );
    $builder->allows('get')->with('dateRange')->andReturn($dateRangeBuilder);

    $formType = new NewLeaveRequestFormType($this->leaveRequestFacade);
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($capturedAttrs)
        ->toHaveKey('data-action', 'live#action')
        ->toHaveKey('data-live-action-param', 'updated');
});

it('dateRange field has no data-controller attribute after moving controller to wrapper div', function (): void {
    $capturedAttrs = [];

    $dateRangeBuilder = mock(FormBuilderInterface::class);
    $dateRangeBuilder->allows('addModelTransformer');

    $builder = mock(FormBuilderInterface::class);
    $builder->allows('add')->andReturnUsing(
        function () use (&$capturedAttrs, $builder) {
            [$child, , $options] = array_pad(func_get_args(), 3, []);
            if ('dateRange' === $child) {
                $capturedAttrs = $options['attr'] ?? [];
            }

            return $builder;
        }
    );
    $builder->allows('get')->with('dateRange')->andReturn($dateRangeBuilder);

    $formType = new NewLeaveRequestFormType($this->leaveRequestFacade);
    $resolver = new OptionsResolver();
    $formType->configureOptions($resolver);
    $formType->buildForm($builder, $resolver->resolve());

    expect($capturedAttrs)->not->toHaveKey('data-controller')
        ->and($capturedAttrs)->not->toHaveKey('placeholder');
});
