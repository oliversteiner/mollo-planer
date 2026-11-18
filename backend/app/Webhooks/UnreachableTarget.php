<?php

namespace App\Webhooks;

use RuntimeException;

/** The target of a webhook points where it must not (private network in production). */
final class UnreachableTarget extends RuntimeException {}
