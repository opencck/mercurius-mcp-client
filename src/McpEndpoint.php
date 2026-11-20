<?php

declare(strict_types=1);

namespace Mercurius\Mcp;

/**
 * Where an MCP server answers: its name (for logs and sessions), its URL and the extra headers every request carries.
 * An application implements it on its own record of a server.
 */
interface McpEndpoint {
    public function endpointName(): string;

    public function endpointUrl(): string;

    /** @return array<non-empty-string, string> */
    public function endpointHeaders(): array;
}
