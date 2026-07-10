<?php

namespace App\MCP;

interface MCPTool
{
    public function name(): string;
    public function title(): string;
    public function description(): string;
    public function inputSchema(): array;
    public function call(array $args): array;
}
