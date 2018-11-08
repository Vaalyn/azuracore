<?php
namespace Azura\Exception;

use Monolog\Logger;

class CsrfValidation extends \Azura\Exception
{
    protected $logger_level = Logger::INFO;
}