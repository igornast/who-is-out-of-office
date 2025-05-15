<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250514181658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE holiday (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', holiday_calendar_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', description VARCHAR(255) NOT NULL, date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DC9AB234608F7002 (holiday_calendar_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE holiday_calendar (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', country_code VARCHAR(4) NOT NULL, country_name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE leave_request (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', approved_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', status VARCHAR(30) NOT NULL, leave_type VARCHAR(30) NOT NULL, work_days INT NOT NULL, comment VARCHAR(255) DEFAULT NULL, start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7DC8F778A76ED395 (user_id), INDEX IDX_7DC8F7782D234F6A (approved_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(200) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, annual_leave_allowance INT NOT NULL, current_leave_balance INT NOT NULL, profile_image_url VARCHAR(255) DEFAULT NULL, birth_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_slack_integration (user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', slack_member_id VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_A27FC92042071BFC (slack_member_id), PRIMARY KEY(user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_session (sess_id VARBINARY(128) NOT NULL, sess_data LONGBLOB NOT NULL, sess_lifetime INT UNSIGNED NOT NULL, sess_time INT UNSIGNED NOT NULL, INDEX sess_lifetime_idx (sess_lifetime), PRIMARY KEY(sess_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_bin` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE holiday ADD CONSTRAINT FK_DC9AB234608F7002 FOREIGN KEY (holiday_calendar_id) REFERENCES holiday_calendar (id)');
        $this->addSql('ALTER TABLE leave_request ADD CONSTRAINT FK_7DC8F778A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE leave_request ADD CONSTRAINT FK_7DC8F7782D234F6A FOREIGN KEY (approved_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_slack_integration ADD CONSTRAINT FK_A27FC920A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO user (id, first_name, last_name, email, password, roles, annual_leave_allowance, current_leave_balance, profile_image_url, birth_date, created_at, updated_at) VALUES (UUID(), \'John\', \'Doe\', \'admin@ooo.com\', \'$argon2id$v=19$m=65536,t=4,p=1$hjMoe4D7E903U/y/oS8EaQ$kOPqvNjlxu4QxdSRzJUgxCgrA4oFZuIDuwDoQVkSSZw\', \'["ROLE_ADMIN"]\', 30, 30, \'https://raw.githubusercontent.com/fortrabbit/art/refs/heads/main/square-icon/png/fortrabbit-square-mark-colorful.png\', \'1991-01-01\', NOW(), NOW());');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE holiday DROP FOREIGN KEY FK_DC9AB234608F7002');
        $this->addSql('ALTER TABLE leave_request DROP FOREIGN KEY FK_7DC8F778A76ED395');
        $this->addSql('ALTER TABLE leave_request DROP FOREIGN KEY FK_7DC8F7782D234F6A');
        $this->addSql('ALTER TABLE user_slack_integration DROP FOREIGN KEY FK_A27FC920A76ED395');
        $this->addSql('DROP TABLE holiday');
        $this->addSql('DROP TABLE holiday_calendar');
        $this->addSql('DROP TABLE leave_request');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_slack_integration');
        $this->addSql('DROP TABLE user_session');
    }
}
