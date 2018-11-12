<?php
namespace Azura;

class App extends \Slim\App
{
    /**
     * Initialize an application and DI container with the specified settings.
     *
     * @param array $values An array of DI container values to initially inject, including possible settings.
     * [
     *      'settings' => [
     *          'base_dir' => '/path', // REQUIRED!
     *          'temp_dir' => '/path', // Optional, defaults to $base_dir/../www_tmp
     *          'views_dir' => '/path', // Optional, defaults to $base_dir/templates
     *          'config_dir' => '/path', // Optional, defaults to $base_dir/config
     *          'is_docker' => false, // Default TRUE
     *          ... any other Slim or Azura specific settings.
     *      ],
     *      ... other DI container objects to override.
     * ]
     * @return self
     * @throws Exception\Bootstrap
     */
    public static function create(array $values): self
    {
        $settings = $values['settings'] ?? [];

        if (!isset($settings[Settings::BASE_DIR])) {
            throw new Exception\Bootstrap('No base directory specified!');
        }

        if (!($settings instanceof Settings)) {
            $settings = new Settings($settings);
        }

        if (!isset($settings[Settings::TEMP_DIR])) {
            $settings[Settings::TEMP_DIR] = dirname($settings[Settings::BASE_DIR]).'/www_tmp';
        }

        if (!isset($settings[Settings::CONFIG_DIR])) {
            $settings[Settings::CONFIG_DIR] = $settings[Settings::BASE_DIR].'/config';
        }

        if (!isset($settings[Settings::VIEWS_DIR])) {
            $settings[Settings::VIEWS_DIR] = $settings[Settings::BASE_DIR].'/templates';
        }

        if ($settings[Settings::IS_DOCKER]) {
            $_ENV = getenv();
        } else if (file_exists($settings[Settings::BASE_DIR].'/env.ini')) {
            $_ENV = array_merge($_ENV, parse_ini_file($settings[Settings::BASE_DIR].'/env.ini'));
        }

        if (!isset($settings[Settings::APP_ENV])) {
            $settings[Settings::APP_ENV] = $_ENV['APPLICATION_ENV'] ?? Settings::ENV_PRODUCTION;
        }

        if ($settings->isProduction()) {
            $settings[Settings::SLIM_ROUTER_CACHE_FILE] = $settings[Settings::TEMP_DIR].'/app_routes.cache.php';
        } else {
            $settings[Settings::SLIM_DISPLAY_ERROR_DETAILS] = true;
        }

        if (isset($_ENV['BASE_URL'])) {
            $settings[Settings::BASE_URL] = $_ENV['BASE_URL'];
        }

        if (file_exists($settings[Settings::CONFIG_DIR].'/settings.php')) {
            $app_settings = require($settings[Settings::CONFIG_DIR].'/settings.php');
            $settings->replace($app_settings);
        }

        $values['settings'] = $settings;

        // Apply PHP settings.
        ini_set('display_startup_errors',   !$settings->isProduction() ? 1 : 0);
        ini_set('display_errors',           !$settings->isProduction() ? 1 : 0);
        ini_set('log_errors',               1);
        ini_set('error_log',                $settings[Settings::IS_DOCKER] ? '/dev/stderr' : $settings[Settings::TEMP_DIR].'/php_errors.log');
        ini_set('error_reporting',          E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly',  1);
        ini_set('session.cookie_lifetime',  86400);
        ini_set('session.use_strict_mode',  1);

        // Disable sessions sending their own Cache-Control/Expires headers.
        session_cache_limiter('');

        $di = new Container($values);
        $app = new self($di);

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $di[EventDispatcher::class];
        $dispatcher->dispatch(Event\BuildRoutes::NAME, new Event\BuildRoutes($app));

        return $app;
    }
}