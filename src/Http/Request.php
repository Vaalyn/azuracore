<?php
namespace Azura\Http;

use Azura\Exception;
use Azura\View;
use Azura\Session;
use Psr\Http\Message\UriInterface;
use Slim\Route;

class Request extends \Slim\Http\Request
{
    const ATTRIBUTE_ROUTER = 'router';
    const ATTRIBUTE_SESSION = 'session';
    const ATTRIBUTE_VIEW = 'view';

    /**
     * Get the current URI with redundant "http://url:80/" and "https://url:443/" filtered out.
     *
     * @return UriInterface
     */
    public function getFilteredUri(): UriInterface
    {
        if (($this->uri->getScheme() === 'http' && $this->uri->getPort() === 80)
            || ($this->uri->getScheme() === 'https' && $this->uri->getPort() === 443)) {
            return $this->uri->withPort(null);
        }

        return $this->uri;
    }

    /**
     * Detect if a parameter exists in the request.
     *
     * @param  string $key The parameter key.
     * @return bool Whether the key exists.
     */
    public function hasParam($key): bool
    {
        return ($this->getParam($key, null) !== null);
    }

    /**
     * Detect if an attribute exists in the request.
     *
     * @param $key
     * @return bool
     */
    public function hasAttribute($key): bool
    {
        return $this->attributes->has($key);
    }

    /**
     * Shortcut to indicate if a request is secure.
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return ('https' === $this->getUri()->getScheme());
    }

    /**
     * Pull the current route, if it's generated yet.
     *
     * @return Route
     * @throws Exception
     */
    public function getCurrentRoute(): Route
    {
        if ($this->hasAttribute('route')) {
            return $this->getAttribute('route');
        }

        throw new Exception("Route does not exist.");
    }

    /**
     * Get the View associated with the request, if it's set.
     * Set by @see \Azura\Middleware\EnableView
     *
     * @return View
     * @throws Exception
     */
    public function getView(): View
    {
        $view = $this->getAttribute(self::ATTRIBUTE_VIEW);
        if ($view instanceof View) {
            return $view;
        }

        throw new Exception('No view present in this request.');
    }

    /**
     * Get the application's Router.
     * Set by @see \Azura\Middleware\EnableRouter
     *
     * @return Router
     * @throws Exception
     */
    public function getRouter(): Router
    {
        return $this->_getAttributeOfType(self::ATTRIBUTE_ROUTER, Router::class);
    }

    /**
     * Get the current session manager associated with the request.
     *
     * @return Session
     * @throws Exception
     */
    public function getSession(): Session
    {
        return $this->_getAttributeOfType(self::ATTRIBUTE_SESSION, Session::class);
    }

    /**
     * Internal handler for retrieving attributes from the request and verifying their type.
     *
     * @param $attribute_name
     * @param $attribute_class
     * @return mixed
     * @throws Exception
     */
    protected function _getAttributeOfType($attribute_name, $attribute_class)
    {
        if ($this->hasAttribute($attribute_name)) {
            $attr = $this->getAttribute($attribute_name);
            if ($attr instanceof $attribute_class) {
                return $attr;
            }

            throw new Exception(sprintf('Attribute "%s" is not of type "%s".', $attribute_name, $attribute_class));
        }

        throw new Exception(sprintf('Attribute "%s" was not set.', $attribute_name));
    }
}
