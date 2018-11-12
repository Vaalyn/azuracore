<?php
namespace Azura\Middleware;

use Azura\Session;
use Azura\Http\Request;
use Azura\Http\Response;

/**
 * Inject the session object into the request.
 */
class EnableSession
{
    /** @var Session */
    protected $session;

    /**
     * EnableSession constructor.
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $next): Response
    {
        $request = $request->withAttribute(Request::ATTRIBUTE_SESSION, $this->session);

        return $next($request, $response);
    }
}
