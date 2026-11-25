<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Exception\AppSettingsWriteException;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\Settings\AppSettingsDTOFixture;
use Doctrine\ORM\EntityManagerInterface;

class FailingSaveAppSettingsFacade implements AppSettingsFacadeInterface
{
    public int $updateCalls = 0;

    private AppSettingsDTO $settings;

    public function __construct()
    {
        $this->settings = AppSettingsDTOFixture::create();
    }

    public function isAutoApprove(): bool
    {
        return $this->settings->autoApprove;
    }

    public function autoApproveDelay(): int
    {
        return $this->settings->autoApproveDelay;
    }

    public function defaultAnnualAllowance(): int
    {
        return $this->settings->defaultAnnualAllowance;
    }

    public function minNoticeDays(): int
    {
        return $this->settings->minNoticeDays;
    }

    public function maxConsecutiveDays(): int
    {
        return $this->settings->maxConsecutiveDays;
    }

    public function skipWeekendHolidays(): bool
    {
        return $this->settings->skipWeekendHolidays;
    }

    public function isSlackStatusSyncEnabled(): bool
    {
        return $this->settings->slackStatusSyncEnabled;
    }

    public function organizationName(): string
    {
        return $this->settings->organizationName;
    }

    public function weeklyDigestCronExpression(): string
    {
        [$hour, $minute] = explode(':', $this->settings->weeklyDigestTime);

        return sprintf('%d %d * * %s', (int) $minute, (int) $hour, $this->settings->weeklyDigestDay->value);
    }

    public function weeklyDigestTimezone(): string
    {
        return $this->settings->weeklyDigestTimezone;
    }

    public function getAllSettings(): AppSettingsDTO
    {
        return $this->settings;
    }

    public function updateAllSettings(AppSettingsDTO $settingsDTO): void
    {
        ++$this->updateCalls;

        throw new AppSettingsWriteException('Failed to write file "app_setting.yaml": Permission denied');
    }
}

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->client->disableReboot();
    $this->admin = static::getContainer()->get(EntityManagerInterface::class)
        ->getRepository(User::class)
        ->findOneBy(['email' => 'admin@whoisooo.app']);
});

it('shows the translated save error and not the success message when the settings write fails', function (): void {
    $failingFacade = new FailingSaveAppSettingsFacade();
    static::getContainer()->set(AppSettingsFacadeInterface::class, $failingFacade);

    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', '/app/settings');
    $this->client->submit($crawler->selectButton('Save Changes')->form());

    $content = $this->client->getResponse()->getContent();

    expect($failingFacade->updateCalls)->toBe(1)
        ->and($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($content)->toContain('Could not save settings. Check that the settings directory is writable.')
        ->and($content)->not->toContain('Settings updated successfully!');
});
