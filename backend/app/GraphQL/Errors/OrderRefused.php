<?php

namespace App\GraphQL\Errors;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use RuntimeException;

/**
 * A change of the order was refused (task 47): a condition of "Nach Version ordnen" is not met, the list of siblings
 * is not complete, or the plan changed since the caller looked. Nothing was written. The error carries a code and,
 * where it helps, the revision or the milestones concerned.
 */
final class OrderRefused extends RuntimeException implements ClientAware, ProvidesExtensions
{
    /**
     * @param  array<string, mixed>  $extensions
     */
    private function __construct(string $message, private readonly string $errorCode, private readonly array $extensions = [])
    {
        parent::__construct($message);
    }

    public static function revision(int $expected, int $current): self
    {
        return new self(
            __('ordering.revision', ['file' => $expected, 'project' => $current]),
            'ORDER_REVISION',
            ['currentRevision' => $current],
        );
    }

    /**
     * @param  list<array{id: int, title: string, version: string|null}>  $milestones
     */
    public static function condition(?string $condition, array $milestones = []): self
    {
        $list = implode(', ', array_map(fn (array $m): string => 'm-'.$m['id'].' '.$m['title'].' ('.($m['version'] ?? '–').')', $milestones));

        return new self(
            __('ordering.condition.'.$condition, ['list' => $list]),
            'ORDER_CONDITION',
            ['condition' => $condition, 'milestones' => $milestones],
        );
    }

    /**
     * @param  list<string>  $missing
     * @param  list<string>  $foreign
     */
    public static function siblings(array $missing, array $foreign): self
    {
        return new self(
            __('ordering.siblings', ['missing' => implode(', ', $missing) ?: '–', 'foreign' => implode(', ', $foreign) ?: '–']),
            'ORDER_SIBLINGS',
            ['missing' => $missing, 'foreign' => $foreign],
        );
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'order';
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        return ['code' => $this->errorCode, ...$this->extensions];
    }
}
