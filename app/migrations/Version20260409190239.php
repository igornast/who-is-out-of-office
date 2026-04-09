<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409190239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add sort to leave_request_type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_request_type ADD sort INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_request_type DROP sort');
    }
}
