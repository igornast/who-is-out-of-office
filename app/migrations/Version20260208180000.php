<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Strip DC2Type column comments for DBAL 4.x compatibility';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holiday CHANGE id id CHAR(36) NOT NULL, CHANGE holiday_calendar_id holiday_calendar_id CHAR(36) NOT NULL, CHANGE date date DATE NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE holiday_calendar CHANGE id id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE invitation CHANGE id id CHAR(36) NOT NULL, CHANGE user_id user_id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE leave_request CHANGE id id CHAR(36) NOT NULL, CHANGE user_id user_id CHAR(36) NOT NULL, CHANGE approved_by_id approved_by_id CHAR(36) DEFAULT NULL, CHANGE leave_type leave_type CHAR(36) NOT NULL, CHANGE start_date start_date DATETIME NOT NULL, CHANGE end_date end_date DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE leave_request_slack_notification CHANGE id id CHAR(36) NOT NULL, CHANGE leave_request_id leave_request_id CHAR(36) DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE leave_request_type CHANGE id id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE id id CHAR(36) NOT NULL, CHANGE birth_date birth_date DATE DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE holiday_calendar_id holiday_calendar_id CHAR(36) DEFAULT NULL, CHANGE contract_started_at contract_started_at DATE DEFAULT NULL, CHANGE absence_balance_reset_day absence_balance_reset_day DATE NOT NULL');
        $this->addSql('ALTER TABLE user_slack_integration CHANGE user_id user_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL, CHANGE available_at available_at DATETIME NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holiday CHANGE date date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE holiday_calendar_id holiday_calendar_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE holiday_calendar CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE invitation CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE leave_request CHANGE start_date start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE end_date end_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE leave_type leave_type CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE approved_by_id approved_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE leave_request_slack_notification CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE leave_request_id leave_request_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE leave_request_type CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE available_at available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user CHANGE birth_date birth_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE contract_started_at contract_started_at DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE absence_balance_reset_day absence_balance_reset_day DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE holiday_calendar_id holiday_calendar_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user_slack_integration CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }
}
