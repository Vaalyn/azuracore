<?php
namespace Azura\Http;

class Response extends \Slim\Http\Response
{
    const CACHE_ONE_MINUTE = 60;
    const CACHE_ONE_HOUR = 3600;
    const CACHE_ONE_DAY = 86400;
    const CACHE_ONE_MONTH = 2592000;
    const CACHE_ONE_YEAR = 31536000;

    /**
     * Send headers that expire the content immediately and prevent caching.
     *
     * @return self
     */
    public function withNoCache()
    {
        return $this
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', 0))
            ->withHeader('Cache-Control', 'private, no-cache, no-store')
            ->withHeader('X-Accel-Expires', '0'); // CloudFlare
    }

    /**
     * Send headers that expire the content in the specified number of seconds.
     *
     * @param int $seconds (Default is one year)
     * @return self
     */
    public function withCacheLifetime(int $seconds = self::CACHE_ONE_MONTH)
    {
        return $this
            ->withHeader('Pragma', '')
            ->withHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $seconds))
            ->withHeader('Cache-Control', 'public, must-revalidate, max-age=' . $seconds)
            ->withHeader('X-Accel-Expires', $seconds); // CloudFlare
    }

    /**
     * Returns whether the request has a "cache lifetime" assigned to it.
     *
     * @return bool
     */
    public function hasCacheLifetime(): bool
    {
        if ($this->hasHeader('Pragma')) {
            return (false === strpos($this->getHeaderLine('Pragma'), 'no-cache'));
        } else {
            return (false === strpos($this->getHeaderLine('Cache-Control'), 'no-cache'));
        }
    }

    /**
     * Stream the contents of a file directly through to the response.
     *
     * @param $file_path
     * @param null $file_name
     * @return self
     */
    public function renderFile($file_path, $file_name = null): self
    {
        set_time_limit(600);

        if ($file_name == null) {
            $file_name = basename($file_path);
        }

        $fh = fopen($file_path, 'rb');
        $stream = new \Slim\Http\Stream($fh);

        return $this
            ->withHeader('Pragma', 'public')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->withHeader('Content-Type', mime_content_type($file_path))
            ->withHeader('Content-Length', filesize($file_path))
            ->withHeader('Content-Disposition', 'attachment; filename=' . $file_name)
            ->withBody($stream);
    }

    /**
     * Write a string of file data to the response as if it is a file for download.
     *
     * @param string $file_data The body of the file contents.
     * @param string $content_type The HTTP header content-type (i.e. text/csv)
     * @param null $file_name
     * @return self
     */
    public function renderStringAsFile($file_data, $content_type, $file_name = null): self
    {
        $response = $this
            ->withHeader('Pragma', 'public')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->withHeader('Content-Type', $content_type);

        if ($file_name !== null) {
            $response = $response->withHeader('Content-Disposition', 'attachment; filename=' . $file_name);
        }

        return $response->write($file_data);
    }
}
