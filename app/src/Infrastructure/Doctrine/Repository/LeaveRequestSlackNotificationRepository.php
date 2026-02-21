<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestSlackNotification;
use App\Infrastructure\Slack\DTO\LeaveRequestSlackNotificationDTO;
use App\Infrastructure\Slack\Repository\LeaveRequestSlackNotificationRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<LeaveRequestSlackNotification>
 */
class LeaveRequestSlackNotificationRepository extends ServiceEntityRepository implements LeaveRequestSlackNotificationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveRequestSlackNotification::class);
    }

    public function save(string $leaveRequestId, string $channelId, string $messageTs): void
    {
        $em = $this->getEntityManager();

        /** @var LeaveRequest $leaveRequest */
        $leaveRequest = $em->getReference(LeaveRequest::class, Uuid::fromString($leaveRequestId));

        $notification = new LeaveRequestSlackNotification(
            id: Uuid::uuid4(),
            leaveRequest: $leaveRequest,
            channelId: $channelId,
            messageTs: $messageTs,
        );

        $em->persist($notification);
        $em->flush();
    }

    public function findByLeaveRequestId(string $leaveRequestId): ?LeaveRequestSlackNotificationDTO
    {
        $notification = $this->findOneBy(['leaveRequest' => $leaveRequestId]);

        if (null === $notification) {
            return null;
        }

        return new LeaveRequestSlackNotificationDTO(
            channelId: $notification->channelId,
            messageTs: $notification->messageTs,
        );
    }
}
