<?php
namespace Azura;

use GuzzleHttp\Client;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Handlers\NotFound;
use Slim\Handlers\NotAllowed;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\Http\EnvironmentInterface;
use Slim\Interfaces\RouterInterface;

/**
 * Slim's default Service Provider.
 */
class DefaultServicesProvider
{
    /**
     * Register default services.
     *
     * @param Container $container A DI container implementing ArrayAccess and container-interop.
     */
    public function register(Container $container)
    {
        if (!isset($container['environment'])) {
            $container['environment'] = function () {
                return new Environment($_SERVER);
            };

            $container->addAlias(Environment::class, 'environment');
            $container->addAlias(EnvironmentInterface::class, 'environment');
        }

        if (!isset($container['request'])) {
            $container['request'] = function (Container $container) {
                return Http\Request::createFromEnvironment($container->get('environment'));
            };

            $container->addAlias(Http\Request::class, 'request');
            $container->addAlias(ServerRequestInterface::class, 'request');
        }

        if (!isset($container['response'])) {
            $container['response'] = function (Container $container) {
                $headers = new Headers(['Content-Type' => 'text/html; charset=UTF-8']);
                $response = new Http\Response(200, $headers, null);

                return $response->withProtocolVersion($container->get('settings')['httpVersion']);
            };

            $container->addAlias(Http\Response::class, 'response');
            $container->addAlias(ResponseInterface::class, 'response');
        }

        if (!isset($container['router'])) {
            $container['router'] = function (Container $container) {
                $routerCacheFile = $container->get('settings')['routerCacheFile'];
                $router = new Http\Router();
                $router->setCacheFile($routerCacheFile);
                $router->setContainer($container);
                return $router;
            };

            $container->addAlias(Http\Router::class, 'router');
            $container->addAlias(RouterInterface::class, 'router');
        }

        if (!isset($container['foundHandler'])) {
            $container['foundHandler'] = function() {
                return new \Slim\Handlers\Strategies\RequestResponseArgs;
            };
        }

        if (!isset($container['phpErrorHandler'])) {
            $container->addAlias('phpErrorHandler', Http\ErrorHandler::class);
        }

        if (!isset($container['errorHandler'])) {
            $container->addAlias('errorHandler', Http\ErrorHandler::class);
        }

        if (!isset($container['notFoundHandler'])) {
            $container['notFoundHandler'] = function () {
                return new NotFound;
            };
        }

        if (!isset($container['notAllowedHandler'])) {
            $container['notAllowedHandler'] = function () {
                return new NotAllowed;
            };
        }

        if (!isset($container['callableResolver'])) {
            $container['callableResolver'] = function ($container) {
                return new Http\Resolver($container);
            };

            $container->addAlias(Http\Resolver::class, 'callableResolver');
            $container->addAlias(CallableResolverInterface::class, 'callableResolver');
        }

        if (!isset($container[Client::class])) {
            $container[Client::class] = function ($di) {
                $stack = \GuzzleHttp\HandlerStack::create();

                $stack->unshift(function (callable $handler) use ($di) {
                    return function (\Psr\Http\Message\RequestInterface $request, array $options) use ($handler, $di) {
                        $settings = $di['settings'];

                        if ($request->getUri()->getScheme() === 'https') {
                            $fetcher = new \ParagonIE\Certainty\RemoteFetch($settings['temp_dir']);
                            $latestCACertBundle = $fetcher->getLatestBundle();

                            $options['verify'] = $latestCACertBundle->getFilePath();
                        }

                        return $handler($request, $options);
                    };
                }, 'ssl_verify');

                $stack->push(\GuzzleHttp\Middleware::log(
                    $di[Logger::class],
                    new \GuzzleHttp\MessageFormatter('HTTP client {method} call to {uri} produced response {code}'),
                    Logger::DEBUG
                ));

                return new Client([
                    'handler' => $stack,
                    'http_errors' => false,
                    'timeout' => 3.0,
                ]);
            };
        }

        if (!isset($container[Cache::class])) {
            $container[Cache::class] = function (Container $di) {
                /** @var \Redis $redis */
                $redis = $di[\Redis::class];
                $redis->select(0);

                return new Cache($redis);
            };
        }

        if (!isset($container[Config::class])) {
            $container[Config::class] = function (Container $di) {
                $settings = $di['settings'];
                return new Config($settings['config_dir']);
            };
        }

        if (!isset($container[Http\ErrorHandler::class])) {
            $container[Http\ErrorHandler::class] = function (Container $di) {
                $settings = $di['settings'];

                return new Http\ErrorHandler(
                    $di[Logger::class],
                    $di['router'],
                    $di[Session::class],
                    $di[View::class],
                    (!$settings['is_production']),
                    ($settings['is_cli'] || $settings['environment'] === App::ENV_TESTING)
                );
            };
        }

        if (!isset($container[EventDispatcher::class])) {
            $container[EventDispatcher::class] = function (Container $di) {
                $dispatcher = new EventDispatcher($di);

                // Register application default events.
                $settings = $di->get('settings');

                if (file_exists($settings['config_dir'].'/events.php')) {
                    call_user_func($settings['config_dir'].'/events.php', $dispatcher);
                }

                return $dispatcher;
            };
        }

        if (!isset($container[Logger::class])) {
            $container[Logger::class] = function (Container $di) {
                $settings = $di['settings'];

                $logger = new Monolog\Logger($settings['name'] ?? 'app');
                $logging_level = $settings['is_production'] ? Logger::INFO : Logger::DEBUG;

                if ($settings['is_docker'] || $settings['is_cli']) {
                    $log_stderr = new \Monolog\Handler\StreamHandler('php://stderr', $logging_level, true);
                    $logger->pushHandler($log_stderr);
                }

                $log_file = new \Monolog\Handler\StreamHandler($settings['temp_dir'] . '/azuracast.log', $logging_level, true);
                $logger->pushHandler($log_file);

                return $logger;
            };
        }

        if (!isset($container[Middleware\EnableRouter::class])) {
            $container[Middleware\EnableRouter::class] = function(Container $di) {
                return new Middleware\EnableRouter(
                    $di['router']
                );
            };
        }

        if (!isset($container[Middleware\EnableSession::class])) {
            $container[Middleware\EnableSession::class] = function(Container $di) {
                return new Middleware\EnableSession(
                    $di[Session::class]
                );
            };
        }

        if (!isset($container[Middleware\EnableView::class])) {
            $container[Middleware\EnableView::class] = function(Container $di) {
                return new Middleware\EnableView(
                    $di[View::class]
                );
            };
        }

        if (!isset($container[Middleware\RateLimit::class])) {
            $container[Middleware\RateLimit::class] = function(Container $di) {
                return new Middleware\RateLimit(
                    $di[RateLimit::class]
                );
            };
        }

        if (!isset($container[Middleware\RemoveSlashes::class])) {
            $container[Middleware\RemoveSlashes::class] = function() {
                return new Middleware\RemoveSlashes;
            };
        }

        if (!isset($container[RateLimit::class])) {
            $container[RateLimit::class] = function($di) {
                /** @var \Redis $redis */
                $redis = $di[\Redis::class];
                $redis->select(3);

                return new RateLimit(
                    $redis,
                    $di['settings']
                );
            };
        }

        if (!isset($container[\Redis::class])) {
            $container[\Redis::class] = $container->factory(function (Container $di) {
                $settings = $di->get('settings');

                $redis_host = $settings['is_docker'] ? 'redis' : 'localhost';

                $redis = new \Redis();
                $redis->connect($redis_host, 6379, 15);
                return $redis;
            });
        }

        if (!isset($container[Session::class])) {
            $container[Session::class] = function (Container $di) {
                $settings = $di['settings'];

                if (App::ENV_TESTING !== $settings['environment']) {
                    ini_set('session.gc_maxlifetime', 86400);
                    ini_set('session.gc_probability', 1);
                    ini_set('session.gc_divisor', 100);

                    $redis_server = ($settings['is_docker']) ? 'redis' : 'localhost';
                    ini_set('session.save_handler', 'redis');
                    ini_set('session.save_path', 'tcp://' . $redis_server . ':6379?database=1');
                }

                return new Session;
            };
        }

        if (!isset($container[View::class])) {
            $container[View::class] = $container->factory(function (Container $di) {
                $settings = $di['settings'];

                $view = new View($settings['views_dir'], 'phtml');

                $view->registerFunction('service', function ($service) use ($di) {
                    return $di->get($service);
                });

                $view->registerFunction('escapeJs', function ($string) {
                    return json_encode($string);
                });

                /** @var Session $session */
                $session = $di[Session::class];

                $view->addData([
                    'settings' => $di['settings'],
                    'router' => $di['router'],
                    'request' => $di['request'],
                    'assets' => $di[Assets::class],
                    'flash' => $session->getFlash(),
                ]);

                /** @var EventDispatcher $dispatcher */
                $dispatcher = $di[EventDispatcher::class];
                $dispatcher->dispatch(Event\BuildView::NAME, new Event\BuildView($view));

                return $view;
            });
        }

    }
}
