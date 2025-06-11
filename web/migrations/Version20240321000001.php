<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240321000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add added_points_during_sprint field to sprint table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sprint ADD added_points_during_sprint INT DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sprint DROP COLUMN added_points_during_sprint');
    }
} 