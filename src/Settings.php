<?php
namespace Azura;

use Slim\Collection;

class Settings extends Collection
{
    // Environments
    const ENV_DEVELOPMENT   = 'development';
    const ENV_TESTING       = 'testing';
    const ENV_PRODUCTION    = 'production';

    // AzuraCore settings values
    const APP_NAME          = 'name';
    const APP_ENV           = 'app_env';

    const BASE_DIR          = 'base_dir';
    const TEMP_DIR          = 'temp_dir';
    const CONFIG_DIR        = 'config_dir';
    const VIEWS_DIR         = 'views_dir';
    const DOCTRINE_OPTIONS  = 'doctrine_options';
    const IS_DOCKER         = 'is_docker';
    const IS_CLI            = 'is_cli';

    const BASE_URL          = 'base_url';
    const ASSETS_URL        = 'assets_url';

    // Slim PHP framework values
    const SLIM_HTTP_VERSION         = 'httpVersion';
    const SLIM_RESPONSE_CHUNK_SIZE  = 'responseChunkSize';
    const SLIM_OUTPUT_BUFFERING     = 'outputBuffering';
    const SLIM_ROUTE_BEFORE_MIDDLEWARE = 'determineRouteBeforeAppMiddleware';
    const SLIM_DISPLAY_ERROR_DETAILS = 'displayErrorDetails';
    const SLIM_ADD_CONTENT_LENGTH   = 'addContentLengthHeader';
    const SLIM_ROUTER_CACHE_FILE    = 'routerCacheFile';

    // Default settings
    protected $data = [
        self::APP_NAME      => 'Application',
        self::APP_ENV       => self::ENV_PRODUCTION,
        self::IS_PRODUCTION => true,

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