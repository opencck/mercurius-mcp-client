# mercurius-mcp-client

[English](README.md) | [Русский](README.ru.md) | **中文**

> 本文由模型翻译，尚未经人工审校；如有出入，以英文版为准。

面向 AMPHP 的异步 Model Context Protocol (MCP) 客户端：基于 Streamable HTTP 的 JSON-RPC 2.0（JSON 应答或服务器发送事件），分页获取工具列表和资源，调用工具并取回其结果中的文件。

属于 [Mercurius](https://github.com/opencck/mercurius)——一个自主的个人智能体。该软件包不依赖 Mercurius 应用，可以单独使用。

**状态：** 已被 Mercurius 使用。stdio 传输（在智能体沙箱中启动的服务器）、工具过滤器、熔断器和软件包的 OSV 检查保留在应用中：它们依赖应用的沙箱和策略。
`McpRpcClient` 是其他传输的基类：它只需要一个会话和会话中的一次请求。

**技术栈：** PHP 8.2+、AMPHP v3、`amphp/http-client` v5。

## 使用

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

客户端放入结果和错误中的文本为英文；可向构造函数传入带有你自己文本的 `new McpTexts(...)`。失败表现为带有此类文本的 `RuntimeException`。

## 服务器提供的模型

服务器可以在普通 MCP 之上提供模型，通信中不增加任何自有内容：它在对 `initialize` 的应答中声明
`capabilities.experimental["mercurius.models"] = {"version": 1, "catalog": "models_list"}`，并用该工具列出模型。
`serverCapabilities()`（接口 `McpServerInfo`）给出服务器在会话开始时所说的内容；`Models\ModelsContract::catalogTool()`
读取声明，`ModelsContract::models()` 读取目录。没有声明的服务器就是普通服务器。

## 检查

`composer check` —— PHPUnit、PHPStan（level max）和 Psalm（level 1）。

## 许可证

[MIT](LICENSE)
