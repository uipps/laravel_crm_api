<?php

namespace App\Exceptions;

use Exception;

class InvalidException extends Exception
{
    /**
     * Create a new authorization exception instance.
     *
     * @param  string|null  $message
     * @param  mixed  $code
     * @param  \Exception|null  $previous
     * @return void
     */
    public function __construct($message = null, $code = null, Exception $previous = null)
    {
        parent::__construct($message ?? 'internal error', 0, $previous);

        $this->code = $code ?: 0;
    }
}
