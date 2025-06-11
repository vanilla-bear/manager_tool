<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240320000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create monthly_stats table for bug tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE monthly_stats (
            id INT AUTO_INCREMENT NOT NULL,
            month DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            bugs_count INT NOT NULL,
            delivered_tickets_count INT NOT NULL,
            synced_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('CREATE UNIQUE INDEX idx_monthly_stats_month ON monthly_stats (month)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE monthly_stats');
    }
} 