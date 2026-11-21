<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20261118120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add slack holiday status tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE slack_holiday_status (date DATE NOT NULL, status_text VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, id CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, holiday_id CHAR(36) DEFAULT NULL, UNIQUE INDEX uniq_slack_holiday_status_user_date (user_id, date), INDEX IDX_DA04057DA76ED395 (user_id), INDEX IDX_DA04057D830A3EC0 (holiday_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE slack_holiday_status ADD CONSTRAINT FK_DA04057DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE slack_holiday_status ADD CONSTRAINT FK_DA04057D830A3EC0 FOREIGN KEY (holiday_id) REFERENCES holiday (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE slack_holiday_status DROP FOREIGN KEY FK_DA04057DA76ED395');
        $this->addSql('ALTER TABLE slack_holiday_status DROP FOREIGN KEY FK_DA04057D830A3EC0');
        $this->addSql('DROP TABLE slack_holiday_status');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
