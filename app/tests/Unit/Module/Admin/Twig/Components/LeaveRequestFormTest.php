<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Twig\Components\LeaveRequestForm;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@whoisooo.app',
        password: 'hashed',
        workingDays: [1, 2, 3, 4, 5],
        currentLeaveBalance: 20,
    );

    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->leaveRequestFacade->shouldReceive('getLeaveRequestsForUser')->andReturn([])->byDefault();
    $this->appSettingsFacade = mock(AppSettingsFacadeInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);

    $this->balanceLeaveType = LeaveRequestTypeDTOFixture::create([
        'isAffectingBalance' => true,
        'name' => 'Vacation',
    ]);

    $form = mock(FormInterface::class);
    $form->allows('createView')->andReturn(mock(FormView::class));
    $formFactory = mock(FormFactoryInterface::class);
    $formFactory->allows('create')->andReturn($form);

    $token = mock(TokenInterface::class);
    $token->allows('getUser')->andReturn($this->user);
    $tokenStorage = mock(TokenStorageInterface::class);
    $tokenStorage->allows('getToken')->andReturn($token);

    $authChecker = mock(AuthorizationCheckerInterface::class);

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('form.factory')->andReturn($formFactory);
    $container->allows('get')->with('security.token_storage')->andReturn($tokenStorage);
    $container->allows('get')->with('security.authorization_checker')->andReturn($authChecker);

    $this->component = new LeaveRequestForm(
        translator: $this->translator,
        leaveRequestFacade: $this->leaveRequestFacade,
    );
    $this->component->setContainer($container);
    $this->component->leaveType = $this->balanceLeaveType->id->toString();
});

it('shows warning and disables submit when start date violates minNoticeDays', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(5);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(0);
    $this->leaveRequestFacade->allows('getLeaveTypeById')
        ->with($this->balanceLeaveType->id->toString())
        ->andReturn($this->balanceLeaveType);

    $startDate = new DateTimeImmutable('+1 day');
    $this->component->formValues = [
        'leaveType' => $this->balanceLeaveType->id->toString(),
        'dateRange' => sprintf('%s to %s', $startDate->format('Y-m-d'), $startDate->modify('+2 days')->format('Y-m-d')),
    ];

    $this->component->updated($this->user, $this->leaveRequestFacade, $this->appSettingsFacade);

    expect($this->component->isSubmitDisabled)->toBeTrue()
        ->and($this->component->infoBox)->toContain('min_notice_box');
});

it('shows warning and disables submit when workdays exceed maxConsecutiveDays', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(0);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(5);
    $this->leaveRequestFacade->allows('getLeaveTypeById')
        ->with($this->balanceLeaveType->id->toString())
        ->andReturn($this->balanceLeaveType);

    $startDate = new DateTimeImmutable('+10 days');
    $endDate = $startDate->modify('+14 days');
    $this->component->formValues = [
        'leaveType' => $this->balanceLeaveType->id->toString(),
        'dateRange' => sprintf('%s to %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')),
    ];

    $this->leaveRequestFacade->allows('calculateWorkDays')->andReturn(10);

    $this->component->updated($this->user, $this->leaveRequestFacade, $this->appSettingsFacade);

    expect($this->component->isSubmitDisabled)->toBeTrue()
        ->and($this->component->infoBox)->toContain('max_consecutive_box');
});

it('allows submit when within minNoticeDays and maxConsecutiveDays limits', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(2);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(10);
    $this->leaveRequestFacade->allows('getLeaveTypeById')
        ->with($this->balanceLeaveType->id->toString())
        ->andReturn($this->balanceLeaveType);

    $startDate = new DateTimeImmutable('+5 days');
    $endDate = $startDate->modify('+3 days');
    $this->component->formValues = [
        'leaveType' => $this->balanceLeaveType->id->toString(),
        'dateRange' => sprintf('%s to %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')),
    ];

    $this->leaveRequestFacade->allows('calculateWorkDays')->andReturn(3);

    $this->component->updated($this->user, $this->leaveRequestFacade, $this->appSettingsFacade);

    expect($this->component->isSubmitDisabled)->toBeFalse();
});

