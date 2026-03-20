<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319085943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add regional holiday support: is_global/counties on holiday, subdivision_code on user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holiday ADD is_global TINYINT NOT NULL DEFAULT 1, ADD counties JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD subdivision_code VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holiday DROP is_global, DROP counties');
        $this->addSql('ALTER TABLE user DROP subdivision_code');
    }
}
