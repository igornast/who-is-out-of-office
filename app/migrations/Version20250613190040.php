<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250613190040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update User - add holiday calendar support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD holiday_calendar_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD CONSTRAINT FK_8D93D649608F7002 FOREIGN KEY (holiday_calendar_id) REFERENCES holiday_calendar (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8D93D649608F7002 ON user (holiday_calendar_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP FOREIGN KEY FK_8D93D649608F7002
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_8D93D649608F7002 ON user
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP holiday_calendar_id
        SQL);
    }
}
