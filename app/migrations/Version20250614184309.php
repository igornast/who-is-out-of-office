<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250614184309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE user ADD working_days JSON DEFAULT NULL
    SQL);

        $this->addSql(<<<'SQL'
        UPDATE user SET working_days = '[1, 2, 3, 4, 5]' WHERE working_days IS NULL
    SQL);

        $this->addSql(<<<'SQL'
        ALTER TABLE user MODIFY working_days JSON NOT NULL
    SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP working_days
        SQL);
    }
}
