<?php

declare(strict_types=1);

namespace App\Shared\DTO\Settings;

use App\Shared\Enum\AppSettingsEnum;
use App\Shared\Enum\WeeklyDigestDayEnum;
use Symfony\Component\Validator\Constraints as Assert;

class AppSettingsDTO
{
    public const DEFAULT_ANNUAL_ALLOWANCE = 25;
    public const WEEKLY_DIGEST_TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';
    public const DEFAULT_WEEKLY_DIGEST_TIME = '08:15';
    public const DEFAULT_WEEKLY_DIGEST_TIMEZONE = 'UTC';
    public const DEFAULT_WEEKLY_DIGEST_DAY = WeeklyDigestDayEnum::Monday;

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
        #[Assert\NotNull]
        public bool $slackStatusSyncEnabled,
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $organizationName,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: self::WEEKLY_DIGEST_TIME_PATTERN, message: 'crud.app_settings.field.weekly_digest_time_invalid')]
        public string $weeklyDigestTime = self::DEFAULT_WEEKLY_DIGEST_TIME,
        #[Assert\NotBlank]
        #[Assert\Timezone]
        public string $weeklyDigestTimezone = self::DEFAULT_WEEKLY_DIGEST_TIMEZONE,
        #[Assert\NotNull]
        public WeeklyDigestDayEnum $weeklyDigestDay = self::DEFAULT_WEEKLY_DIGEST_DAY,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            autoApprove: self::getNestedValue($data, AppSettingsEnum::AUTO_APPROVE, false),
            autoApproveDelay: self::getNestedValue($data, AppSettingsEnum::AUTO_APPROVE_DELAY, 0),
            defaultAnnualAllowance: self::getNestedValue($data, AppSettingsEnum::DEFAULT_ANNUAL_ALLOWANCE, self::DEFAULT_ANNUAL_ALLOWANCE),
            minNoticeDays: self::getNestedValue($data, AppSettingsEnum::MIN_NOTICE_DAYS, 0),
            maxConsecutiveDays: self::getNestedValue($data, AppSettingsEnum::MAX_CONSECUTIVE_DAYS, 0),
            skipWeekendHolidays: self::getNestedValue($data, AppSettingsEnum::SKIP_WEEKEND_HOLIDAYS, false),
            slackStatusSyncEnabled: self::getNestedValue($data, AppSettingsEnum::SLACK_STATUS_SYNC_ENABLED, false),
            organizationName: self::getNestedValue($data, AppSettingsEnum::ORGANIZATION_NAME, ''),
            weeklyDigestTime: self::getNestedValue($data, AppSettingsEnum::WEEKLY_DIGEST_TIME, self::DEFAULT_WEEKLY_DIGEST_TIME),
            weeklyDigestTimezone: self::getNestedValue($data, AppSettingsEnum::WEEKLY_DIGEST_TIMEZONE, self::DEFAULT_WEEKLY_DIGEST_TIMEZONE),
            weeklyDigestDay: WeeklyDigestDayEnum::from(
                self::getNestedValue($data, AppSettingsEnum::WEEKLY_DIGEST_DAY, self::DEFAULT_WEEKLY_DIGEST_DAY->value),
            ),
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
        self::setNestedValue($result, AppSettingsEnum::SLACK_STATUS_SYNC_ENABLED, $this->slackStatusSyncEnabled);
        self::setNestedValue($result, AppSettingsEnum::ORGANIZATION_NAME, $this->organizationName);
        self::setNestedValue($result, AppSettingsEnum::WEEKLY_DIGEST_TIME, $this->weeklyDigestTime);
        self::setNestedValue($result, AppSettingsEnum::WEEKLY_DIGEST_TIMEZONE, $this->weeklyDigestTimezone);
        self::setNestedValue($result, AppSettingsEnum::WEEKLY_DIGEST_DAY, $this->weeklyDigestDay->value);

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
