<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250115000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove finalVelocity and pointsAdded columns from time_period table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_period DROP COLUMN final_velocity');
        $this->addSql('ALTER TABLE time_period DROP COLUMN points_added');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_period ADD final_velocity DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE time_period ADD points_added INTEGER DEFAULT NULL');
    }
}
