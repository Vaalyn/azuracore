<?php
namespace Azura\Http;

use Azura\View;
use Azura\Session;
use Monolog\Logger;

class ErrorHandler
{
    /** @var Logger */
    protected $logger;

    /** @var Session */
    protected $session;

    /** @var Router */
    protected $router;

    /** @var View */
    protected $view;

    /** @var bool */
    protected $show_detailed;

    /** @var bool */
    protected $return_json;

    /**
     * ErrorHandler constructor.
     *
     * @param Logger $logger
     * @param Router $router
     * @param Session $session
     * @param View $view
     * @param $show_detailed
     * @param $return_json
     */
    public function __construct(
        Logger $logger,
        Router $router,
        Session $session,
        View $view,
        $show_detailed,
        $return_json
    )
    {
        $this->logger = $logger;
        $this->router = $router;
        $this->session = $session;
        $this->view = $view;

        $this->show_detailed = $show_detailed;
        $this->return_json = $return_json;
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

        // Special handling for cURL (i.e. Liquidsoap) requests.
        $ua = $req->getHeaderLine('User-Agent');

        if (false !== stripos($ua, 'curl')) {
            $res->getBody()
                ->write('Error: '.$e->getMessage().' on '.$e->getFile().' L'.$e->getLine());

            return $res;
        }

        $show_detailed = $this->show_detailed;
        $return_json = ($req->isXhr() || $this->return_json);

        if ($e instanceof \Azura\Exception\NotLoggedIn) {
            $error_message = 'You must be logged in to access this page.';

            if ($return_json) {
                return $res
                    ->withStatus(403)
                    ->withJson(new Entity\Api\Error(403, $error_message));
            }

            // Redirect to login page for not-logged-in users.
            $this->session->flash('You must be logged in to access this page.', 'red');

            // Set referrer for login redirection.
            $referrer_login = $this->session->get('login_referrer');
            $referrer_login->url = $req->getUri()->getPath();

            return $res
                ->withStatus(302)
                ->withHeader('Location', $this->router->named('account:login'));
        }

        if ($e instanceof \Azura\Exception\PermissionDenied) {
            $error_message = 'You do not have permission to access this portion of the site.';

            if ($return_json) {
                return $res
                    ->withStatus(403)
                    ->withJson($this->_getErrorApiResponse(403, $error_message));
            }

            // Bounce back to homepage for permission-denied users.
            $this->session->flash('You do not have permission to access this portion of the site.',
                Session\Flash::ERROR);

            return $res
                ->withStatus(302)
                ->withHeader('Location', $this->router->named('home'));
        }

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
