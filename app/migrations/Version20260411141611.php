<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411141611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add slack status auto-sync';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE slack_admin_token (id CHAR(36) NOT NULL, encrypted_token LONGTEXT NOT NULL, slack_user_id VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE leave_request_type ADD slack_status_emoji VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_slack_integration ADD slack_status_sync_enabled TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE leave_request ADD is_external_status_synced TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE slack_admin_token');
        $this->addSql('ALTER TABLE leave_request_type DROP slack_status_emoji');
        $this->addSql('ALTER TABLE user_slack_integration DROP slack_status_sync_enabled');
        $this->addSql('ALTER TABLE leave_request DROP is_external_status_synced');
    }
}