it('disables submit and clears info box when dateRange is empty', function (): void {
    $this->component->formValues = ['leaveType' => $this->balanceLeaveType->id->toString(), 'dateRange' => ''];

    $this->component->updated($this->user, $this->leaveRequestFacade, $this->appSettingsFacade);

    expect($this->component->isSubmitDisabled)->toBeTrue()
        ->and($this->component->infoBox)->toBe('');
});

it('disables submit when leaveType is null and dateRange is provided', function (): void {
    $this->component->leaveType = null;
    $startDate = new DateTimeImmutable('+5 days');
    $this->component->formValues = [
        'dateRange' => $startDate->format('Y-m-d'),
    ];

    $this->component->updated($this->user, $this->leaveRequestFacade, $this->appSettingsFacade);

    expect($this->component->isSubmitDisabled)->toBeTrue()
        ->and($this->component->infoBox)->toBe('');
});

it('enables submit when leaveType ID does not resolve to a known type', function (): void {
    $this->component->leaveType = 'non-existent-uuid';
    $this->leaveRequestFacade->allows('getLeaveTypeById')
        ->with('non-existent-uuid')
        ->andReturn(null);

    $startDate = new DateTimeImmutable('+5 days');
    $this->component->formValues = [
        'leaveType' => 'non-existent-uuid',
        'dateRange' => sprintf('%s to %s', $startDate->format('Y-m-d'), $startDate->modify('+2 days')->format('Y-m-d')),
    ];

    $this->component->updated($this->user, $this->leaveRequestFacade, $this->appSettingsFacade);

    expect($this->component->isSubmitDisabled)->toBeFalse()
        ->and($this->component->infoBox)->toBe('');
});

it('enables submit immediately when leave type does not affect balance', function (): void {
    $nonBalanceLeaveType = LeaveRequestTypeDTOFixture::create([
        'isAffectingBalance' => false,
        'name' => 'Remote Work',
    ]);
    $this->component->leaveType = $nonBalanceLeaveType->id->toString();
    $this->leaveRequestFacade->allows('getLeaveTypeById')
        ->with($nonBalanceLeaveType->id->toString())
        ->andReturn($nonBalanceLeaveType);

    $startDate = new DateTimeImmutable('+5 days');
    $this->component->formValues = [
        'leaveType' => $nonBalanceLeaveType->id->toString(),
        'dateRange' => sprintf('%s to %s', $startDate->format('Y-m-d'), $startDate->modify('+2 days')->format('Y-m-d')),
    ];

    $this->component->updated($this->user, $this->leaveRequestFacade, $this->appSettingsFacade);

    expect($this->component->isSubmitDisabled)->toBeFalse()
        ->and($this->component->infoBox)->toBe('');
});

it('shows no balance warning and disables submit when remaining balance is negative', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(0);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(0);
    $this->leaveRequestFacade->allows('getLeaveTypeById')
        ->with($this->balanceLeaveType->id->toString())
        ->andReturn($this->balanceLeaveType);

    $startDate = new DateTimeImmutable('+5 days');
    $endDate = $startDate->modify('+10 days');
    $this->component->formValues = [
        'leaveType' => $this->balanceLeaveType->id->toString(),
        'dateRange' => sprintf('%s to %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')),
    ];

    $this->leaveRequestFacade->allows('calculateWorkDays')->andReturn(25);

    $this->component->updated($this->user, $this->leaveRequestFacade, $this->appSettingsFacade);

    expect($this->component->isSubmitDisabled)->toBeTrue()
        ->and($this->component->infoBox)->toContain('no_balance_box');
});

