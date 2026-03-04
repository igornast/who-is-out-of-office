<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260302173033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add theme and palette preference fields to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD theme_preference VARCHAR(10) NOT NULL DEFAULT 'auto', ADD palette_preference VARCHAR(10) NOT NULL DEFAULT 'teal'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP theme_preference, DROP palette_preference');
    }
}
