<?php

namespace App\Enums;

/**
 * How far a person reaches into a document (docs/dokumentenverwaltung.md §4). NONE, READ and EDIT are stored
 * (documents.project_access); MANAGE exists only as the access of a person (Document.viewerAccess). The cases
 * are ordered: a higher level includes the lower ones.
 */
enum DocumentAccess: string
{
    case NONE = 'none';
    case READ = 'read';
    case EDIT = 'edit';
    case MANAGE = 'manage';

    public function rank(): int
    {
        return match ($this) {
            self::NONE => 0,
            self::READ => 1,
            self::EDIT => 2,
            self::MANAGE => 3,
        };
    }

    public function atLeast(self $level): bool
    {
        return $this->rank() >= $level->rank();
    }

    /** The higher of two levels. */
    public function max(self $other): self
    {
        return $other->rank() > $this->rank() ? $other : $this;
    }
}
