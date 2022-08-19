<?php

namespace NickyX3\Blitz\Exceptions;

use Illuminate\Http\Response;

class BlitzHandlerException extends BlitzException
{
    public function __construct($message, $errorLevel = 0)
    {
        parent::__construct($message, $errorLevel);
    }
    public function render ():Response
    {
        return $this->response;
    }
}
