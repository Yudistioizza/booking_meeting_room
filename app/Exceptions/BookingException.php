<?php

namespace App\Exceptions;

use Illuminate\Support\Collection;
use RuntimeException;

/** A booking rule was broken. The message is safe to show to the user. */
class BookingException extends RuntimeException
{
    public Collection $conflicts;

    public static function because(string $message, ?Collection $conflicts = null): self
    {
        $e = new self($message);
        $e->conflicts = $conflicts ?? collect();

        return $e;
    }
}
