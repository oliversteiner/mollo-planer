<?php

namespace App\Services;

use RuntimeException;

/** The directory of people was asked something it does not answer (a project that is not there for the person). */
class UserDirectoryRefused extends RuntimeException {}
