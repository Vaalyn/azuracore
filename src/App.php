<?php
namespace Azura;

class App extends \Slim\App
{
    const ENV_DEVELOPMENT   = 'development';
    const ENV_TESTING       = 'testing';
    const ENV_PRODUCTION    = 'production';

    /**
     * Initialize an application and DI container with the specified settings.
     *
     * @param array $values An array of DI container values to initially inject, including possible settings.
     * [
     *      'settings' => [
     *          'base_dir' => '/path', // REQUIRED!
     *          'temp_dir' => '/path', // Optional
     *          'views_dir' => '/path', // Optional
     *          'translations_dir' => '/path', // Optional
     *          'config_dir' => '/path', // Optional
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

        if (!isset($settings['base_dir'])) {
            throw new Exception\Bootstrap('No base directory specified!');
        }

        $settings['temp_dir'] = $settings['temp_dir'] ??
            dirname($settings['base_dir']).'/www_tmp';

        $settings['config_dir'] = $settings['config_dir'] ??
            $settings['base_dir'].'/config';

        $settings['views_dir'] = $settings['views_dir'] ??
            $settings['base_dir'].'/templates';

        $settings['translations_dir'] = $settings['translations_dir'] ??
            $settings['base_dir'].'/translations';

        $settings['is_docker'] = $settings['is_docker'] ?? true;

        if ($settings['is_docker']) {
            $_ENV = getenv();
        } else if (file_exists($settings['base_dir'].'/env.ini')) {
            $_ENV = array_merge($_ENV, parse_ini_file($settings['base_dir'].'/env.ini'));
        }

        $settings['is_cli'] = ('cli' === PHP_SAPI);
        $settings['environment'] = $_ENV['APPLICATION_ENV'] ?? self::ENV_PRODUCTION;
        $settings['is_production'] = (self::ENV_PRODUCTION === $settings['environment']);

        if ($settings['is_production']) {
            $settings['routerCacheFile'] = $settings['temp_dir'].'/app_routes.cache.php';
        } else {
            $settings['displayErrorDetails'] = true;
        }

        if (file_exists($settings['base_dir'].'/config/settings.php')) {
            $app_settings = require($settings['base_dir'].'/config/settings.php');
            $settings = array_merge($settings, $app_settings);
        }

        $values['settings'] = $settings;

        // Apply PHP settings.
        ini_set('display_startup_errors',   !$settings['is_production'] ? 1 : 0);
        ini_set('display_errors',           !$settings['is_production'] ? 1 : 0);
        ini_set('log_errors',               1);
        ini_set('error_log',                $settings['is_docker'] ? '/dev/stderr' : $settings['temp_dir'].'/php_errors.log');
        ini_set('error_reporting',          E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly',  1);
        ini_set('session.cookie_lifetime',  86400);
        ini_set('session.use_strict_mode',  1);

        // Disable sessions sending their own Cache-Control/Expires headers.
        session_cache_limiter('');

        $di = new Container($values);
        return new self($di);
    }
}