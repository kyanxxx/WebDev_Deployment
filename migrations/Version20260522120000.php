<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Align legacy user table columns with the current User entity (Railway/phpMyAdmin imports).
 */
final class Version20260522120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate legacy user.role column to roles JSON and add missing User fields';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('user')) {
            return;
        }

        $table = $schema->getTable('user');

        if ($table->hasColumn('role') && !$table->hasColumn('roles')) {
            $this->addSql('ALTER TABLE user ADD roles JSON DEFAULT NULL');
            $this->addSql("UPDATE user SET roles = JSON_ARRAY('ROLE_ADMIN') WHERE role IN ('ROLE_ADMIN', 'admin', 'Admin')");
            $this->addSql("UPDATE user SET roles = JSON_ARRAY('ROLE_STAFF') WHERE role IN ('ROLE_STAFF', 'staff', 'Staff')");
            $this->addSql("UPDATE user SET roles = JSON_ARRAY('ROLE_USER') WHERE roles IS NULL");
            $this->addSql('ALTER TABLE user MODIFY roles JSON NOT NULL');
            $this->addSql('ALTER TABLE user DROP COLUMN role');
        } elseif (!$table->hasColumn('roles')) {
            $this->addSql('ALTER TABLE user ADD roles JSON NOT NULL');
            $this->addSql("UPDATE user SET roles = JSON_ARRAY('ROLE_USER')");
        }

        if (!$table->hasColumn('email')) {
            $this->addSql('ALTER TABLE user ADD email VARCHAR(255) DEFAULT NULL');
        }

        if (!$table->hasColumn('status')) {
            $this->addSql("ALTER TABLE user ADD status VARCHAR(20) DEFAULT 'active' NOT NULL");
        }

        if (!$table->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE user ADD created_at DATETIME DEFAULT NULL');
            $this->addSql('UPDATE user SET created_at = NOW() WHERE created_at IS NULL');
            $this->addSql('ALTER TABLE user MODIFY created_at DATETIME NOT NULL');
        }

        if (!$table->hasColumn('is_verified')) {
            $this->addSql('ALTER TABLE user ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL');
        }

        if (!$table->hasColumn('verification_token')) {
            $this->addSql('ALTER TABLE user ADD verification_token VARCHAR(100) DEFAULT NULL');
        }

        // Legacy/manual accounts without email verification should be able to log in
        $this->addSql('UPDATE user SET is_verified = 1 WHERE verification_token IS NULL AND is_verified = 0');
        $this->addSql("UPDATE user SET status = 'active' WHERE status IS NULL OR status = ''");
    }

    public function down(Schema $schema): void
    {
    }
}
