<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240320000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add devs_termines_count field to sprint table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sprint ADD devs_termines_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sprint DROP devs_termines_count');
    }
} 