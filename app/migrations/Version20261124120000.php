<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20261124120000 extends AbstractMigration
{
    private const array DC2TYPE_COLUMNS = [
        'user' => [
            'manager_id' => ['CHAR(36) DEFAULT NULL', 'uuid'],
        ],
        'leave_period' => [
            'id' => ['CHAR(36) NOT NULL', 'uuid'],
            'user_id' => ['CHAR(36) NOT NULL', 'uuid'],
            'start_date' => ['DATE NOT NULL', 'date_immutable'],
            'end_date' => ['DATE NOT NULL', 'date_immutable'],
            'closed_at' => ['DATETIME DEFAULT NULL', 'datetime_immutable'],
            'created_at' => ['DATETIME NOT NULL', 'datetime_immutable'],
            'updated_at' => ['DATETIME NOT NULL', 'datetime_immutable'],
        ],
        'balance_ledger_entry' => [
            'id' => ['CHAR(36) NOT NULL', 'uuid'],
            'user_id' => ['CHAR(36) NOT NULL', 'uuid'],
            'leave_period_id' => ['CHAR(36) NOT NULL', 'uuid'],
            'actor_user_id' => ['CHAR(36) DEFAULT NULL', 'uuid'],
            'leave_request_id' => ['CHAR(36) DEFAULT NULL', 'uuid'],
            'created_at' => ['DATETIME NOT NULL', 'datetime_immutable'],
            'updated_at' => ['DATETIME NOT NULL', 'datetime_immutable'],
        ],
    ];

    private const array COLUMN_DEFAULTS = [
        'holiday' => ['is_global' => '1'],
        'holiday_calendar' => ['is_active' => '1'],
        'user' => [
            'theme_preference' => "'auto'",
            'palette_preference' => "'teal'",
            'is_two_factor_enabled' => '0',
        ],
    ];

    private const array GENERATED_INDEX_NAMES = [
        ['balance_ledger_entry', 'IDX_2AF691DF859B83FF', 'fk_ledger_actor'],
        ['balance_ledger_entry', 'IDX_2AF691DFF2E1C15D', 'fk_ledger_leave_request'],
        ['feed_item', 'UNIQ_9F8CCE499F75D7B0', 'uniq_feed_item_external_id'],
    ];

    public function getDescription(): string
    {
        return 'Strip leftover DC2Type column comments, align column defaults and index names with the mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET SESSION lock_wait_timeout = 30');

        foreach (self::DC2TYPE_COLUMNS as $table => $columns) {
            $modifications = [];
            foreach ($columns as $column => [$definition]) {
                $modifications[] = sprintf('MODIFY %s %s', $column, $definition);
            }
            $this->addSql(sprintf('ALTER TABLE %s %s', $table, implode(', ', $modifications)));
        }

        foreach (self::COLUMN_DEFAULTS as $table => $defaults) {
            $alterations = [];
            foreach ($defaults as $column => $default) {
                $alterations[] = sprintf('ALTER COLUMN %s SET DEFAULT %s', $column, $default);
            }
            $this->addSql(sprintf('ALTER TABLE %s %s', $table, implode(', ', $alterations)));
        }

        foreach (self::GENERATED_INDEX_NAMES as [$table, $generatedName, $name]) {
            if ($this->hasIndex($table, $generatedName) && !$this->hasIndex($table, $name)) {
                $this->addSql(sprintf('ALTER TABLE %s RENAME INDEX %s TO %s', $table, $generatedName, $name));
            }
        }

        $this->addSql('SET SESSION lock_wait_timeout = DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET SESSION lock_wait_timeout = 30');

        foreach (self::DC2TYPE_COLUMNS as $table => $columns) {
            $modifications = [];
            foreach ($columns as $column => [$definition, $type]) {
                $modifications[] = sprintf("MODIFY %s %s COMMENT '(DC2Type:%s)'", $column, $definition, $type);
            }
            $this->addSql(sprintf('ALTER TABLE %s %s', $table, implode(', ', $modifications)));
        }

        $this->addSql('SET SESSION lock_wait_timeout = DEFAULT');
    }

    public function isTransactional(): bool
    {
        return false;
    }

    private function hasIndex(string $table, string $index): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND LOWER(INDEX_NAME) = LOWER(?) LIMIT 1',
            [$table, $index],
        );
    }
}
