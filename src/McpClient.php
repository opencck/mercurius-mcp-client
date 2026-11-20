<?php

declare(strict_types=1);

namespace Mercurius\Mcp;

use Amp\Cancellation;
use RuntimeException;
use SensitiveParameter;

/**
 * Talks MCP to a server: the list of its tools, a call of one, its resources and reading one of them.
 */
interface McpClient {
    /**
     * @return list<array{name: string, description: string, schema: array<array-key, mixed>, annotations: array<array-key, mixed>}>
     * @throws RuntimeException
     */
    public function tools(McpEndpoint $server, #[SensitiveParameter] ?string $token, Cancellation $cancellation): array;

    /**
     * @param array<string, mixed> $arguments
     * @return array{0: string, 1: bool, 2?: list<array{name: string, type: string, bytes: string}>} the text of the result,
     *         whether the server reports an error, and the files it returned (embedded resources, images, audio)
     * @throws RuntimeException
     */
    public function call(McpEndpoint $server, #[SensitiveParameter] ?string $token, string $tool, array $arguments, Cancellation $cancellation): array;

    /**
     * @return list<array{uri: string, name: string, description: string, mimeType: string}>
     * @throws RuntimeException
     */
    public function resources(McpEndpoint $server, #[SensitiveParameter] ?string $token, Cancellation $cancellation): array;

    /**
     * @return string the text of the resource; binary parts are named, not given
     * @throws RuntimeException
     */
    public function readResource(McpEndpoint $server, #[SensitiveParameter] ?string $token, string $uri, Cancellation $cancellation): string;
}
