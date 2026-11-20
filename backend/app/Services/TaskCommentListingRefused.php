<?php

namespace App\Services;

use RuntimeException;

/** The comments asked for are not there for the person (for example a task they cannot see). */
class TaskCommentListingRefused extends RuntimeException {}
