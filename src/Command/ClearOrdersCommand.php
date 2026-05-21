<?php

namespace App\Command;

use App\Entity\Orders;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clear-orders',
    description: 'Delete all orders from the database',
)]
class ClearOrdersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force deletion without confirmation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $ordersRepository = $this->entityManager->getRepository(Orders::class);
        $allOrders = $ordersRepository->findAll();
        $orderCount = count($allOrders);

        if ($orderCount === 0) {
            $io->success('No orders found in the database. Nothing to delete.');
            return Command::SUCCESS;
        }

        if (!$input->getOption('force')) {
            $confirmed = $io->confirm(
                sprintf(
                    '⚠️  WARNING: This will delete ALL %d order(s) from the database. This action cannot be undone. Are you sure?',
                    $orderCount
                ),
                false
            );

            if (!$confirmed) {
                $io->info('Operation cancelled. No orders were deleted.');
                return Command::SUCCESS;
            }
        }

        // Delete all orders
        foreach ($allOrders as $order) {
            $this->entityManager->remove($order);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully deleted %d order(s) from the database.', $orderCount));

        return Command::SUCCESS;
    }
}
