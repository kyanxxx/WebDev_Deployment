<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed default menu products (must run after Version20251001103104 creates products table).
 */
final class Version20251001103105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert default menu products into the database';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT IGNORE INTO products (name, description, price, stock) VALUES
            ('Iced Latte', 'Cool and creamy espresso with milk over ice.', 4.50, 100),
            ('Caramel Macchiato', 'Rich espresso layered with vanilla and caramel.', 5.20, 100),
            ('Mocha', 'Espresso with chocolate and steamed milk.', 4.80, 100),
            ('Cappuccino', 'Espresso topped with steamed milk foam.', 4.00, 100),
            ('Americano', 'Smooth espresso diluted with hot water.', 3.80, 100),
            ('Frappuccino', 'Blended ice coffee with sweet whipped cream.', 5.50, 100),
            ('Vanilla Latte', 'Espresso mixed with steamed milk and vanilla syrup.', 4.70, 100),
            ('Hazelnut Cold Brew', 'Chilled slow-brew coffee with hazelnut flavor.', 5.10, 100)");
    }

    public function down(Schema $schema): void
    {
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
