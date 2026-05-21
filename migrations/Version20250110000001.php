<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to insert default menu products
 */
final class Version20250110000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert default menu products into the database';
    }

    public function up(Schema $schema): void
    {
        // Insert the 8 menu products only if they don't already exist
        $this->addSql("INSERT IGNORE INTO products (name, description, price) VALUES 
            ('Iced Latte', 'Cool and creamy espresso with milk over ice.', 4.50),
            ('Caramel Macchiato', 'Rich espresso layered with vanilla and caramel.', 5.20),
            ('Mocha', 'Espresso with chocolate and steamed milk.', 4.80),
            ('Cappuccino', 'Espresso topped with steamed milk foam.', 4.00),
            ('Americano', 'Smooth espresso diluted with hot water.', 3.80),
            ('Frappuccino', 'Blended ice coffee with sweet whipped cream.', 5.50),
            ('Vanilla Latte', 'Espresso mixed with steamed milk and vanilla syrup.', 4.70),
            ('Hazelnut Cold Brew', 'Chilled slow-brew coffee with hazelnut flavor.', 5.10)");
    }

    public function down(Schema $schema): void
    {
        // Remove the default menu products
        $this->addSql("DELETE FROM products WHERE name IN (
            'Iced Latte',
            'Caramel Macchiato',
            'Mocha',
            'Cappuccino',
            'Americano',
            'Frappuccino',
            'Vanilla Latte',
            'Hazelnut Cold Brew'
        )");
    }
}

