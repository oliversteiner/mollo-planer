<?php

namespace App\Enums;

/**
 * Status of a milestone's release. Values match the GraphQL enum ReleaseStatus
 * and the planer-v2 file (release.status).
 */
enum ReleaseStatus: string
{
    case PLANNED = 'planned';
    case RELEASED = 'released';
}
