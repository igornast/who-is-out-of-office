<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ical_hash_salt column to user table for calendar subscription regeneration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD ical_hash_salt VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP ical_hash_salt');
    }
}
