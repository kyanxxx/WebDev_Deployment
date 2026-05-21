<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209032719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add email column (nullable)
        $this->addSql('ALTER TABLE user ADD email VARCHAR(255) DEFAULT NULL');
        
        // Add status column with default
        $this->addSql('ALTER TABLE user ADD status VARCHAR(20) DEFAULT \'active\' NOT NULL');
        
        // Add created_at column (nullable first, then update existing records, then make NOT NULL)
        $this->addSql('ALTER TABLE user ADD created_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE user SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE user MODIFY created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP email, DROP created_at, DROP status');
    }
}
