<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260410065814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD totp_secret LONGTEXT DEFAULT NULL, ADD is_two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD backup_codes JSON NULL');
        $this->addSql("UPDATE user SET backup_codes = '[]' WHERE backup_codes IS NULL");
        $this->addSql('ALTER TABLE user MODIFY backup_codes JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP totp_secret, DROP is_two_factor_enabled, DROP backup_codes');
    }
}
