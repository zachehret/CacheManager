<?php

namespace zachehret\CacheManager;

use Curl\Curl;

class CacheManager
{
    /**
     * @var $_CACHE string - The local cache directory - Can be overridden
     */
    public static string $_CACHE = __DIR__ . "../../.cache";


    /**
     * Determines if the cached file is expired.
     *
     * @param string $path - The path to the file relative to CacheManager::$_CACHE
     * @param int $maxAge - The max age of the file in seconds
     * @return bool - Returns true if the file is expired, does not exist, or is not accessible; Returns false otherwise.
     */
    public static function isExpired(string $path, int $maxAge) : bool {
        if(file_exists(CacheManager::$_CACHE . $path)) {
            $lastUpdatedTime = filemtime(CacheManager::$_CACHE . $path);
            if($lastUpdatedTime === false) {
                // Failure to read
                return true;
            }
            if(time() - $lastUpdatedTime > $maxAge) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public static function updateFile(string $path, string $content, int $maxAge = 0, bool $forceUpdate = false) : bool {
        if(! $forceUpdate || $maxAge !== 0) {
            // Not force updating.
            if(! self::isExpired($path, $maxAge)) {
                // File is not expired - don't update
                return false;
            }
        }

        return self::writeFile($path, $content);
    }

    /**
     * @param string $path - Path of the file relative to CacheManager::$_CACHE
     * @param string $url - The URL to fetch
     * @param int $maxAge - The max age of the file in seconds
     * @param bool $forceUpdate - If true, the file will be updated regardless of its age
     * @return string - Returns the content of the file, or an empty string if the file is not found or is expired.
     */
    public static function readOrUpdateFile(string $path, string $url, int $maxAge = 0, bool $forceUpdate = false) : string {
        if($forceUpdate || self::isExpired($path, $maxAge)) {
            // Force update or file is expired
            $curl = new Curl();
            $content = $curl->get($url);
            if($content === false) {
                // Failure to read
                return "";
            }
            self::writeFile($path, json_encode($content));
            return json_encode($content);
        } else {
            // File is not expired - read from cache
            return self::readFile($path);
        }
    }

    public static function readFile(string $path) : string{
        $file = fopen(CacheManager::$_CACHE . $path, "r");
        if($file === false) {
            return "";
        }
        $content = fread($file, filesize(CacheManager::$_CACHE . $path));
        fclose($file);
        return $content;
    }

    protected static function writeFile(string $path, string $content) : bool {
        $path = str_replace("\\", "/", $path);
        $standardizedCache = self::getStandardizedCachePath();
        $directorySplits = explode("/", $standardizedCache . $path);
        $directories = implode("/", array_splice($directorySplits, 0, sizeof($directorySplits) - 1));
        $filename = $directorySplits[sizeof($directorySplits) - 1];
        if( !is_dir($directories)) {
            if (mkdir($directories) === false) {
                return false;
            }
        }
        $resource = fopen($directories . "/" . $filename, "w");
        if($resource === false) {
            return false;
        }

        $writeResults = fwrite($resource, $content);

        fclose($resource);

        return !(($writeResults === false));
    }

    protected static function getStandardizedCachePath() : string {
        return str_replace("\\", "/", self::$_CACHE);
    }
}