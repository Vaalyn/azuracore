<?php
namespace Azura;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
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
        $container->addAlias(Settings::class, 'settings');

        if (!isset($container['environment'])) {
            $container['environment'] = function () {
                if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                    $_SERVER['HTTPS'] = ('https' === strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']));
                }

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

                return $response->withProtocolVersion($container->get('settings')[Settings::SLIM_HTTP_VERSION]);
            };

            $container->addAlias(Http\Response::class, 'response');
            $container->addAlias(ResponseInterface::class, 'response');
        }

        if (!isset($container['router'])) {
            $container['router'] = function (Container $container) {
                $routerCacheFile = $container->get('settings')[Settings::SLIM_ROUTER_CACHE_FILE];
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
                            $fetcher = new \ParagonIE\Certainty\RemoteFetch($settings[Settings::TEMP_DIR]);
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
                return new Config($settings[Settings::CONFIG_DIR]);
            };
        }

        if (!isset($container[Connection::class])) {
            $container[Connection::class] = function(Container $di) {
                /** @var EntityManager $em */
                $em = $di[EntityManager::class];
                return $em->getConnection();
            };

            $container->addAlias('db', Connection::class);
        }

        if (!isset($container[Console\Application::class])) {
            $container[Console\Application::class] = function(Container $di) {
                $console = new Console\Application('Command Line Interface', '1.0.0');
                $console->setContainer($di);

                return $console;
            };
        }

        if (!isset($container[EntityManager::class])) {
            $container[EntityManager::class] = function (Container $di) {
                /** @var Settings $settings */
                $settings = $di->get('settings');

                $defaults = [
                    'autoGenerateProxies' => !$settings->isProduction(),
                    'proxyNamespace' => 'AppProxy',
                    'proxyPath' => $settings[Settings::TEMP_DIR] . '/proxies',
                    'modelPath' => $settings[Settings::BASE_DIR] . '/src/Entity',
                    'conn' => [
                        'host'      => $_ENV['MYSQL_HOST'] ?? 'mariadb',
                        'port'      => $_ENV['MYSQL_PORT'] ?? 3306,
                        'dbname'    => $_ENV['MYSQL_DATABASE'],
                        'user'      => $_ENV['MYSQL_USER'],
                        'password'  => $_ENV['MYSQL_PASSWORD'],
                        'driver'    => 'pdo_mysql',
                        'charset'   => 'utf8mb4',
                        'defaultTableOptions' => [
                            'charset' => 'utf8mb4',
                            'collate' => 'utf8mb4_general_ci',
                        ],
                        'driverOptions' => [
                            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_general_ci',
                        ],
                        'platform' => new \Doctrine\DBAL\Platforms\MariaDb1027Platform(),
                    ]
                ];

                if (!$settings[Settings::IS_DOCKER]) {
                    $defaults['conn']['host'] = $_ENV['db_host'] ?? 'localhost';
                    $defaults['conn']['port'] = $_ENV['db_port'] ?? '3306';
                    $defaults['conn']['dbname'] = $_ENV['db_name'] ?? 'azuracast';
                    $defaults['conn']['user'] = $_ENV['db_username'] ?? 'azuracast';
                    $defaults['conn']['password'] = $_ENV['db_password'];
                }

                if ($settings->isProduction()) {
                    /** @var \Redis $redis */
                    $redis = $di[\Redis::class];
                    $redis->select(2);

                    $cache = new Doctrine\Cache\Redis;
                    $cache->setRedis($redis);
                    $defaults['cache'] = $cache;
                } else {
                    $defaults['cache'] = new \Doctrine\Common\Cache\ArrayCache;
                }

                $app_options = $settings[Settings::DOCTRINE_OPTIONS] ?? [];
                $options = array_merge($defaults, $app_options);

                try {
                    // Fetch and store entity manager.
                    $config = new \Doctrine\ORM\Configuration;

                    $metadata_driver = $config->newDefaultAnnotationDriver($options['modelPath']);
                    $config->setMetadataDriverImpl($metadata_driver);

                    $repo_factory = new Doctrine\RepositoryFactory($di);
                    $config->setRepositoryFactory($repo_factory);

                    $config->setMetadataCacheImpl($options['cache']);
                    $config->setQueryCacheImpl($options['cache']);
                    $config->setResultCacheImpl($options['cache']);

                    $config->setProxyDir($options['proxyPath']);
                    $config->setProxyNamespace($options['proxyNamespace']);
                    $config->setAutoGenerateProxyClasses(\Doctrine\Common\Proxy\AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);
                    $config->setDefaultRepositoryClassName(Doctrine\Repository::class);

                    if (isset($options['conn']['debug']) && $options['conn']['debug']) {
                        $config->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger);
                    }

                    $config->addCustomNumericFunction('RAND', Doctrine\Functions\Rand::class);
                    
                    $em = EntityManager::create($options['conn'], $config, new \Doctrine\Common\EventManager);

                    return $em;
                } catch (\Exception $e) {
                    throw new Exception\Bootstrap($e->getMessage());
                }
            };

            $container->addAlias('em', EntityManager::class);
        }

        if (!isset($container[Http\ErrorHandler::class])) {
            $container[Http\ErrorHandler::class] = function (Container $di) {
                $error_handler = new Http\ErrorHandler($di[Logger::class]);

                /** @var Settings $settings */
                $settings = $di['settings'];

                $error_handler->setShowDetailed(!$settings->isProduction());
                $error_handler->setReturnJson($settings[Settings::IS_CLI] || $settings->isTesting());

                return $error_handler;
            };
        }

        if (!isset($container[EventDispatcher::class])) {
            $container[EventDispatcher::class] = function (Container $di) {
                $dispatcher = new EventDispatcher($di);

                $dispatcher->addSubscriber(new EventHandler);

                // Register application default events.
                $settings = $di['settings'];

                if (file_exists($settings[Settings::CONFIG_DIR].'/events.php')) {
                    call_user_func(include($settings[Settings::CONFIG_DIR].'/events.php'), $dispatcher);
                }

                return $dispatcher;
            };
        }

        if (!isset($container[Logger::class])) {
            $container[Logger::class] = function (Container $di) {
                /** @var Settings $settings */
                $settings = $di['settings'];

                $logger = new Logger($settings[Settings::APP_NAME] ?? 'app');
                $logging_level = $settings->isProduction() ? Logger::INFO : Logger::DEBUG;

                if ($settings[Settings::IS_DOCKER] || $settings[Settings::IS_CLI]) {
                    $log_stderr = new \Monolog\Handler\StreamHandler('php://stderr', $logging_level, true);
                    $logger->pushHandler($log_stderr);
                }

                $log_file = new \Monolog\Handler\StreamHandler($settings[Settings::TEMP_DIR] . '/app.log', $logging_level, true);
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

                $redis_host = $settings[Settings::IS_DOCKER] ? 'redis' : 'localhost';

                $redis = new \Redis();
                $redis->connect($redis_host, 6379, 15);
                return $redis;
            });
        }

        if (!isset($container[Session::class])) {
            $container[Session::class] = function (Container $di) {
                /** @var Settings $settings */
                $settings = $di['settings'];

                if ($settings->isTesting()) {
                    ini_set('session.gc_maxlifetime', 86400);
                    ini_set('session.gc_probability', 1);
                    ini_set('session.gc_divisor', 100);

                    $redis_server = ($settings[Settings::IS_DOCKER]) ? 'redis' : 'localhost';
                    ini_set('session.save_handler', 'redis');
                    ini_set('session.save_path', 'tcp://' . $redis_server . ':6379?database=1');
                }

                $app_prefix = 'APP_'.strtoupper(substr(md5($settings[Settings::BASE_DIR]), 0, 5));
                return new Session($app_prefix);
            };
        }

        if (!isset($container[View::class])) {
            $container[View::class] = $container->factory(function (Container $di) {
                $settings = $di['settings'];

                $view = new View($settings[Settings::VIEWS_DIR], 'phtml');

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
