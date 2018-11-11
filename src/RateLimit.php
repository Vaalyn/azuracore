<?php
namespace Azura;

class RateLimit
{
    /** @var \Redis */
    protected $redis;

    /** @var array */
    protected $app_settings;

    public function __construct(\Redis $redis, $app_settings)
    {
        $this->redis = $redis;
        $this->app_settings = $app_settings;
    }

    /**
     * @param string $group_name
     * @param int $timeout
     * @param int $interval
     * @return bool
     * @throws Exception\RateLimitExceeded
     */
    public function checkRateLimit($group_name = 'default', $timeout = 5, $interval = 2)
    {
        if (Settings::ENV_TESTING === $this->app_settings[Settings::APP_ENV] || $this->app_settings[Settings::IS_CLI]) {
            return true;
        }

        $ip = $this->_getIp();
        $cache_name = 'rate_limit:'.$group_name.':'.str_replace(':', '.', $ip);

        $result = $this->redis->get($cache_name);

        if ($result !== false) {
            if ($result + 1 > $interval) {
                throw new Exception\RateLimitExceeded();
            } else {
                $this->redis->incr($cache_name);
            }
        } else {
            $this->redis->setex($cache_name, $timeout, 1);
        }

        return true;
    }

    protected function _getIp()
    {
        return $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_FORWARDED']
            ?? $_SERVER['HTTP_FORWARDED_FOR']
            ?? $_SERVER['HTTP_FORWARDED']
            ?? $_SERVER['REMOTE_ADDR']
            ?? null;
    }
}
