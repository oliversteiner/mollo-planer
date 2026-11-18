<?php

namespace App\Webhooks;

use RuntimeException;

/** An announcement did not arrive; the message is the short code (never a header, body or secret). */
final class WebhookFailed extends RuntimeException {}
