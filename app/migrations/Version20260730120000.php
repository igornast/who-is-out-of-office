<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260730120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add leave_period and balance_ledger_entry tables with one opening balance row per existing user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE leave_period (
                id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
                user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
                start_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
                end_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
                allowance_granted INT NOT NULL,
                opening_balance INT NOT NULL,
                closing_balance INT DEFAULT NULL,
                closed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_leave_period_user_start (user_id, start_date),
                INDEX idx_leave_period_user_closed (user_id, closed_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE balance_ledger_entry (
                id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
                user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
                leave_period_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
                actor_user_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
                leave_request_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
                sequence INT NOT NULL,
                type VARCHAR(30) NOT NULL,
                source VARCHAR(30) NOT NULL,
                delta INT NOT NULL,
                balance_before INT NOT NULL,
                balance_after INT NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_ledger_user_created (user_id, created_at),
                INDEX idx_ledger_period (leave_period_id),
                UNIQUE INDEX uniq_ledger_user_sequence (user_id, sequence),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE leave_period ADD CONSTRAINT fk_leave_period_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE balance_ledger_entry ADD CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE balance_ledger_entry ADD CONSTRAINT fk_ledger_period FOREIGN KEY (leave_period_id) REFERENCES leave_period (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE balance_ledger_entry ADD CONSTRAINT fk_ledger_actor FOREIGN KEY (actor_user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE balance_ledger_entry ADD CONSTRAINT fk_ledger_leave_request FOREIGN KEY (leave_request_id) REFERENCES leave_request (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO leave_period (id, user_id, start_date, end_date, allowance_granted, opening_balance, closing_balance, closed_at, created_at, updated_at)
            SELECT UUID(), u.id, DATE_SUB(u.absence_balance_reset_day, INTERVAL 1 YEAR), u.absence_balance_reset_day,
                   u.annual_leave_allowance, u.current_leave_balance, NULL, NULL, NOW(), NOW()
            FROM user u
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO balance_ledger_entry (id, user_id, leave_period_id, actor_user_id, leave_request_id, sequence, type, source, delta, balance_before, balance_after, reason, created_at, updated_at)
            SELECT UUID(), u.id, lp.id, NULL, NULL, 1, 'opening_balance', 'migration',
                   u.current_leave_balance, 0, u.current_leave_balance, NULL, NOW(), NOW()
            FROM user u
            INNER JOIN leave_period lp ON lp.user_id = u.id
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE balance_ledger_entry');
        $this->addSql('DROP TABLE leave_period');
    }
}
