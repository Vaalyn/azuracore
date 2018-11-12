<?php
namespace Azura\Middleware;

use Azura\Http\Request;
use Azura\Http\Response;
use Azura\Http\Router;

/**
 * Set the current route on the URL object, and inject the URL object into the router.
 */
class EnableRouter
{
    /** @var Router */
    protected $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $next): Response
    {
        $this->router->setCurrentRequest($request);

        $request = $request->withAttribute(Request::ATTRIBUTE_ROUTER, $this->router);

        return $next($request, $response);
    }
}
