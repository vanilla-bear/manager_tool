<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250612145516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_BUG_KEY ON bug_mttr');
        $this->addSql('DROP INDEX idx_monthly_stats_month ON monthly_stats');
        $this->addSql('DROP INDEX UNIQ_EF8B34CC4B97E655 ON sprint');
        $this->addSql('ALTER TABLE sprint CHANGE completed_points completed_points DOUBLE PRECISION NOT NULL, CHANGE committed_points committed_points DOUBLE PRECISION NOT NULL, CHANGE planned_capacity_days planned_capacity_days DOUBLE PRECISION DEFAULT NULL, CHANGE devs_termines_count devs_termines_count INT NOT NULL, CHANGE devs_termines_points devs_termines_points DOUBLE PRECISION NOT NULL, CHANGE added_points_during_sprint added_points_during_sprint DOUBLE PRECISION NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX idx_monthly_stats_month ON monthly_stats (month)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BUG_KEY ON bug_mttr (bug_key)');
        $this->addSql('ALTER TABLE sprint CHANGE completed_points completed_points INT NOT NULL, CHANGE committed_points committed_points INT NOT NULL, CHANGE devs_termines_points devs_termines_points INT DEFAULT 0 NOT NULL, CHANGE devs_termines_count devs_termines_count INT DEFAULT 0 NOT NULL, CHANGE added_points_during_sprint added_points_during_sprint INT DEFAULT 0, CHANGE planned_capacity_days planned_capacity_days DOUBLE PRECISION NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EF8B34CC4B97E655 ON sprint (jira_id)');
    }
}
