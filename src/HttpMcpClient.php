<?php

declare(strict_types=1);

namespace Mercurius\Mcp;

use Amp\Cancellation;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * MCP over Streamable HTTP: JSON-RPC 2.0 by POST, the answer as JSON or as a server-sent event. A session per use:
 * initialize, then the request.
 */
final class HttpMcpClient extends McpRpcClient {

    public function __construct(private readonly HttpClient $http, McpTexts $texts = new McpTexts(), string $clientName = 'mercurius') {
        parent::__construct($texts, $clientName);
    }

    protected function session(McpEndpoint $server, #[SensitiveParameter] ?string $token, Cancellation $cancellation): ?string {
        [$started, $session] = $this->send($server, $token, null, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => $this->initializeParams(),
        ], $cancellation);
        $this->started($server, isset($started['result']) && is_array($started['result']) ? $started['result'] : []);
        $this->send($server, $token, $session, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $cancellation);
        return $session;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<array-key, mixed>
     */
    protected function rpc(McpEndpoint $server, #[SensitiveParameter] ?string $token, object|string|null $session, string $method, array $params, Cancellation $cancellation): array {
        [$message] = $this->send($server, $token, is_string($session) ? $session : null, ['jsonrpc' => '2.0', 'id' => 2, 'method' => $method, 'params' => (object) $params], $cancellation);
        if (isset($message['error']) && is_array($message['error'])) {
            $text = isset($message['error']['message']) && is_string($message['error']['message']) ? $message['error']['message'] : $this->texts->error;
            throw new RuntimeException($this->texts->serverError . mb_substr($text, 0, 300));
        }
        return isset($message['result']) && is_array($message['result']) ? $message['result'] : [];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{array<array-key, mixed>, ?string} the JSON-RPC message answering this one, and the session id
     */
    private function send(McpEndpoint $server, ?string $token, ?string $session, array $body, Cancellation $cancellation): array {
        $request = new Request($server->endpointUrl(), 'POST', json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $request->setHeader('content-type', 'application/json');
        $request->setHeader('accept', 'application/json, text/event-stream');
        $request->setHeader('mcp-protocol-version', self::PROTOCOL);
        if ($token !== null) {
            $request->setHeader('authorization', 'Bearer ' . $token);
        }
        if ($session !== null) {
            $request->setHeader('mcp-session-id', $session);
        }
        foreach ($server->endpointHeaders() as $name => $value) {
            $request->setHeader($name, $value);
        }
        $request->setTransferTimeout(60);
        $request->setInactivityTimeout(60);
        // an answer may carry a file (an attachment of a letter, base64): up to 25 MB of it
        $request->setBodySizeLimit(36 * 1024 * 1024);
        try {
            $response = $this->http->request($request, $cancellation);
            $text = $response->getBody()->buffer($cancellation);
        } catch (Throwable $e) {
            throw new RuntimeException($this->texts->noAnswer . (new \ReflectionClass($e))->getShortName());
        }
        if ($response->getStatus() >= 400) {
            throw new RuntimeException(sprintf($this->texts->httpStatus, $response->getStatus()));
        }
        $session = $response->getHeader('mcp-session-id') ?? $session;
        if (!isset($body['id'])) {
            return [[], $session];
        }
        foreach (self::messages($text, $response->getHeader('content-type') ?? '') as $message) {
            if (($message['id'] ?? null) === $body['id']) {
                return [$message, $session];
            }
        }
        throw new RuntimeException($this->texts->noReply);
    }

    /** @return list<array<array-key, mixed>> JSON-RPC messages of a JSON body or of an event stream */
    public static function messages(string $text, string $contentType): array {
        $chunks = [$text];
        if (str_contains($contentType, 'text/event-stream')) {
            $chunks = [];
            foreach (preg_split('~\r?\n~', $text) ?: [] as $line) {
                if (str_starts_with($line, 'data:')) {
                    $chunks[] = substr($line, 5);
                }
            }
        }
        $messages = [];
        foreach ($chunks as $chunk) {
            /** @var mixed $decoded */
            $decoded = json_decode(trim($chunk), true, 64);
            if (is_array($decoded)) {
                if (array_is_list($decoded)) {
                    foreach (array_keys($decoded) as $k) {
                        if (isset($decoded[$k]) && is_array($decoded[$k])) {
                            $messages[] = $decoded[$k];
                        }
                    }
                } else {
                    $messages[] = $decoded;
                }
            }
        }
        return $messages;
    }
}
