<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306141119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active and last_synced_year columns to holiday_calendar table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holiday_calendar ADD is_active TINYINT NOT NULL DEFAULT 1, ADD last_synced_year INT DEFAULT NULL');
        $this->addSql('UPDATE holiday_calendar SET is_active = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holiday_calendar DROP is_active, DROP last_synced_year');
    }
}
