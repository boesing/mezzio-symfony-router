<?php

declare(strict_types=1);

namespace WShafer\Mezzio\Symfony\Router\Cache;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use WShafer\Mezzio\Symfony\Router\Exception\InvalidCacheDirectoryException;
use WShafer\Mezzio\Symfony\Router\Exception\InvalidCacheException;
use WShafer\Mezzio\Symfony\Router\Exception\WriteCacheException;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Cache
{
    /**
     * @const string Configuration key used to enable/disable fastroute caching
     */
    public const CONFIG_CACHE_ENABLED = 'cache_enabled';

    /**
     * @const string Configuration key used to set the cache file path
     */
    public const CONFIG_CACHE_FILE = 'cache_file';

    protected $cacheEnabled = false;

    protected $cacheFile = null;

    protected $cache = null;

    protected $cacheNeedsUpdates = false;

    public function __construct(array $config = null)
    {
        $this->cacheEnabled = $config[self::CONFIG_CACHE_ENABLED] ?? false;
        $this->cacheFile = $config[self::CONFIG_CACHE_FILE] ?? null;
    }

    /**
     * @throws InvalidCacheException
     */
    public function populateCollectionFromCache(RouteCollection $collection): RouteCollection
    {
        if (!$this->cacheEnabled) {
            return $collection;
        }

        $routes = $this->fetchCache();

        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        return $collection;
    }

    public function add(string $name, Route $route): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        // Lets pre-compile this for caching
        $route->compile();
        $this->cache[$name] = $route;
        $this->cacheNeedsUpdates = true;
    }

    public function has($name)
    {
        return isset($this->cache[$name]);
    }

    public function writeCache(): bool
    {
        if (
            !$this->cacheEnabled
            || !$this->cacheNeedsUpdates
        ) {
            return true;
        }

        $cacheDir = dirname($this->cacheFile);

        if (!is_dir($cacheDir)) {
            throw new InvalidCacheDirectoryException(sprintf(
                'The cache directory "%s" does not exist',
                $cacheDir
            ));
        }

        if (!is_writable($cacheDir)) {
            throw new InvalidCacheDirectoryException(sprintf(
                'The cache directory "%s" is not writable',
                $cacheDir
            ));
        }

        $bytes = file_put_contents(
            $this->cacheFile,
            serialize($this->cache)
        );

        // @codeCoverageIgnoreStart
        if ($bytes === false) {
            throw new WriteCacheException('Unable to write cache file: ' . $this->cacheFile);
        }
        // @codeCoverageIgnoreEnd

        return true;
    }

    public function invalidateCacheFile(): void
    {
        if (
            !$this->cacheEnabled
            || empty($this->cacheFile)
            || !is_file($this->cacheFile)
        ) {
            return;
        }

        unlink($this->cacheFile);
    }

    public function isCacheEnabled()
    {
        return $this->cacheEnabled;
    }

    public function getCacheFile()
    {
        return $this->cacheFile;
    }

    public function doesCacheNeedsUpdate()
    {
        return $this->cacheNeedsUpdates;
    }

    public function fetchCache()
    {
        if (
            !$this->cacheEnabled
            || empty($this->cacheFile)
            || !is_file($this->cacheFile)
        ) {
            return [];
        }

        $this->cache = unserialize(file_get_contents($this->cacheFile));

        if (empty($this->cache)) {
            throw new InvalidCacheException('Unable to load route cache');
        }

        return $this->cache;
    }
}
