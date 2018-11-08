<?php
namespace Azura\Exception;

use Monolog\Logger;

class NotFound extends \Azura\Exception
{
    protected $logger_level = Logger::INFO;
}