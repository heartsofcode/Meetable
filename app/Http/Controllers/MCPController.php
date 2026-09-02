<?php

namespace App\Http\Controllers;

use App\MCP\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;

class MCPController extends BaseController
{
    private Server $server;

    public function __construct()
    {
        $this->server = new Server();
    }

    public function handle(Request $request)
    {
        if ($forbidden = $this->checkOrigin($request)) {
            return $forbidden;
        }

        $body = $request->json()->all();

        if (empty($body) || !isset($body['jsonrpc']) || $body['jsonrpc'] !== '2.0') {
            return $this->error(null, -32600, 'Invalid Request');
        }

        $method = $body['method'] ?? null;
        $id = $body['id'] ?? null;
        $params = $body['params'] ?? [];

        // Notifications have no id and require no response
        if ($id === null && $method !== null) {
            return response('', 202);
        }

        return match ($method) {
            'initialize'  => $this->jsonRpcResult($id, $this->server->initializeResult()),
            'tools/list'  => $this->jsonRpcResult($id, $this->server->toolsList()),
            'tools/call'  => $this->handleToolsCall($id, $params),
            default       => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    public function handleGet(Request $request)
    {
        if ($forbidden = $this->checkOrigin($request)) {
            return $forbidden;
        }

        // We don't support server-initiated SSE streams
        return response('', 405)->header('Allow', 'POST');
    }

    private function handleToolsCall($id, array $params)
    {
        $name = $params['name'] ?? null;
        $args = $params['arguments'] ?? [];

        if (!$name) {
            return $this->error($id, -32602, 'Missing required parameter: name');
        }

        $result = $this->server->toolsCall($name, $args);

        if ($result['isError']) {
            return $this->jsonRpcResult($id, $result);
        }

        return $this->jsonRpcResult($id, $result);
    }

    private function checkOrigin(Request $request)
    {
        $origin = $request->header('Origin');

        if ($origin === null) {
            return null; // No Origin header — allow (direct API calls, CLI tools)
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $originHost = parse_url($origin, PHP_URL_HOST);

        if ($appHost && $originHost && $originHost === $appHost) {
            return null; // Same host — allow
        }

        return response()->json(
            ['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Forbidden: invalid Origin']],
            403
        );
    }

    private function jsonRpcResult($id, array $result)
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ]);
    }

    private function error($id, int $code, string $message)
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => $code, 'message' => $message],
        ], 400);
    }
}
