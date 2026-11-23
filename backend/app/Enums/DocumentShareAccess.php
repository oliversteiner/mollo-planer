<?php

namespace App\Enums;

/** The level of a share to one person (document_shares.access). */
enum DocumentShareAccess: string
{
    case READ = 'read';
    case EDIT = 'edit';

    public function level(): DocumentAccess
    {
        return DocumentAccess::from($this->value);
    }
}
