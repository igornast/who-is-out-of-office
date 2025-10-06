<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251004140758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contract start date to the User entity.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD celebrate_work_anniversary TINYINT(1) NOT NULL, ADD contract_started_at DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP celebrate_work_anniversary, DROP contract_started_at');
    }
}
