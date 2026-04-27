<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427164234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add feed_item table and feed_last_seen_at column for user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE feed_item (id CHAR(36) NOT NULL, external_id VARCHAR(191) NOT NULL, title VARCHAR(500) NOT NULL, url VARCHAR(1000) NOT NULL, summary LONGTEXT DEFAULT NULL, content_type VARCHAR(32) NOT NULL, published_at DATETIME NOT NULL COMMENT \'UTC\', fetched_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_feed_item_external_id (external_id), INDEX idx_feed_item_published_at (published_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user ADD feed_last_seen_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP feed_last_seen_at');
        $this->addSql('DROP TABLE feed_item');
    }
}
