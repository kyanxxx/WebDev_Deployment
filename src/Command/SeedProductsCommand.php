<?php

namespace App\Command;

use App\Entity\Products;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-products',
    description: 'Seed the database with default menu products',
)]
class SeedProductsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $products = [
            ['name' => 'Iced Latte', 'description' => 'Cool and creamy espresso with milk over ice.', 'price' => 4.50],
            ['name' => 'Caramel Macchiato', 'description' => 'Rich espresso layered with vanilla and caramel.', 'price' => 5.20],
            ['name' => 'Mocha', 'description' => 'Espresso with chocolate and steamed milk.', 'price' => 4.80],
            ['name' => 'Cappuccino', 'description' => 'Espresso topped with steamed milk foam.', 'price' => 4.00],
            ['name' => 'Americano', 'description' => 'Smooth espresso diluted with hot water.', 'price' => 3.80],
            ['name' => 'Frappuccino', 'description' => 'Blended ice coffee with sweet whipped cream.', 'price' => 5.50],
            ['name' => 'Vanilla Latte', 'description' => 'Espresso mixed with steamed milk and vanilla syrup.', 'price' => 4.70],
            ['name' => 'Hazelnut Cold Brew', 'description' => 'Chilled slow-brew coffee with hazelnut flavor.', 'price' => 5.10],
            ['name' => 'Thai Tea', 'description' => 'Black tea sweetened with condensed milk—iced or hot.', 'price' => 4.50],
            ['name' => 'Chai Tea', 'description' => 'Warm spices steeped with black tea and steamed milk.', 'price' => 4.50],
            ['name' => 'Matcha Latte', 'description' => 'Stone-ground green tea whisked with steamed milk.', 'price' => 5.50],
            ['name' => 'Hot Chocolate', 'description' => 'Rich cocoa with steamed milk and a cloud of whipped cream.', 'price' => 4.50],
            ['name' => 'Herbal Tea', 'description' => 'Caffeine-free botanical blend—ask for today\'s selection.', 'price' => 3.50],
            ['name' => 'Croissant', 'description' => 'Buttery, flaky layers baked fresh for the morning rush.', 'price' => 3.50],
            ['name' => 'Blueberry Muffin', 'description' => 'Moist muffin studded with wild blueberries.', 'price' => 3.00],
            ['name' => 'Chocolate Cookie', 'description' => 'Soft-baked cookie with melty dark chocolate chunks.', 'price' => 2.50],
            ['name' => 'Avocado Toast', 'description' => 'Smashed avocado on artisan toast with sea salt and citrus.', 'price' => 6.50],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($products as $productData) {
            // Check if product already exists
            $existingProduct = $this->entityManager->getRepository(Products::class)
                ->findOneBy(['name' => $productData['name']]);

            if ($existingProduct) {
                $skipped++;
                $io->note(sprintf('Product "%s" already exists, skipping...', $productData['name']));
                continue;
            }

            // Create new product
            $product = new Products();
            $product->setName($productData['name']);
            $product->setDescription($productData['description']);
            $product->setPrice($productData['price']);

            $this->entityManager->persist($product);
            $created++;
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully created %d products. %d products already existed and were skipped.', $created, $skipped));

        return Command::SUCCESS;
    }
}
