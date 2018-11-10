<?php
namespace Azura\Http;

use Azura\Settings;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\UriInterface;
use Slim\Route;

class Router extends \Slim\Router
{
    /** @var Request */
    protected $current_request;

    /**
     * @return Request
     */
    public function getCurrentRequest(): Request
    {
        return $this->current_request;
    }

    /**
     * @param Request $current_request
     */
    public function setCurrentRequest(Request $current_request): void
    {
        $this->current_request = $current_request;
    }

    /**
     * Dynamically calculate the base URL the first time it's called, if it is at all in the request.
     *
     * @return UriInterface
     */
    public function getBaseUrl(): UriInterface
    {
        static $base_url;

        if (!$base_url)
        {
            // Use the current request's URI if applicable.
            if ($this->current_request instanceof Request) {
                $current_uri = $this->current_request->getUri();

                $ignored_hosts = ['nginx', 'localhost'];
                if (!in_array($current_uri->getHost(), $ignored_hosts)) {
                    $base_url = (new Uri())
                        ->withScheme($current_uri->getScheme())
                        ->withHost($current_uri->getHost())
                        ->withPort($current_uri->getPort());
                }
            }

            // Check the settings for a hard-coded base URI.
            $settings = $this->container[Settings::class];

            if (!$base_url && !empty($settings[Settings::BASE_URL])) {
                $base_url = new Uri($settings[Settings::BASE_URL]);
            }

            if (!($base_url instanceof Uri)) {
                throw new \Azura\Exception('Base URL could not be determined.');
            }
        }

        return $base_url;
    }

    /**
     * Compose a URL, returning an absolute URL (including base URL) if the current settings or this function's parameters
     * indicate an absolute URL is necessary
     *
     * @param  UriInterface|string $uri_raw
     * @param bool $absolute
     * @return UriInterface
     */
    public function getUri($uri_raw, $absolute = false): UriInterface
    {
        if ($uri_raw instanceof UriInterface) {
            $uri = $uri_raw;
        } else {
            $uri = new Uri($uri_raw);
        }

        if (!$absolute) {
            return $uri;
        }

        // URI has an authority solely because of its port.
        if ($uri->getAuthority() != '' && $uri->getHost() == '' && $uri->getPort()) {
            // Strip the authority from the URI, then reapply the port after the merge.
            $original_port = $uri->getPort();

            $new_uri = UriResolver::resolve($this->getBaseUrl(), $uri->withScheme('')->withHost('')->withPort(null));

            return $new_uri->withPort($original_port);
        }

        return UriResolver::resolve($this->getBaseUrl(), $uri);
    }

    /**
     * Simpler format for calling "named" routes with parameters.
     *
     * @param $route_name
     * @param array $route_params
     * @param array $query_params
     * @param boolean $absolute Whether to include the full URL.
     * @return UriInterface
     */
    public function named($route_name, $route_params = [], array $query_params = [], $absolute = false): UriInterface
    {
        return $this->getUri($this->relativePathFor($route_name, $route_params, $query_params), $absolute);
    }

    /**
     * Return a named route based on the current page and its route arguments.
     *
     * @param null $route_name
     * @param array $route_params
     * @param array $query_params
     * @param bool $absolute
     * @return string
     */
    public function fromHere($route_name = null, array $route_params = [], array $query_params = [], $absolute = false): string
    {
        $route = ($this->current_request instanceof Request)
            ? $this->current_request->getAttribute('route')
            : null;

        if ($route_name === null) {
            if ($route instanceof Route) {
                $route_name = $route->getName();
            } else {
                throw new \InvalidArgumentException('Cannot specify a null route name if no existing route is configured.');
            }
        }

        if ($route instanceof Route) {
            $route_params = array_merge($route->getArguments(), $route_params);
        }

        return $this->named($route_name, $route_params, $query_params, $absolute);
    }

    /**
     * Same as $this->fromHere(), but merging the current GET query parameters into the request as well.
     *
     * @param null $route_name
     * @param array $route_params
     * @param array $query_params
     * @param bool $absolute
     * @return string
     */
    public function fromHereWithQuery($route_name = null, array $route_params = [], array $query_params = [], $absolute = false): string
    {
        if ($this->current_request instanceof Request) {
            $query_params = array_merge($this->current_request->getQueryParams(), $query_params);
        }

        return $this->fromHere($route_name, $route_params, $query_params, $absolute);
    }
}
