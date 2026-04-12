<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

class IntegrationStatusDTO
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_NEEDS_AUTH = 'needs_auth';
    public const STATUS_DISABLED = 'disabled';

    /**
     * @param list<string> $missingVars
     */
    public function __construct(
        public string $status,
        public array $missingVars = [],
    ) {
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function fromEnvVars(array $vars): self
    {
        $missing = [];

        foreach ($vars as $name => $value) {
            if (!is_string($value) || '' === $value) {
                $missing[] = $name;
            }
        }

        if ([] === $missing) {
            return new self(self::STATUS_ACTIVE);
        }

        if (count($missing) === count($vars)) {
            return new self(self::STATUS_DISABLED, $missing);
        }

        return new self(self::STATUS_INCOMPLETE, $missing);
    }
}
