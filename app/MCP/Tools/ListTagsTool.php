<?php

namespace App\MCP\Tools;

use App\MCP\MCPTool;
use App\Tag;

class ListTagsTool implements MCPTool
{
    public function name(): string { return 'list_tags'; }

    public function title(): string { return 'List Tags'; }

    public function description(): string
    {
        return 'List all tags used by events, sorted by number of events descending.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
        ];
    }

    public function call(array $args): array
    {
        $tags = Tag::withCount('events')
            ->having('events_count', '>', 0)
            ->orderBy('events_count', 'desc')
            ->orderBy('tag', 'asc')
            ->get()
            ->map(fn($t) => ['name' => $t->tag, 'event_count' => $t->events_count])
            ->values()
            ->all();

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]],
            'isError' => false,
        ];
    }
}
