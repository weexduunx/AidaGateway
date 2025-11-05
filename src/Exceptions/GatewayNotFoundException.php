<?php

namespace Weexduunx\AidaGateway\Exceptions;

use Exception;

class GatewayNotFoundException extends Exception
{
    protected $message = 'The specified gateway was not found.';
}
