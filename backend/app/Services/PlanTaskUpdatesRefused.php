<?php

namespace App\Services;

use RuntimeException;

/** The update of tasks was refused; the message is the text the AI client reads (a sentence, or the errors as JSON). */
class PlanTaskUpdatesRefused extends RuntimeException {}
