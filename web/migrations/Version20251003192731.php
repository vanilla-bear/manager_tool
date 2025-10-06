<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251003192731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE velocity_prediction CHANGE predicted_velocity predicted_velocity DOUBLE PRECISION NOT NULL, CHANGE confidence confidence DOUBLE PRECISION NOT NULL, CHANGE trend trend DOUBLE PRECISION NOT NULL, CHANGE seasonality seasonality DOUBLE PRECISION NOT NULL, CHANGE team_capacity team_capacity DOUBLE PRECISION NOT NULL, CHANGE historical_average historical_average DOUBLE PRECISION NOT NULL, CHANGE completion_probability completion_probability DOUBLE PRECISION NOT NULL, CHANGE prediction_date prediction_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE target_sprint_start target_sprint_start DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE target_sprint_end target_sprint_end DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE is_active is_active TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE velocity_prediction CHANGE predicted_velocity predicted_velocity DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE confidence confidence DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE trend trend DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE seasonality seasonality DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE team_capacity team_capacity DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE historical_average historical_average DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE completion_probability completion_probability DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE prediction_date prediction_date DATETIME NOT NULL, CHANGE target_sprint_start target_sprint_start DATETIME DEFAULT NULL, CHANGE target_sprint_end target_sprint_end DATETIME DEFAULT NULL, CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL');
    }
}
