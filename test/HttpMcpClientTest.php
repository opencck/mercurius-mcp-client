<?php

declare(strict_types=1);

namespace Mercurius\Mcp\Test;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Mercurius\Mcp\HttpMcpClient;
use Mercurius\Mcp\McpEndpoint;
use Mercurius\Mcp\McpTexts;
use RuntimeException;

use function Amp\ByteStream\buffer;

final class HttpMcpClientTest extends AsyncTestCase {
    /** @param array<non-empty-string, string> $headers */
    private static function endpoint(array $headers = []): McpEndpoint {
        return new class ($headers) implements McpEndpoint {
            /** @param array<non-empty-string, string> $headers */
            public function __construct(private readonly array $headers) {}

            public function endpointName(): string {
                return 'x';
            }

            public function endpointUrl(): string {
                return 'https://mcp.example.com/mcp';
            }

            public function endpointHeaders(): array {
                return $this->headers;
            }
        };
    }

    /** @param list<string> $events the data of each event, one per request */
    private static function events(array $events): string {
        return implode('', array_map(static fn(string $data): string => "event: message\ndata: " . $data . "\n\n", $events));
    }

    public function testToolsComeOverEventsInOneSession(): void {
        $http = new McpHttpFake([200, ['content-type' => 'text/event-stream', 'mcp-session-id' => 's1'], self::events([
            '{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18"}}',
            '{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"echo","description":"Echo","inputSchema":{"type":"object"},"annotations":{"readOnlyHint":true}},{"description":"no name"}]}}',
        ])]);
        $client = new HttpMcpClient(new HttpClient($http, []));

        $tools = $client->tools(self::endpoint(['x-team' => 'a']), 'tok', new NullCancellation());

        self::assertSame([['name' => 'echo', 'description' => 'Echo', 'schema' => ['type' => 'object'], 'annotations' => ['readOnlyHint' => true]]], $tools);
        self::assertCount(3, $http->bodies, 'initialize, initialized, tools/list');
        self::assertStringContainsString('"clientInfo":{"name":"mercurius"', $http->bodies[0] ?? '');
        self::assertSame('Bearer tok', $http->headers[2]['authorization'] ?? null);
        self::assertSame('s1', $http->headers[2]['mcp-session-id'] ?? null, 'the session of initialize goes on');
        self::assertSame('a', $http->headers[2]['x-team'] ?? null, 'the headers of the endpoint go with every request');
    }

    public function testACallGivesTextErrorAndFiles(): void {
        $http = new McpHttpFake([200, ['content-type' => 'application/json'], '[{"jsonrpc":"2.0","id":1,"result":{}},{"jsonrpc":"2.0","id":2,"result":{"isError":true,"content":[{"type":"text","text":"half"},{"type":"image","mimeType":"image/png","data":"iVBORw=="},{"kind":"weird"},{"type":"resource","resource":{"uri":"file:///tmp/a%20b.txt","mimeType":"text/plain","blob":"aGk="}}]}}]']);
        $client = new HttpMcpClient(new HttpClient($http, []), new McpTexts(unknownContent: 'неизвестно'));

        [$text, $error, $files] = $client->call(self::endpoint(), null, 'draw', ['a' => 1], new NullCancellation()) + [2 => []];

        self::assertSame("half\n(неизвестно)", $text, 'the texts are the client\'s');
        self::assertTrue($error);
        self::assertSame(['image-1.png', 'a b.txt'], array_column($files, 'name'));
        self::assertSame('hi', $files[1]['bytes'] ?? null);
    }

    public function testResourcesAreReadAndBinaryPartsNamed(): void {
        $http = new McpHttpFake([200, ['content-type' => 'text/event-stream'], self::events([
            '{"jsonrpc":"2.0","id":1,"result":{}}',
            '{"jsonrpc":"2.0","id":2,"result":{"resources":[{"uri":"docs://a","name":"A","mimeType":"text/plain"}],"contents":[{"uri":"docs://a","text":"hello"},{"uri":"docs://a","blob":"AAAA"}]}}',
        ])]);
        $client = new HttpMcpClient(new HttpClient($http, []));

        self::assertSame([['uri' => 'docs://a', 'name' => 'A', 'description' => '', 'mimeType' => 'text/plain']], $client->resources(self::endpoint(), null, new NullCancellation()));
        self::assertSame("hello\n[binary data, 3 bytes]", $client->readResource(self::endpoint(), null, 'docs://a', new NullCancellation()));
    }

    public function testFailuresAreSaidInTheClientsWords(): void {
        $texts = new McpTexts(serverError: 'ошибка сервера: ', httpStatus: 'HTTP %d', noAnswer: 'нет ответа: ', noReply: 'не тот ответ');
        $cases = [
            [[200, ['content-type' => 'application/json'], '[{"jsonrpc":"2.0","id":1,"result":{}},{"jsonrpc":"2.0","id":2,"error":{"message":"bad tool"}}]'], 'ошибка сервера: bad tool'],
            [[503, [], 'down'], 'HTTP 503'],
            [[0, [], ''], 'нет ответа: SocketException'],
            [[200, ['content-type' => 'application/json'], '{"jsonrpc":"2.0","id":7,"result":{}}'], 'не тот ответ'],
        ];
        foreach ($cases as [$answer, $message]) {
            $client = new HttpMcpClient(new HttpClient(new McpHttpFake($answer), []), $texts);
            try {
                $client->call(self::endpoint(), null, 't', [], new NullCancellation());
                self::fail('no error for ' . $message);
            } catch (RuntimeException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }

    public function testMessagesOfJsonAndOfEvents(): void {
        self::assertSame([['jsonrpc' => '2.0', 'id' => 1, 'result' => []]], HttpMcpClient::messages('{"jsonrpc":"2.0","id":1,"result":[]}', 'application/json'));
        self::assertSame([['id' => 1], ['id' => 2]], HttpMcpClient::messages('[{"id":1},{"id":2}]', 'application/json'), 'a batch');
        self::assertSame([['id' => 3]], HttpMcpClient::messages("event: message\ndata: {\"id\":3}\n\n", 'text/event-stream'));
    }
}

/** Answers every request the same; status 0 — the connection fails. */
final class McpHttpFake implements DelegateHttpClient {
    /** @var list<array<string, string>> */
    public array $headers = [];
    /** @var list<string> */
    public array $bodies = [];

    /** @param array{int, array<non-empty-string, string>, string} $answer */
    public function __construct(private readonly array $answer) {}

    public function request(Request $request, Cancellation $cancellation): Response {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', $values);
        }
        $this->headers[] = $headers;
        $this->bodies[] = buffer($request->getBody()->getContent());
        [$status, $answerHeaders, $body] = $this->answer;
        if ($status === 0) {
            throw new SocketException('refused');
        }
        return new Response('1.1', $status, null, $answerHeaders, $body, $request);
    }
}
