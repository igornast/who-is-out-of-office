<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250816182346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add leave request type table, and update leave request types in existing table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE leave_request_type (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', is_affecting_balance TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, background_color VARCHAR(10) NOT NULL, border_color VARCHAR(10) NOT NULL, text_color VARCHAR(10) NOT NULL, icon VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE leave_request CHANGE leave_type leave_type CHAR(36) DEFAULT NULL');

        $this->addSql(
        'INSERT INTO leave_request_type (id, is_affecting_balance, name, background_color, border_color, text_color, icon, created_at, updated_at)
            SELECT UUID(), 1, leave_type, \'#ffffff\',\'#ffffff\',\'#ffffff\', \'🌴\', NOW(), NOW()
            FROM leave_request
            GROUP BY leave_type'
        );

        $this->addSql('UPDATE leave_request lr JOIN leave_request_type lrt ON lr.leave_type = lrt.name SET lr.leave_type = lrt.id');

        $this->addSql('ALTER TABLE leave_request CHANGE leave_type leave_type CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE leave_request ADD CONSTRAINT FK_7DC8F778E2BC4391 FOREIGN KEY (leave_type) REFERENCES leave_request_type (id)');
        $this->addSql('CREATE INDEX IDX_7DC8F778E2BC4391 ON leave_request (leave_type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_request DROP FOREIGN KEY FK_7DC8F778E2BC4391');
        $this->addSql('DROP TABLE leave_request_type');
        $this->addSql('DROP INDEX IDX_7DC8F778E2BC4391 ON leave_request');
        $this->addSql('ALTER TABLE leave_request CHANGE leave_type leave_type VARCHAR(30) NOT NULL');
    }
}
