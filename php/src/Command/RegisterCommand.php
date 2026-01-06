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
use App\Service\Prompt\PromptBuilder;

#[AsCommand(
    name: 'app:register',
)]
class RegisterCommand extends Command
{
    public function __construct(
        private readonly EmbedderClient $embedderClient,
        private readonly ChromaClient $chromaClient,
        private readonly QdrantClient $qdrantClient,
        private readonly FaissClient $faissClient,
        private readonly PromptBuilder $promptBuilder,
        private readonly string $basePath,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fileName = $io->ask('Quel fichier voulez-vous enregistrer ?');
        $filePath = \sprintf('%s/pdf/%s.pdf', $this->basePath, $fileName);
        if (!file_exists($filePath)) {
            throw new \RuntimeException(\sprintf('File "%s" does not exist', $filePath));
        }

        $io->section('Embed PDF ...');
        $documentVectors = $this->embedderClient->getEmbededDocument(\sprintf('/pdf/%s.pdf', $fileName));

        $io->section('Registering ...');
        $totalTimeChroma = 0;
        $totalTimeQdrant = 0;
        $totalTimeFaiss = 0;

        $io->progressStart(count($documentVectors));
        foreach ($documentVectors as $document => $vector) {
            $totalTimeChroma += $this->chromaClient->insert($vector, $document);
            $totalTimeQdrant += $this->qdrantClient->upsert(EmbeddingEnum::FYC, $vector, $document);
            $totalTimeFaiss += $this->faissClient->insert($vector, $document);
            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->section('Chroma :');
        $io->text(\sprintf('Documents registered into Chroma in %.2f ms', $totalTimeChroma * 1000));

        $io->section('Qdrant :');
        $io->text(\sprintf('Documents registered into Qdrant in %.2f ms', $totalTimeQdrant * 1000));

        $io->section('Faiss :');
        $io->text(\sprintf('Documents registered into Faiss in %.2f ms', $totalTimeFaiss * 1000));

        return Command::SUCCESS;
    }
}
