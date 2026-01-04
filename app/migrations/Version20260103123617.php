<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103123617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auto approved flag to the leave request entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_request ADD is_auto_approved TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_request DROP is_auto_approved');
    }
}
