<?php

namespace App\Exceptions;

use RuntimeException;

/** An invoice or payment action that is not allowed in the record's current state (message is user-facing). */
class InvoiceStateException extends RuntimeException {}
