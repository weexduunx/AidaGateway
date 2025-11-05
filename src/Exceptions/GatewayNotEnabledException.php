<?php

namespace Weexduunx\AidaGateway\Exceptions;

use Exception;

class GatewayNotEnabledException extends Exception
{
    protected $message = 'The specified gateway is not enabled in configuration.';
}
