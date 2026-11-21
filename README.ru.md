# mercurius-mcp-client

[English](README.md) | **Русский** | [中文](README.cn.md)

Асинхронный клиент Model Context Protocol (MCP) для AMPHP: JSON-RPC 2.0 по Streamable HTTP (ответ JSON или события
server-sent events), список инструментов и ресурсы постранично, вызов инструмента с файлами результата.

Часть проекта [Меркурий (Mercurius)](https://github.com/opencck/mercurius) — автономного персонального агента. Пакет не зависит от приложения Меркурия и может использоваться отдельно.

**Статус:** используется Меркурием. Транспорт stdio (серверы, запущенные в песочнице агента), фильтры инструментов,
предохранитель и проверка пакетов по OSV остаются в приложении: они зависят от его песочницы и политик.
`McpRpcClient` — основа для другого транспорта: ему нужны только сессия и один запрос в ней.

**Стек:** PHP 8.2+, AMPHP v3, `amphp/http-client` v5.

## Использование

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

Тексты, которые клиент кладёт в результаты и ошибки, — на английском; свои передаются в конструктор как
`new McpTexts(...)`. Неудача — `RuntimeException` с таким текстом.

## Проверки

`composer check` — PHPUnit, PHPStan (уровень max) и Psalm (уровень 1).

## Лицензия

[MIT](LICENSE)
