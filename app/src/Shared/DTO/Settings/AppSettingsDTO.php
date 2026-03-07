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
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $defaultAnnualAllowance,
        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public int $minNoticeDays,
        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public int $maxConsecutiveDays,
        #[Assert\NotNull]
        public bool $skipWeekendHolidays,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            autoApprove: self::getNestedValue($data, AppSettingsEnum::AUTO_APPROVE),
            autoApproveDelay: self::getNestedValue($data, AppSettingsEnum::AUTO_APPROVE_DELAY),
            defaultAnnualAllowance: self::getNestedValue($data, AppSettingsEnum::DEFAULT_ANNUAL_ALLOWANCE),
            minNoticeDays: self::getNestedValue($data, AppSettingsEnum::MIN_NOTICE_DAYS),
            maxConsecutiveDays: self::getNestedValue($data, AppSettingsEnum::MAX_CONSECUTIVE_DAYS),
            skipWeekendHolidays: self::getNestedValue($data, AppSettingsEnum::SKIP_WEEKEND_HOLIDAYS, false),
        );
    }

    public function toArray(): array
    {
        $result = [];
        self::setNestedValue($result, AppSettingsEnum::AUTO_APPROVE, $this->autoApprove);
        self::setNestedValue($result, AppSettingsEnum::AUTO_APPROVE_DELAY, $this->autoApproveDelay);
        self::setNestedValue($result, AppSettingsEnum::DEFAULT_ANNUAL_ALLOWANCE, $this->defaultAnnualAllowance);
        self::setNestedValue($result, AppSettingsEnum::MIN_NOTICE_DAYS, $this->minNoticeDays);
        self::setNestedValue($result, AppSettingsEnum::MAX_CONSECUTIVE_DAYS, $this->maxConsecutiveDays);
        self::setNestedValue($result, AppSettingsEnum::SKIP_WEEKEND_HOLIDAYS, $this->skipWeekendHolidays);

        return $result;
    }

    private static function getNestedValue(array $data, AppSettingsEnum $setting, mixed $default = null): mixed
    {
        $value = $data;
        foreach (explode('.', $setting->value) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
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