it('treats single date selection as same start and end date', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(0);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(0);
    $this->leaveRequestFacade->allows('getLeaveTypeById')
        ->with($this->balanceLeaveType->id->toString())
        ->andReturn($this->balanceLeaveType);

    $startDate = new DateTimeImmutable('+5 days');
    $this->component->formValues = [
        'leaveType' => $this->balanceLeaveType->id->toString(),
        'dateRange' => $startDate->format('Y-m-d'),
    ];

    $this->leaveRequestFacade->allows('calculateWorkDays')->andReturn(1);

    $this->component->updated($this->user, $this->leaveRequestFacade, $this->appSettingsFacade);

    expect($this->component->isSubmitDisabled)->toBeFalse();
});

it('instantiateForm uses selected leaveType when already set', function (): void {
    $this->component->leaveType = $this->balanceLeaveType->id->toString();
    $this->leaveRequestFacade->expects('getLeaveTypeById')
        ->with($this->balanceLeaveType->id->toString())
        ->once()
        ->andReturn($this->balanceLeaveType);

    $method = new ReflectionMethod($this->component, 'instantiateForm');
    $form = $method->invoke($this->component);

    expect($form)->toBeInstanceOf(FormInterface::class);
});

it('instantiateForm defaults to first type when leaveType is null', function (): void {
    $this->component->leaveType = null;
    $types = [
        LeaveRequestTypeDTOFixture::create(['name' => 'Vacation']),
        LeaveRequestTypeDTOFixture::create(['name' => 'Sick']),
    ];
    $this->leaveRequestFacade->expects('getAllLeaveTypes')->once()->andReturn($types);

    $method = new ReflectionMethod($this->component, 'instantiateForm');
    $method->invoke($this->component);

    expect($this->component->leaveType)->toBe($types[0]->id->toString());
});

it('instantiateForm handles empty types list when leaveType is null', function (): void {
    $this->component->leaveType = null;
    $this->leaveRequestFacade->expects('getAllLeaveTypes')->once()->andReturn([]);

    $method = new ReflectionMethod($this->component, 'instantiateForm');
    $method->invoke($this->component);

    expect($this->component->leaveType)->toBeNull();
});

it('returns existing leave requests as serialized array', function (): void {
    $leaveRequest1 = App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture::create([
        'startDate' => new DateTimeImmutable('2026-04-01'),
        'endDate' => new DateTimeImmutable('2026-04-03'),
        'status' => App\Shared\Enum\LeaveRequestStatusEnum::Approved,
    ]);
    $leaveRequest2 = App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture::create([
        'startDate' => new DateTimeImmutable('2026-04-10'),
        'endDate' => new DateTimeImmutable('2026-04-10'),
        'status' => App\Shared\Enum\LeaveRequestStatusEnum::Pending,
    ]);

    $this->leaveRequestFacade->allows('getLeaveRequestsForUser')
        ->with($this->user->id->toString(), [
            App\Shared\Enum\LeaveRequestStatusEnum::Pending,
            App\Shared\Enum\LeaveRequestStatusEnum::Approved,
        ])
        ->andReturn([$leaveRequest1, $leaveRequest2]);

    $result = $this->component->getExistingLeaves();

    expect($result)->toHaveCount(2)
        ->and($result[0])->toMatchArray([
            'start' => '2026-04-01',
            'end' => '2026-04-03',
            'type' => $leaveRequest1->leaveType->name,
            'color' => $leaveRequest1->leaveType->borderColor,
            'status' => 'approved',
        ])
        ->and($result[1])->toMatchArray([
            'start' => '2026-04-10',
            'end' => '2026-04-10',
            'type' => $leaveRequest2->leaveType->name,
            'color' => $leaveRequest2->leaveType->borderColor,
            'status' => 'pending',
        ]);
});

it('returns empty array when user has no active leave requests', function (): void {
    $this->leaveRequestFacade->allows('getLeaveRequestsForUser')
        ->andReturn([]);

    $result = $this->component->getExistingLeaves();

    expect($result)->toBe([]);
});
