<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250115000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add velocity prediction table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE velocity_prediction (
            id INT AUTO_INCREMENT NOT NULL,
            predicted_velocity DOUBLE PRECISION NOT NULL DEFAULT 0,
            confidence DOUBLE PRECISION NOT NULL DEFAULT 0,
            trend DOUBLE PRECISION NOT NULL DEFAULT 0,
            seasonality DOUBLE PRECISION NOT NULL DEFAULT 0,
            team_capacity DOUBLE PRECISION NOT NULL DEFAULT 0,
            historical_average DOUBLE PRECISION NOT NULL DEFAULT 0,
            completion_probability DOUBLE PRECISION NOT NULL DEFAULT 0,
            risk_factors JSON NOT NULL,
            recommendations JSON NOT NULL,
            prediction_date DATETIME NOT NULL,
            target_sprint_start DATETIME DEFAULT NULL,
            target_sprint_end DATETIME DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            actual_velocity DOUBLE PRECISION DEFAULT NULL,
            accuracy DOUBLE PRECISION DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE velocity_prediction');
    }
}

