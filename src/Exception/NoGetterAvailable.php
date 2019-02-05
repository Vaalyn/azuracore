<?php
namespace Azura\Exception;

use Monolog\Logger;

class NoGetterAvailable extends \Azura\Exception
{
    protected $logger_level = Logger::INFO;
}
