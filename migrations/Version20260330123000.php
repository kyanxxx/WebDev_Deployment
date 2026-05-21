<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create transactions table for transaction history';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('transactions')) {
            return;
        }

        $this->addSql('CREATE TABLE transactions (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_transactions_order_id FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_transactions_order_id');
        $this->addSql('DROP TABLE transactions');
    }
}

