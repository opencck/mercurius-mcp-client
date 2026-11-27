<?php

declare(strict_types=1);

namespace Mercurius\Mcp\Test;

use Amp\Http\Client\HttpClient;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Mercurius\Mcp\HttpMcpClient;
use Mercurius\Mcp\McpEndpoint;
use Mercurius\Mcp\Models\McpModel;
use Mercurius\Mcp\Models\ModelsContract;

/**
 * A server offers models on top of plain MCP: the key in `experimental` and a catalog tool. A server without the key,
 * or with a version or a catalog this client cannot read, stays an ordinary server.
 */
final class ModelsContractTest extends AsyncTestCase {
    public function testTheDeclarationIsReadOnlyInItsVersion(): void {
        self::assertSame('models_list', ModelsContract::catalogTool(['experimental' => ['mercurius.models' => ['version' => 1]]]));
        self::assertSame('catalog', ModelsContract::catalogTool(['experimental' => ['mercurius.models' => ['version' => 1, 'catalog' => 'catalog']]]));
        self::assertNull(ModelsContract::catalogTool([]), 'an ordinary server');
        self::assertNull(ModelsContract::catalogTool(['tools' => ['listChanged' => true]]));
        self::assertNull(ModelsContract::catalogTool(['experimental' => ['mercurius.models' => ['version' => 2]]]), 'a version this client does not know');
        self::assertNull(ModelsContract::catalogTool(['experimental' => ['mercurius.models' => ['version' => 1, 'catalog' => 'bad name!']]]));
        self::assertNull(ModelsContract::catalogTool(['experimental' => ['mercurius.models' => true]]));
    }

    public function testTheCatalogKeepsOnlyModelsItCanCall(): void {
        $tools = ['image', 'video_start', 'video_result', 'chat'];
        $models = ModelsContract::models(['models' => [
            ['id' => 'img-1', 'kind' => 'image', 'tool' => 'image', 'default' => true, 'price' => ['unit' => 'call', 'usd' => 0.04], 'title' => 'Image one'],
            ['id' => 'img-1', 'kind' => 'image', 'tool' => 'image'],
            ['id' => 'img-1', 'kind' => 'vision', 'tool' => 'image'],
            ['id' => 'vid', 'kind' => 'film', 'tool' => 'video_start', 'result_tool' => 'video_result', 'input' => ['text', 'image', 'smell']],
            ['id' => 'vid-no-result', 'kind' => 'film', 'tool' => 'video_start'],
            ['id' => 'chat-1', 'kind' => 'chat', 'tool' => 'chat', 'context' => 128000],
            ['id' => 'x', 'kind' => 'teleport', 'tool' => 'image'],
            ['id' => 'y', 'kind' => 'image', 'tool' => 'not_listed'],
            ['id' => 'bad id', 'kind' => 'image', 'tool' => 'image'],
            'not an object',
        ]], $tools);

        self::assertSame(['img-1 image', 'img-1 vision', 'vid film', 'chat-1 chat'], array_map(static fn(McpModel $m): string => $m->id . ' ' . $m->kind, $models), 'once per kind');
        self::assertTrue($models[0]->default);
        self::assertSame(['unit' => 'call', 'usd' => 0.04], $models[0]->price);
        self::assertSame('video_result', $models[2]->resultTool);
        self::assertSame(['text', 'image'], $models[2]->input, 'what it reads, without words the contract does not know');
        self::assertSame(128000, $models[3]->context);
        self::assertSame([], ModelsContract::models(null, $tools));
        self::assertSame([], ModelsContract::models(['models' => 'no'], $tools));
    }

    public function testTheCapabilitiesOfAServerAreKeptFromItsStart(): void {
        $http = new McpHttpFake([200, ['content-type' => 'application/json'], '{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{},"experimental":{"mercurius.models":{"version":1}}}}}']);
        $client = new HttpMcpClient(new HttpClient($http, []));
        $endpoint = new class implements McpEndpoint {
            public function endpointName(): string {
                return 'm';
            }

            public function endpointUrl(): string {
                return 'https://mcp.example.com/mcp';
            }

            public function endpointHeaders(): array {
                return [];
            }
        };

        $capabilities = $client->serverCapabilities($endpoint, null, new NullCancellation());

        self::assertSame('models_list', ModelsContract::catalogTool($capabilities));
        self::assertStringContainsString('"capabilities":{}', $http->bodies[0] ?? '', 'the client asks nothing new of a server');
    }
}
