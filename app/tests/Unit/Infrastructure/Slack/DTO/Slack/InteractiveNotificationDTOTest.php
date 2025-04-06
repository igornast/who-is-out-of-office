<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Slack\DTO\Slack;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use PHPUnit\Framework\TestCase;

class InteractiveNotificationDTOTest extends TestCase
{
    public function testItCreatedInteractiveNotificationDTOFromPayload(): void
    {
        $json = file_get_contents(__DIR__.'/../../../../../_sample-data/interactive_leave_request_approve_request.json');
        $data = ['payload' => json_encode(json_decode($json, true)['payload'])];

        $dto = InteractiveNotificationDTO::fromArray($data);

        $this->assertSame('leave-request', $dto->type);
        $this->assertSame('request-approve-id', $dto->identifier);
        $this->assertEquals(LeaveRequestStatusEnum::Approved, $dto->status);
        $this->assertSame('ABC123ABC123', $dto->channel);
        $this->assertSame('123-member-id', $dto->memberId);
        $this->assertSame('https://hooks.slack.com/actions/T12A123123/123123123/ABC123ABC123ABC', $dto->responseUrl);
    }
}
