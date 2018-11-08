<?php
namespace Azura\Exception;

use Monolog\Logger;

class Bootstrap extends \Azura\Exception
{
    protected $logger_level = Logger::ALERT;
}