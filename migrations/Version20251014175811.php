<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014175811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('orders')) {
            $orders = $schema->getTable('orders');
            if ($orders->hasColumn('product_id') && $orders->hasColumn('quantity')) {
                return;
            }

            $this->addSql('SET FOREIGN_KEY_CHECKS=0');
            $this->addSql('DROP TABLE orders');
            $this->addSql('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->addSql('CREATE TABLE orders (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, quantity INT NOT NULL, total_price DOUBLE PRECISION NOT NULL, INDEX IDX_E52FFDEE4584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE4584665A FOREIGN KEY (product_id) REFERENCES products (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE4584665A');
        $this->addSql('DROP TABLE orders');
    }
}
