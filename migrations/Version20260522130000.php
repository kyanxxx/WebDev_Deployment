<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove legacy stock column from products (no longer mapped on Products entity).
 */
final class Version20260522130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy stock column from products table';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('products')) {
            return;
        }

        $table = $schema->getTable('products');
        if ($table->hasColumn('stock')) {
            $this->addSql('ALTER TABLE products DROP COLUMN stock');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('products')) {
            return;
        }

        $table = $schema->getTable('products');
        if (!$table->hasColumn('stock')) {
            $this->addSql('ALTER TABLE products ADD stock INT NOT NULL DEFAULT 0');
        }
    }
}
