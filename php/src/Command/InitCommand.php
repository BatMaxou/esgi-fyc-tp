<?php

namespace App\Command;

use App\Enum\EmbeddingEnum;
use App\Service\Client\ChromaClient;
use App\Service\Client\FaissClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\Client\QdrantClient;

#[AsCommand(
    name: 'app:init',
)]
class InitCommand extends Command
{
    public function __construct(
        private readonly ChromaClient $chromaClient,
        private readonly QdrantClient $qdrantClient,
        private readonly FaissClient $faissClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->faissClient
            ->reset()
            ->initIndex();

        $io->success('Faiss index initialized.');

        $this->chromaClient
            ->reset()
            ->initTenant()
            ->initDatabase()
            ->initCollection(EmbeddingEnum::FYC);

        $io->success('Chroma collection initialized.');

        $this->qdrantClient
            ->removeCollection(EmbeddingEnum::FYC)
            ->initCollection(EmbeddingEnum::FYC);

        $io->success('Qdrant collection initialized.');

        return Command::SUCCESS;
    }
}
