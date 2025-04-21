<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\DTO\Slack;

use App\Shared\Enum\LeaveRequestStatusEnum;

class InteractiveNotificationDTO
{
    public function __construct(
        public readonly LeaveRequestStatusEnum $status,
        public readonly string $type,
        public readonly string $identifier,
        public readonly string $channel,
        public readonly ?string $responseUrl,
        public readonly ?string $memberId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $payload = json_decode(json: $data['payload'], associative: true) ?? [];
        $action = $payload['actions'][0] ?? null;

        if (null === $action) {
            throw new \InvalidArgumentException(message: '[SLACK][PAYLOAD] Action not found in payload.');
        }

        /** @var ?string $value */
        $value = $action['value'] ?? null;

        if (!is_string($value) || empty($value)) {
            throw new \InvalidArgumentException(message: '[SLACK][PAYLOAD] Value not found in action.');
        }

        [$type, $status, $identifier] = explode('_', $value);

        return new self(
            status: LeaveRequestStatusEnum::tryFrom($status),
            type: $type,
            identifier: $identifier,
            channel: $payload['channel']['id'] ?? '',
            responseUrl: $payload['response_url'] ?? null,
            memberId: $payload['user']['id'] ?? null,
        );
    }
}
