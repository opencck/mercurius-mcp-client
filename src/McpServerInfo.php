<?php

declare(strict_types=1);

namespace Mercurius\Mcp;

use Amp\Cancellation;
use RuntimeException;
use SensitiveParameter;

/**
 * What a server said of itself when a session began. A client that does not implement it still lists and calls tools;
 * the application asks it only to learn whether a server offers more than tools (models, see Models\ModelsContract).
 */
interface McpServerInfo {
    /**
     * The `capabilities` of the server from its answer to `initialize`; a session is started when none is open.
     *
     * @return array<array-key, mixed>
     * @throws RuntimeException the server did not answer
     */
    public function serverCapabilities(McpEndpoint $server, #[SensitiveParameter] ?string $token, Cancellation $cancellation): array;
}
