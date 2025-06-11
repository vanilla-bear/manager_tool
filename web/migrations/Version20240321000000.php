<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240321000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bug_mttr table for tracking MTTR statistics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bug_mttr (
            id INT AUTO_INCREMENT NOT NULL,
            bug_key VARCHAR(255) NOT NULL,
            summary VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            a_faire_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            devs_termines_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            termine_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            current_status VARCHAR(50) NOT NULL,
            synced_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BUG_KEY ON bug_mttr (bug_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bug_mttr');
    }
} 