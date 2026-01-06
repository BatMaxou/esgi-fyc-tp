<?php

namespace App\Service\Prompt;

use App\Enum\PromptEnum;

class PromptBuilder
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    /**
     * @param array<string, string> $context
     */
    public function build(PromptEnum $type, array $context = []): string
    {
        $path = $this->basePath.'/'.$type->value;
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('Prompt file "%s" does not exist', $path));
        }

        $promptContent = file_get_contents($path);
        if (!$promptContent) {
            throw new \RuntimeException(sprintf('Prompt file "%s" is empty', $path));
        }

        return str_replace(
            array_keys($context),
            array_values($context),
            $promptContent
        );
    }
}

