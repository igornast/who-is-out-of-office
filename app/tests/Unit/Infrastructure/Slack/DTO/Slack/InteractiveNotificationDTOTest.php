<?php

declare(strict_types=1);

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;

it('creates interactive notification from array', function () {
    $json = file_get_contents(__DIR__.'/../../../../../_sample-data/interactive_leave_request_approve_request.json');
    $data = ['payload' => json_encode(json_decode($json, true)['payload'])];

    $dto = InteractiveNotificationDTO::fromArray($data);

    expect($dto->type)->toBe('leave-request')
        ->and($dto->channel)->toBe('ABC123ABC123')
        ->and($dto->status)->toBe(LeaveRequestStatusEnum::Approved)
        ->and($dto->memberId)->toBe('123-member-id')
        ->and($dto->responseUrl)->toBe('https://hooks.slack.com/actions/T12A123123/123123123/ABC123ABC123ABC');
});
