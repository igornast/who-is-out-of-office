<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260207000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add absence balance reset day to the User entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf(
            "ALTER TABLE user ADD absence_balance_reset_day DATE NOT NULL DEFAULT '%s-01-01' COMMENT '(DC2Type:date_immutable)'",
            date('Y'),
        ));
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP absence_balance_reset_day');
    }
}
