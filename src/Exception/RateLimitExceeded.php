<?php
namespace Azura\Exception;

class RateLimitExceeded extends \Azura\Exception
{
    protected $logger_level = Logger::INFO;

    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'You have exceeded the rate limit for this application.';
        }

        parent::__construct($message, $code, $previous);
    }
}
