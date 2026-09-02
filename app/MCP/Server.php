<?php

namespace App\MCP;

use App\MCP\Tools\ListEventsTool;
use App\MCP\Tools\GetEventTool;
use App\MCP\Tools\ListTagsTool;
use App\MCP\Tools\SearchEventsTool;
use App\Setting;

class Server
{
    const PROTOCOL_VERSION = '2025-11-25';
    const SERVER_NAME = 'Meetable';
    const SERVER_VERSION = '1.0.0';

    /** @var MCPTool[] */
    private array $tools = [];

    public function __construct()
    {
        $this->register(new ListEventsTool());
        $this->register(new GetEventTool());
        $this->register(new ListTagsTool());
        $this->register(new SearchEventsTool());
    }

    private function register(MCPTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function initializeResult(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => ['tools' => (object)[]],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'title' => 'Meetable Event Calendar',
                'version' => self::SERVER_VERSION,
            ],
            'instructions' => $this->buildInstructions(),
        ];
    }

    private function buildInstructions(): string
    {
        $name = config('app.name');
        $description = Setting::value('home_meta_description');

        $instructions = "This is the {$name} event calendar.";

        if ($description) {
            $instructions .= ' ' . rtrim($description, '.') . '.';
        }

        $instructions .= ' Use the available tools to list, search, and retrieve events and tags.'
            . ' Events are sorted by date. All times are in the event\'s local timezone unless otherwise noted.';

        return $instructions;
    }

    public function toolsList(): array
    {
        $tools = [];
        foreach ($this->tools as $tool) {
            $tools[] = [
                'name' => $tool->name(),
                'title' => $tool->title(),
                'description' => $tool->description(),
                'inputSchema' => $tool->inputSchema(),
            ];
        }
        return ['tools' => $tools];
    }

    public function toolsCall(string $name, array $args): array
    {
        if (!isset($this->tools[$name])) {
            return [
                'content' => [['type' => 'text', 'text' => "Unknown tool: {$name}"]],
                'isError' => true,
            ];
        }

        try {
            return $this->tools[$name]->call($args);
        } catch (\Throwable $e) {
            return [
                'content' => [['type' => 'text', 'text' => 'Tool error: ' . $e->getMessage()]],
                'isError' => true,
            ];
        }
    }
}
