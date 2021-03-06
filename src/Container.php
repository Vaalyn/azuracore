<?php
namespace Azura;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Pimple\Container as PimpleContainer;
use Slim\Exception\ContainerValueNotFoundException;
use Slim\Exception\ContainerException as SlimContainerException;

/**
 * Class Container
 * @package Azura
 *
 * Reimplementation of Slim's default handler. Slim's default DI container is Pimple.
 *
 * Slim\App expects a container that implements Psr\Container\ContainerInterface
 * with these service keys configured and ready for use:
 *
 *  - settings: an array or instance of \ArrayAccess
 *  - environment: an instance of \Slim\Interfaces\Http\EnvironmentInterface
 *  - request: an instance of \Psr\Http\Message\ServerRequestInterface
 *  - response: an instance of \Psr\Http\Message\ResponseInterface
 *  - router: an instance of \Slim\Interfaces\RouterInterface
 *  - foundHandler: an instance of \Slim\Interfaces\InvocationStrategyInterface
 *  - errorHandler: a callable with the signature: function($request, $response, $exception)
 *  - notFoundHandler: a callable with the signature: function($request, $response)
 *  - notAllowedHandler: a callable with the signature: function($request, $response, $allowedHttpMethods)
 *  - callableResolver: an instance of \Slim\Interfaces\CallableResolverInterface
 */
class Container extends PimpleContainer implements ContainerInterface
{
    /**
     * @var array Mapping of service aliases
     */
    protected $aliases = [];

    /**
     * Create new container
     *
     * @param array $values
     * @throws Exception\Bootstrap
     */
    public function __construct(array $values = [])
    {
        if (!isset($values['settings'])) {
            throw new Exception\Bootstrap('No settings provided.');
        }

        parent::__construct($values);

        $defaultProvider = new DefaultServicesProvider();
        $defaultProvider->register($this);

        // Check for services.php file and include it if one exists.
        $config_dir = $this['settings']['config_dir'];

        if (file_exists($config_dir.'/services.php')) {
            call_user_func(include($config_dir.'/services.php'), $this);
        }
    }

    public function addAlias($alias_name, $original_name): void
    {
        $this->aliases[$alias_name] = $original_name;
    }

    public function removeAlias($alias_name): void
    {
        unset($this->aliases[$alias_name]);
    }

    /********************************************************************************
     * Methods to satisfy Psr\Container\ContainerInterface
     *******************************************************************************/

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     */
    public function get($id)
    {
        if (!$this->offsetExists($id)) {
            throw new ContainerValueNotFoundException(sprintf('Identifier "%s" is not defined.', $id));
        }
        try {
            return $this->offsetGet($id);
        } catch (\InvalidArgumentException $exception) {
            if ($this->exceptionThrownByContainer($exception)) {
                throw new SlimContainerException(
                    sprintf('Container error while retrieving "%s"', $id),
                    null,
                    $exception
                );
            } else {
                throw $exception;
            }
        }
    }

    /** @inheritdoc */
    public function offsetGet($id)
    {
        if (isset($this->aliases[$id])) {
            return parent::offsetGet($this->aliases[$id]);
        }

        return parent::offsetGet($id);
    }

    /**
     * Tests whether an exception needs to be recast for compliance with Container-Interop.  This will be if the
     * exception was thrown by Pimple.
     *
     * @param \InvalidArgumentException $exception
     *
     * @return bool
     */
    private function exceptionThrownByContainer(\InvalidArgumentException $exception)
    {
        $trace = $exception->getTrace()[0];

        return $trace['class'] === PimpleContainer::class && $trace['function'] === 'offsetGet';
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return boolean
     */
    public function has($id)
    {
        return $this->offsetExists($id);
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($id)
    {
        return isset($this->aliases[$id]) || parent::offsetExists($id);
    }

    /********************************************************************************
     * Magic methods for convenience
     *******************************************************************************/

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name)
    {
        return $this->has($name);
    }
}