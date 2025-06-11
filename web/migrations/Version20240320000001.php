<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240320000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sprint table for velocity tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sprint (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            jira_id INT NOT NULL,
            completed_points INT NOT NULL,
            committed_points INT NOT NULL,
            capacity_days DOUBLE PRECISION NOT NULL,
            planned_capacity_days DOUBLE PRECISION NOT NULL,
            start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            end_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            synced_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EF8B34CC4B97E655 ON sprint (jira_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sprint');
    }
} 