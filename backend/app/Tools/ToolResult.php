<?php

namespace App\Tools;

/**
 * What a tool gives back (task 34): data (a JSON answer), a text, or an error with a message the AI can act on. The
 * adapters turn it into their format: Laravel MCP (Response::json, ::text, ::error) or the text of a tool result for
 * Claude tool use.
 */
final class ToolResult
{
    public const JSON = 'json';

    public const TEXT = 'text';

    public const ERROR = 'error';

    private function __construct(
        public readonly string $kind,
        public readonly mixed $data,
    ) {}

    /** An answer of structured data (array or scalar). */
    public static function json(mixed $data): self
    {
        return new self(self::JSON, $data);
    }

    public static function text(string $text): self
    {
        return new self(self::TEXT, $text);
    }

    /** The message is the text the AI client reads, as it is today. */
    public static function error(string $message): self
    {
        return new self(self::ERROR, $message);
    }

    public function isError(): bool
    {
        return $this->kind === self::ERROR;
    }

    /** The result as one text: what Claude tool use gets as the content of the tool result. */
    public function toText(): string
    {
        return match ($this->kind) {
            self::JSON => json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $this->data,
        };
    }
}
