<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208172300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add leave request slack notification entity - to track messages sent and update if necessary';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE leave_request_slack_notification (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', leave_request_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', channel_id VARCHAR(100) NOT NULL, message_ts VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CF8EFF83F2E1C15D (leave_request_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE leave_request_slack_notification ADD CONSTRAINT FK_CF8EFF83F2E1C15D FOREIGN KEY (leave_request_id) REFERENCES leave_request (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_request_slack_notification DROP FOREIGN KEY FK_CF8EFF83F2E1C15D');
        $this->addSql('DROP TABLE leave_request_slack_notification');
    }
}
