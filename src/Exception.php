<?php
namespace Azura;

use Monolog\Logger;

class Exception extends \Exception
{
    /** @var int The logging severity of the exception. */
    protected $logger_level = Logger::ERROR;

    /** @var array Any additional data that can be displayed in debugging. */
    protected $extra_data = [];

    /** @var array Additional data supplied to the logger class when handling the exception. */
    protected $logging_context = [];

    /** @var string|null */
    protected $formatted_message;

    /**
     * @param string $message
     */
    public function setMessage($message): void
    {
        $this->message = $message;
    }

    /**
     * Set a display-formatted message (if one exists).
     *
     * @param string|null $message
     */
    public function setFormattedMessage($message): void
    {
        $this->formatted_message = $message;
    }

    /**
     * @return string A display-formatted message, if one exists, or
     *                the regular message if one doesn't.
     */
    public function getFormattedMessage(): string
    {
        return $this->formatted_message ?? $this->message;
    }

    /**
     * @param int $logger_level
     */
    public function setLoggerLevel($logger_level): void
    {
        $this->logger_level = $logger_level;
    }

    /**
     * @return int
     */
    public function getLoggerLevel(): int
    {
        return $this->logger_level;
    }

    /**
     * @param string|int $legend
     * @param mixed $data
     */
    public function addExtraData($legend, $data): void
    {
        if (is_array($data)) {
            $this->extra_data[$legend] = $data;
        }
    }

    /**
     * @return array
     */
    public function getExtraData(): array
    {
        return $this->extra_data;
    }

    /**
     * @param string|int $key
     * @param mixed $data
     */
    public function addLoggingContext($key, $data): void
    {
        $this->logging_context[$key] = $data;
    }

    /**
     * @return array
     */
    public function getLoggingContext(): array
    {
        return $this->logging_context;
    }
}