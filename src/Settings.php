<?php
namespace Azura;

use Slim\Collection;

class Settings extends Collection
{
    // Environments
    public const ENV_DEVELOPMENT   = 'development';
    public const ENV_TESTING       = 'testing';
    public const ENV_PRODUCTION    = 'production';

    // AzuraCore settings values
    public const APP_NAME          = 'name';
    public const APP_ENV           = 'app_env';

    public const BASE_DIR          = 'base_dir';
    public const TEMP_DIR          = 'temp_dir';
    public const CONFIG_DIR        = 'config_dir';
    public const VIEWS_DIR         = 'views_dir';
    public const DOCTRINE_OPTIONS  = 'doctrine_options';
    public const IS_DOCKER         = 'is_docker';
    public const IS_CLI            = 'is_cli';

    public const BASE_URL          = 'base_url';
    public const ASSETS_URL        = 'assets_url';

    // Slim PHP framework values
    public const SLIM_HTTP_VERSION         = 'httpVersion';
    public const SLIM_RESPONSE_CHUNK_SIZE  = 'responseChunkSize';
    public const SLIM_OUTPUT_BUFFERING     = 'outputBuffering';
    public const SLIM_ROUTE_BEFORE_MIDDLEWARE = 'determineRouteBeforeAppMiddleware';
    public const SLIM_DISPLAY_ERROR_DETAILS = 'displayErrorDetails';
    public const SLIM_ADD_CONTENT_LENGTH   = 'addContentLengthHeader';
    public const SLIM_ROUTER_CACHE_FILE    = 'routerCacheFile';

    // Default settings
    protected $data = [
        self::APP_NAME      => 'Application',
        self::APP_ENV       => self::ENV_PRODUCTION,

        self::IS_DOCKER     => true,
        self::IS_CLI        => ('cli' === PHP_SAPI),

        self::ASSETS_URL    => '/static',

        self::SLIM_HTTP_VERSION         => '1.1',
        self::SLIM_RESPONSE_CHUNK_SIZE  => 4096,
        self::SLIM_OUTPUT_BUFFERING     => false,
        self::SLIM_ROUTE_BEFORE_MIDDLEWARE => true,
        self::SLIM_DISPLAY_ERROR_DETAILS => false,
        self::SLIM_ADD_CONTENT_LENGTH   => false,
        self::SLIM_ROUTER_CACHE_FILE    => false,
    ];

    public function isProduction(): bool
    {
        if (isset($this->data[self::APP_ENV])) {
            return (self::ENV_PRODUCTION === $this->data[self::APP_ENV]);
        }
        return true;
    }

    public function isTesting(): bool
    {
        if (isset($this->data[self::APP_ENV])) {
            return (self::ENV_TESTING === $this->data[self::APP_ENV]);
        }
        return false;
    }

    public function isDocker(): bool
    {
        return (bool)$this->data[self::IS_DOCKER] ?? true;
    }

    public function isCli(): bool
    {
        return $this->data[self::IS_CLI] ?? ('cli' === PHP_SAPI);
    }
}