<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\Client\EmbedderClient;

#[AsCommand(
    name: 'app:tp',
)]
class TpCommand extends Command
{
    public function __construct(
        private readonly EmbedderClient $embedderClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $documentVectors = $this->embedderClient->getEmbededDocument();

        return Command::SUCCESS;
    }
}
