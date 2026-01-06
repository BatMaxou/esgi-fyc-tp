<?php

namespace App\Command;

use App\Enum\EmbeddingEnum;
use App\Enum\PromptEnum;
use App\Service\Client\ChromaClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\Client\EmbedderClient;
use App\Service\Client\FaissClient;
use App\Service\Client\OllamaClient;
use App\Service\Client\QdrantClient;
use App\Service\Prompt\PromptBuilder;

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
        private readonly PromptBuilder $promptBuilder,
        private readonly OllamaClient $ollamaClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $query = $io->ask('Que voulez-vous rechercher ?');
        $vectors = $this->embedderClient->embed($query);

        $io->section('Chroma:');
        $chromaResults = $this->chromaClient->search($vectors, 2);
        $io->text(\sprintf('Chroma finished in %.2f ms', $chromaResults['time'] * 1000));

        $io->section('Qdrant:');
        $qdrantResults = $this->qdrantClient->search(EmbeddingEnum::FYC, $vectors, 2);
        $io->text(\sprintf('Qdrant finished in %.2f ms', $qdrantResults['time'] * 1000));

        $io->section('Faiss:');
        $faissResults = $this->faissClient->search($vectors, 2);
        $io->text(\sprintf('Faiss finished in %.2f ms', $faissResults['time'] * 1000));

        $io->section('Reponse:');
        $prompt = $this->buildPrompt($query, $chromaResults, $qdrantResults, $faissResults);
        $response = $this->ollamaClient->ask($prompt);
        $io->text($response);

        return Command::SUCCESS;
    }

    private function buildPrompt(string $query, array $chromaResults, array $qdrantResults, array $faissResults): string
    {
        return $this->promptBuilder->build(
            PromptEnum::FYC,
            [
                '{{ query }}' => $query,
                '{{ chroma_results }}' => implode("\n---------------\n", $chromaResults['results']),
                '{{ qdrant_results }}' => implode("\n---------------\n", $qdrantResults['results']),
                '{{ faiss_results }}' => implode("\n---------------\n", $faissResults['results']),
            ]
        );
    }
}
