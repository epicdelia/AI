<?php

namespace App\Domains\Agents\Mcp;

use App\Domains\Agents\Harness\ToolRegistry;
use InvalidArgumentException;
use Throwable;

/**
 * Model Context Protocol (MCP) Server.
 * Implements the standard JSON-RPC 2.0 MCP protocol specifications for agent-to-tool
 * interoperability, typed parameter validation, and structured error recovery.
 * Compatible with Claude Desktop, OpenAI Swarm, and frontier agentic harnesses.
 */
class McpServer
{
    public const PROTOCOL_VERSION = '2024-11-05';

    public function __construct(
        private ToolRegistry $tools
    ) {
    }

    /**
     * Handle incoming JSON-RPC 2.0 MCP request.
     *
     * @param  array{jsonrpc?: string, id?: mixed, method: string, params?: array}  $request
     * @return array{jsonrpc: string, id: mixed, result?: mixed, error?: array}
     */
    public function handle(array $request): array
    {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? '';
        $params = (array)($request['params'] ?? []);

        try {
            $result = match ($method) {
                'initialize' => $this->handleInitialize($params),
                'tools/list' => $this->handleToolsList($params),
                'tools/call' => $this->handleToolsCall($params),
                'ping' => [
                    'status' => 'pong',
                ],
                default => throw new InvalidArgumentException("Method not found: {$method}", -32601),
            };

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ];
        } catch (Throwable $e) {
            $code = $e->getCode() ?: -32603;

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => is_int($code) && $code !== 0 ? $code : -32603,
                    'message' => $e->getMessage(),
                    'data' => [
                        'hint' => $this->recoveryHint($method, $e->getMessage()),
                    ],
                ],
            ];
        }
    }

    private function handleInitialize(array $params): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
                'logging' => [],
            ],
            'serverInfo' => [
                'name' => 'creator-studio-mcp-server',
                'version' => '1.0.0',
            ],
        ];
    }

    private function handleToolsList(array $params): array
    {
        $definitions = ToolRegistry::definitions();
        $tools = [];

        foreach ($definitions as $def) {
            $tools[] = [
                'name' => $def['name'],
                'description' => $def['description'],
                'inputSchema' => $def['parameters'],
            ];
        }

        return [
            'tools' => $tools,
        ];
    }

    private function handleToolsCall(array $params): array
    {
        $name = (string)($params['name'] ?? '');
        $arguments = (array)($params['arguments'] ?? []);

        if (empty($name)) {
            throw new InvalidArgumentException('Tool name is required.', -32602);
        }

        try {
            $data = $this->tools->executeTool($name, $arguments);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                'isError' => false,
            ];
        } catch (Throwable $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Tool execution error: {$e->getMessage()}",
                    ],
                ],
                'isError' => true,
            ];
        }
    }

    private function recoveryHint(string $method, string $error): string
    {
        if (str_contains($error, 'Method not found')) {
            return 'Supported MCP methods are: initialize, tools/list, tools/call, ping.';
        }
        if (str_contains($error, 'Unknown tool')) {
            return 'Call tools/list to inspect available registered studio tools.';
        }

        return 'Check parameter types and required properties against tools/list JSON schema.';
    }
}
