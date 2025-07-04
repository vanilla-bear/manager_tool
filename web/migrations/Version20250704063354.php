<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250704063354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE team_member_profile (id INT AUTO_INCREMENT NOT NULL, team_member_id INT NOT NULL, productivity_stats JSON NOT NULL, quality_stats JSON NOT NULL, impact_stats JSON NOT NULL, collaboration_stats JSON NOT NULL, evolution_stats JSON NOT NULL, qualitative_feedback JSON NOT NULL, analysis_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', period_start DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', period_end DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_sync_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B9DEDC6CC292CD19 (team_member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE team_member_profile ADD CONSTRAINT FK_B9DEDC6CC292CD19 FOREIGN KEY (team_member_id) REFERENCES team_member (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team_member_profile DROP FOREIGN KEY FK_B9DEDC6CC292CD19');
        $this->addSql('DROP TABLE team_member_profile');
    }
}
