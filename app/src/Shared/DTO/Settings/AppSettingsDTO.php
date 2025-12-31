<?php

declare(strict_types=1);

namespace App\Shared\DTO\Settings;

use App\Shared\Enum\AppSettingsEnum;
use Symfony\Component\Validator\Constraints as Assert;

class AppSettingsDTO
{
    public function __construct(
        #[Assert\NotNull]
        public bool $autoApprove,
        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public int $autoApproveDelay,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            autoApprove: self::getNestedValue($data, AppSettingsEnum::AUTO_APPROVE),
            autoApproveDelay: self::getNestedValue($data, AppSettingsEnum::AUTO_APPROVE_DELAY),
        );
    }

    public function toArray(): array
    {
        $result = [];
        self::setNestedValue($result, AppSettingsEnum::AUTO_APPROVE, $this->autoApprove);
        self::setNestedValue($result, AppSettingsEnum::AUTO_APPROVE_DELAY, $this->autoApproveDelay);

        return $result;
    }

    private static function getNestedValue(array $data, AppSettingsEnum $setting): mixed
    {
        $value = $data;
        foreach (explode('.', $setting->value) as $key) {
            $value = $value[$key];
        }

        return $value;
    }

    private static function setNestedValue(array &$result, AppSettingsEnum $setting, mixed $value): void
    {
        $keys = explode('.', $setting->value);
        $current = &$result;

        $lastKey = array_pop($keys);

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current[$lastKey] = $value;
    }
}
