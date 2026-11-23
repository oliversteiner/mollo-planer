<?php

namespace App\Tools;

use InvalidArgumentException;

/**
 * Every tool, in one place (task 34). Both adapters read this list (the MCP server and the Claude tool use of the
 * assistant); a new tool is written in App\Tools and entered here, nowhere else.
 */
final class ToolRegistry
{
    /** @var list<class-string<Tool>> */
    public const TOOLS = [
        ListProjects::class,
        ListTemplates::class,
        ListUsers::class,
        ExportPlan::class,
        ListTasks::class,
        ImportPlan::class,
        UpdateTasks::class,
        AddComment::class,
        ListComments::class,
        CreateIdea::class,
        ListIdeas::class,
        GetProjectAiContext::class,
        GetProjectStatistics::class,
    ];

    /**
     * @return list<Tool>
     */
    public static function all(): array
    {
        return array_map(fn (string $class): Tool => app($class), self::TOOLS);
    }

    public static function find(string $name): ?Tool
    {
        foreach (self::all() as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * @throws InvalidArgumentException when no tool has this name
     */
    public static function get(string $name): Tool
    {
        return self::find($name) ?? throw new InvalidArgumentException("Unknown tool [{$name}].");
    }
}
