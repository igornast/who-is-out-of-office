<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\NewLeaveRequestDTO;
use App\Module\Admin\Validator\HasWorkdaysAndBalance;
use App\Module\Admin\Validator\HasWorkdaysAndBalanceValidator;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

beforeEach(function (): void {
    $this->security = mock(Security::class);
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->appSettingsFacade = mock(AppSettingsFacadeInterface::class);

    $this->user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@ooo.com',
        password: 'hashed',
        workingDays: [1, 2, 3, 4, 5],
        currentLeaveBalance: 20,
    );
    $this->security->allows('getUser')->andReturn($this->user);

    $this->balanceLeaveType = new LeaveRequestType(
        id: Uuid::uuid4(),
        isAffectingBalance: true,
        name: 'Vacation',
        backgroundColor: '#000',
        borderColor: '#000',
        textColor: '#fff',
        icon: 'icon',
    );

    $this->nonBalanceLeaveType = new LeaveRequestType(
        id: Uuid::uuid4(),
        isAffectingBalance: false,
        name: 'Remote',
        backgroundColor: '#000',
        borderColor: '#000',
        textColor: '#fff',
        icon: 'icon',
    );

    $this->constraint = new HasWorkdaysAndBalance();
    $this->context = mock(ExecutionContextInterface::class);

    $this->validator = new HasWorkdaysAndBalanceValidator(
        security: $this->security,
        leaveRequestFacade: $this->leaveRequestFacade,
        appSettingsFacade: $this->appSettingsFacade,
    );
    $this->validator->initialize($this->context);
});

function setupFormContext(object $testCase, LeaveRequestType $leaveType): void
{
    $dto = new NewLeaveRequestDTO(leaveType: $leaveType);
    $parentForm = mock(FormInterface::class);
    $parentForm->allows('getData')->andReturn($dto);
    $form = mock(FormInterface::class);
    $form->allows('getParent')->andReturn($parentForm);
    $testCase->context->allows('getObject')->andReturn($form);
}

it('adds violation when start date is less than minNoticeDays from today', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(3);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(0);

    $startDate = new DateTimeImmutable('+1 day');
    $endDate = new DateTimeImmutable('+2 days');

    setupFormContext($this, $this->balanceLeaveType);

    $violationBuilder = mock(ConstraintViolationBuilderInterface::class);
    $violationBuilder->allows('setParameter')->andReturn($violationBuilder);
    $violationBuilder->allows('addViolation');
    $this->context->expects('buildViolation')
        ->with($this->constraint->minNoticeDaysMessage)
        ->once()
        ->andReturn($violationBuilder);

    $this->validator->validate(['start' => $startDate, 'end' => $endDate], $this->constraint);
});

it('does not add minNoticeDays violation when start date meets minimum notice', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(3);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(0);

    $startDate = new DateTimeImmutable('+5 days');
    $endDate = new DateTimeImmutable('+6 days');

    setupFormContext($this, $this->balanceLeaveType);

    $this->leaveRequestFacade->allows('calculateWorkDays')->andReturn(2);

    $this->context->expects('buildViolation')
        ->with($this->constraint->minNoticeDaysMessage)
        ->never();

    $this->validator->validate(['start' => $startDate, 'end' => $endDate], $this->constraint);
});

it('skips minNoticeDays check when setting is 0', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(0);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(0);

    $startDate = new DateTimeImmutable('tomorrow');
    $endDate = new DateTimeImmutable('tomorrow');

    setupFormContext($this, $this->balanceLeaveType);

    $this->leaveRequestFacade->allows('calculateWorkDays')->andReturn(1);

    $this->context->expects('buildViolation')
        ->with($this->constraint->minNoticeDaysMessage)
        ->never();

    $this->validator->validate(['start' => $startDate, 'end' => $endDate], $this->constraint);
});

it('skips minNoticeDays check for non-balance leave types', function (): void {
    setupFormContext($this, $this->nonBalanceLeaveType);

    $startDate = new DateTimeImmutable('tomorrow');
    $endDate = new DateTimeImmutable('tomorrow');

    $this->context->expects('buildViolation')->never();

    $this->validator->validate(['start' => $startDate, 'end' => $endDate], $this->constraint);
});

it('adds violation when workdays exceed maxConsecutiveDays', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(0);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(5);

    $startDate = new DateTimeImmutable('+1 day');
    $endDate = new DateTimeImmutable('+14 days');

    setupFormContext($this, $this->balanceLeaveType);

    $this->leaveRequestFacade->allows('calculateWorkDays')->andReturn(10);

    $violationBuilder = mock(ConstraintViolationBuilderInterface::class);
    $violationBuilder->allows('setParameter')->andReturn($violationBuilder);
    $violationBuilder->allows('addViolation');
    $this->context->expects('buildViolation')
        ->with($this->constraint->maxConsecutiveDaysMessage)
        ->once()
        ->andReturn($violationBuilder);

    $this->validator->validate(['start' => $startDate, 'end' => $endDate], $this->constraint);
});

it('does not add maxConsecutiveDays violation when within limit', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(0);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(10);

    $startDate = new DateTimeImmutable('+1 day');
    $endDate = new DateTimeImmutable('+5 days');

    setupFormContext($this, $this->balanceLeaveType);

    $this->leaveRequestFacade->allows('calculateWorkDays')->andReturn(5);

    $this->context->expects('buildViolation')
        ->with($this->constraint->maxConsecutiveDaysMessage)
        ->never();

    $this->validator->validate(['start' => $startDate, 'end' => $endDate], $this->constraint);
});

it('skips maxConsecutiveDays check when setting is 0', function (): void {
    $this->appSettingsFacade->allows('minNoticeDays')->andReturn(0);
    $this->appSettingsFacade->allows('maxConsecutiveDays')->andReturn(0);

    $startDate = new DateTimeImmutable('+1 day');
    $endDate = new DateTimeImmutable('+30 days');

    setupFormContext($this, $this->balanceLeaveType);

    $this->leaveRequestFacade->allows('calculateWorkDays')->andReturn(20);

    $this->context->expects('buildViolation')
        ->with($this->constraint->maxConsecutiveDaysMessage)
        ->never();

    $this->validator->validate(['start' => $startDate, 'end' => $endDate], $this->constraint);
});
