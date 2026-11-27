<?php

declare(strict_types=1);

namespace Mercurius\Mcp\Models;

/**
 * A model an MCP server offers: what it does, by which tool it is called and what it reads.
 */
final class McpModel {
    /**
     * @param list<string> $input what the model reads (ModelsContract::INPUTS)
     * @param array{unit: string, usd: float}|null $price an estimate the server gives; null — none
     */
    public function __construct(
        public readonly string $id,
        /** one of ModelsContract::KINDS */
        public readonly string $kind,
        /** the tool that calls it (for a video: the one that starts it) */
        public readonly string $tool,
        /** the tool that reads the result of a long call; null — the call answers at once */
        public readonly ?string $resultTool,
        public readonly array $input,
        public readonly bool $default,
        public readonly string $title,
        public readonly ?array $price,
        /** the window in tokens of a chat model, when the server names it */
        public readonly ?int $context = null,
    ) {}
}
