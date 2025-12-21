<?php

namespace App\Command;

use App\Enum\EmbeddingEnum;
use App\Service\Client\ChromaClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\Client\EmbedderClient;
use App\Service\Client\FaissClient;
use App\Service\Client\QdrantClient;

#[AsCommand(
    name: 'app:run',
)]
class RunCommand extends Command
{
    public function __construct(
        private readonly EmbedderClient $embedderClient,
        private readonly ChromaClient $chromaClient,
        private readonly QdrantClient $qdrantClient,
        private readonly FaissClient $faissClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $documentVectors = $this->embedderClient->getEmbededDocument('/pdf/sujet.pdf');

        foreach ($documentVectors as $document => $vector) {
            $this->chromaClient->insert($vector, $document);
            $this->qdrantClient->upsert(EmbeddingEnum::PDF, $vector, $document);
            $this->faissClient->insert($vector, $document);
        }

        $query = 'Quels languages de programmation doivent être utilisés ?';
        $vectors = $this->embedderClient->embed($query);

        $io->section('Chroma Results:');
        $chromaResults = $this->chromaClient->search($vectors, 2);

        $io->section('Qdrant Results:');
        $qdrantResults = $this->qdrantClient->search(EmbeddingEnum::PDF, $vectors, 2);

        $io->section('Faiss Results:');
        $faissResults = $this->faissClient->search($vectors);

        return Command::SUCCESS;
    }
}
