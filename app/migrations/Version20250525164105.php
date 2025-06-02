<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250525164105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE invitation (id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', token VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_F11D61A25F37A13B (token), UNIQUE INDEX UNIQ_F11D61A2A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD is_active TINYINT(1) NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE invitation
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP is_active
        SQL);
    }
}
