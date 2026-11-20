<?php

declare(strict_types=1);

namespace Mercurius\Mcp;

/**
 * What the client says in results and errors: English by default, an application passes its own language. The texts
 * reach a model, so they say what happened, not how to fix the code.
 */
final class McpTexts {
    public function __construct(
        /** a part of a result of a type the client does not know */
        public readonly string $unknownContent = 'unknown content',
        /** a binary part of a resource; %d — its size in bytes */
        public readonly string $binary = '[binary data, %d bytes]',
        /** the server answered with an error without a message */
        public readonly string $error = 'an error',
        /** the server answered with an error; its message follows */
        public readonly string $serverError = 'The MCP server answered with an error: ',
        /** the request failed; the kind of the failure follows */
        public readonly string $noAnswer = 'The MCP server did not answer: ',
        /** the server answered with an HTTP error; %d — its status */
        public readonly string $httpStatus = 'The MCP server answered with HTTP %d.',
        /** the server answered, but not to this request */
        public readonly string $noReply = 'The MCP server sent no answer to the request.',
    ) {}
}
