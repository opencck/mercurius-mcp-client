<?php

declare(strict_types=1);

namespace Mercurius\Mcp\Models;

/**
 * The contract by which an MCP server offers models to its client, on top of plain MCP: nothing in it is a method of its
 * own. A server declares itself in `capabilities.experimental["mercurius.models"]` of its answer to `initialize`, a
 * place the protocol keeps for additions; a client that does not know the key sees an ordinary server with tools, and a
 * server without the key is an ordinary server to us. The catalog is a tool of the server (by default `models_list`)
 * that returns `{"models": [...]}` as structured content; every model is called by a tool of the server with fixed
 * arguments of its kind.
 *
 * Version 1. A declaration of another version, or a catalog that does not read, leaves the server an ordinary one.
 */
final class ModelsContract {
    public const KEY = 'mercurius.models';
    public const VERSION = 1;
    public const CATALOG = 'models_list';

    /** what a model of a kind does; the arguments its tool takes are fixed by the kind (see the brief of the package) */
    public const KINDS = ['chat', 'image', 'voice', 'music', 'film', 'speech', 'vision', 'video'];
    /** what a model may read */
    public const INPUTS = ['text', 'image', 'audio', 'video', 'file'];
    /** the units of an estimated price */
    public const UNITS = ['call', 'second', 'mtok_in', 'mtok_out'];

    private const MODELS_MAX = 200;
    private const TOOL = '~^[A-Za-z0-9_.-]{1,64}\z~';
    /** a name, then one optional part after a slash (which may hold colons): what a category of the agent can hold */
    private const ID = '~^[A-Za-z0-9][A-Za-z0-9._-]{0,63}(?:/[A-Za-z0-9._:-]{1,63})?\z~';
    /** above this a price of one call or one million tokens is not a real one */
    private const MAX_USD = 1000.0;
    /** the widest window a model can claim */
    private const MAX_CONTEXT = 10_000_000;

    /**
     * The name of the catalog tool when the server declares the contract in a version this client knows; null — an
     * ordinary server.
     *
     * @param array<array-key, mixed> $capabilities the capabilities of the server
     */
    public static function catalogTool(array $capabilities): ?string {
        $experimental = isset($capabilities['experimental']) && is_array($capabilities['experimental']) ? $capabilities['experimental'] : [];
        $declared = $experimental[self::KEY] ?? null;
        if (!is_array($declared) || ($declared['version'] ?? null) !== self::VERSION) {
            return null;
        }
        /** @var mixed $tool */
        $tool = $declared['catalog'] ?? self::CATALOG;
        return is_string($tool) && preg_match(self::TOOL, $tool) === 1 ? $tool : null;
    }

    /**
     * The models of a catalog; a model that does not read is left out, a catalog with nothing to read is empty.
     *
     * @param array<array-key, mixed>|null $structured the structured content of the catalog tool's result
     * @param list<string> $tools the tools the server lists: a model is called by one of them, or it is left out
     * @return list<McpModel>
     */
    public static function models(?array $structured, array $tools): array {
        $rows = isset($structured['models']) && is_array($structured['models']) ? $structured['models'] : [];
        $models = [];
        $seen = [];
        /** @var mixed $row */
        foreach (array_slice(array_values($rows), 0, self::MODELS_MAX) as $row) {
            $model = is_array($row) ? self::model($row, $tools) : null;
            // one model may do several kinds (a Gemini model reads pictures and videos): it is one row per kind
            if ($model !== null && !isset($seen[$model->id . "
" . $model->kind])) {
                $seen[$model->id . "
" . $model->kind] = true;
                $models[] = $model;
            }
        }
        return $models;
    }

    /**
     * @param array<array-key, mixed> $row
     * @param list<string> $tools
     */
    private static function model(array $row, array $tools): ?McpModel {
        $id = $row['id'] ?? null;
        $kind = $row['kind'] ?? null;
        $tool = $row['tool'] ?? null;
        if (!is_string($id) || preg_match(self::ID, $id) !== 1 || !is_string($kind) || !in_array($kind, self::KINDS, true)
            || !is_string($tool) || !in_array($tool, $tools, true)) {
            return null;
        }
        $result = $row['result_tool'] ?? null;
        if ($result !== null && (!is_string($result) || !in_array($result, $tools, true))) {
            return null;
        }
        // a video is made by a start and a reading of its result: one without the other cannot be called
        if ($kind === 'film' && $result === null) {
            return null;
        }
        $input = [];
        /** @var mixed $item */
        foreach (is_array($row['input'] ?? null) ? $row['input'] : ['text'] as $item) {
            if (is_string($item) && in_array($item, self::INPUTS, true)) {
                $input[$item] = true;
            }
        }
        $price = null;
        /** @var mixed $priceRow */
        $priceRow = $row['price'] ?? null;
        if (is_array($priceRow) && is_string($priceRow['unit'] ?? null) && in_array($priceRow['unit'], self::UNITS, true)
            && (is_int($priceRow['usd'] ?? null) || is_float($priceRow['usd'] ?? null)) && is_finite((float) $priceRow['usd'])
            && $priceRow['usd'] >= 0 && $priceRow['usd'] <= self::MAX_USD) {
            $price = ['unit' => $priceRow['unit'], 'usd' => (float) $priceRow['usd']];
        }
        /** @var mixed $titleRaw */
        $titleRaw = $row['title'] ?? null;
        // the title goes into the list of models: no control, format or private characters
        $title = is_string($titleRaw) ? mb_substr(trim((string) preg_replace('/\p{C}+/u', ' ', $titleRaw)), 0, 120) : '';
        /** @var mixed $context */
        $context = $row['context'] ?? null;
        $window = is_int($context) && $context > 0 && $context <= self::MAX_CONTEXT ? $context : null;
        return new McpModel($id, $kind, $tool, $result, array_keys($input) ?: ['text'], ($row['default'] ?? false) === true, $title, $price, $window);
    }
}
