<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add per-user calendar subscription customization columns to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD calendar_subscription_team_member_ids JSON DEFAULT NULL, ADD calendar_subscription_holiday_calendar_ids JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP calendar_subscription_team_member_ids, DROP calendar_subscription_holiday_calendar_ids');
    }
}
