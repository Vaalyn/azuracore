<?php
namespace Azura\Http;

use Monolog\Logger;

class ErrorHandler
{
    /** @var Logger */
    protected $logger;

    /** @var bool */
    protected $show_detailed = false;

    /** @var bool */
    protected $return_json = false;

    /**
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return bool
     */
    public function showDetailed(): bool
    {
        return $this->show_detailed;
    }

    /**
     * @param bool $show_detailed
     */
    public function setShowDetailed(bool $show_detailed): void
    {
        $this->show_detailed = $show_detailed;
    }

    /**
     * @return bool
     */
    public function returnJson(): bool
    {
        return $this->return_json;
    }

    /**
     * @param bool $return_json
     */
    public function setReturnJson(bool $return_json): void
    {
        $this->return_json = $return_json;
    }

    public function __invoke(Request $req, Response $res, \Throwable $e)
    {
        // Don't log errors that are internal to the application.
        $e_level = ($e instanceof \Azura\Exception)
            ? $e->getLoggerLevel()
            : Logger::ERROR;

        $this->logger->addRecord($e_level, $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
        ]);

        // Special handling for cURL requests.
        $ua = $req->getHeaderLine('User-Agent');

        if (false !== stripos($ua, 'curl')) {
            $res->getBody()
                ->write('Error: '.$e->getMessage().' on '.$e->getFile().' L'.$e->getLine());

            return $res;
        }

        $show_detailed = $this->show_detailed;
        $return_json = ($req->isXhr() || $this->return_json);

        if ($return_json) {
            $api_response = $this->_getErrorApiResponse(
                $e->getCode(),
                $e->getMessage(),
                ($show_detailed) ? $e->getTrace() : []
            );

            return $res
                ->withStatus(500)
                ->withJson($api_response);
        }

        if ($show_detailed) {
            // Register error-handler.
            $handler = new \Whoops\Handler\PrettyPageHandler;
            $handler->setPageTitle('An error occurred!');

            if ($e instanceof \Azura\Exception) {
                $extra_tables = $e->getExtraData();
                foreach($extra_tables as $legend => $data) {
                    $handler->addDataTable($legend, $data);
                }
            }

            $run = new \Whoops\Run;
            $run->pushHandler($handler);

            return $res->withStatus(500)->write($run->handleException($e));
        }

        return (new \Slim\Handlers\Error)($req, $res, $e);
    }

    protected function _getErrorApiResponse($code = 500, $message = 'General Error', $stack_trace = [])
    {
        $api = new \stdClass;

        $api->success = false;
        $api->code = (int)$code;
        $api->message = (string)$message;
        $api->stack_trace = (array)$stack_trace;

        return $api;
    }

}
