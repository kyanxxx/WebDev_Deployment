<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528105800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mobile_push_token column to user for customer push notifications';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('user')) {
            return;
        }

        $table = $schema->getTable('user');
        if (!$table->hasColumn('mobile_push_token')) {
            $this->addSql('ALTER TABLE user ADD mobile_push_token VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('user')) {
            return;
        }

        $table = $schema->getTable('user');
        if ($table->hasColumn('mobile_push_token')) {
            $this->addSql('ALTER TABLE user DROP mobile_push_token');
        }
    }
}
