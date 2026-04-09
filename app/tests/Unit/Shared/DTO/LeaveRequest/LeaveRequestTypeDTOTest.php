<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use Ramsey\Uuid\Uuid;

it('maps sort field from entity', function (): void {
    $entity = new LeaveRequestType(
        id: Uuid::uuid4(),
        isAffectingBalance: true,
        name: 'Vacation',
        backgroundColor: '#d4edda',
        borderColor: '#28a745',
        textColor: '#000000',
        icon: '🌴',
        sort: 5,
    );

    $dto = LeaveRequestTypeDTO::fromEntity($entity);

    expect($dto->sort)->toBe(5);
});

it('maps null sort field from entity', function (): void {
    $entity = new LeaveRequestType(
        id: Uuid::uuid4(),
        isAffectingBalance: true,
        name: 'Vacation',
        backgroundColor: '#d4edda',
        borderColor: '#28a745',
        textColor: '#000000',
        icon: '🌴',
    );

    $dto = LeaveRequestTypeDTO::fromEntity($entity);

    expect($dto->sort)->toBeNull();
});
