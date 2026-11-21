# mercurius-mcp-client

**English** | [Русский](README.ru.md) | [中文](README.cn.md)

An asynchronous Model Context Protocol (MCP) client for AMPHP: JSON-RPC 2.0 over Streamable HTTP (a JSON answer or
server-sent events), the tool list and resources by pages, a tool call with the files of its result.

Part of [Mercurius](https://github.com/opencck/mercurius) — an autonomous personal agent. The package does not depend on the Mercurius application and can be used on its own.

**Status:** used by Mercurius. The stdio transport (servers started in the agent's sandbox), tool filters, a circuit
breaker and the OSV check of packages stay in the application: they depend on its sandbox and policies.
`McpRpcClient` is the base for another transport: it needs only a session and one request in it.

**Stack:** PHP 8.2+, AMPHP v3, `amphp/http-client` v5.

## Use

```php
use Amp\Http\Client\HttpClientBuilder;
use Amp\NullCancellation;
use Mercurius\Mcp\HttpMcpClient;
use Mercurius\Mcp\McpEndpoint;

final class Server implements McpEndpoint {
    public function endpointName(): string { return 'github'; }
    public function endpointUrl(): string { return 'https://mcp.example.com/mcp'; }
    public function endpointHeaders(): array { return []; }
}

$client = new HttpMcpClient(HttpClientBuilder::buildDefault());
$tools = $client->tools(new Server(), $token, new NullCancellation());
[$text, $isError, $files] = $client->call(new Server(), $token, 'search', ['q' => 'amphp'], new NullCancellation()) + [2 => []];
```

The texts the client puts into results and errors are English; pass `new McpTexts(...)` with your own to the
constructor. A failure is a `RuntimeException` with such a text.

## Checks

`composer check` — PHPUnit, PHPStan (level max) and Psalm (level 1).

## License

[MIT](LICENSE)
