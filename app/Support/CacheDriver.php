<?php

namespace App\Support;

use Predis\Client;
use Throwable;

/**
 * Picks the cache store when CACHE_STORE=auto: Redis when the server has it
 * (PHP Redis extension or Predis, and a Redis server answering PING), otherwise
 * the file cache. The answer is remembered for a short time in a small file so
 * Redis isn't probed on every request.
 */
class CacheDriver
{
    /** How long a detection result is trusted, in seconds. */
    public const TTL = 60;

    public static function resolve(array $redis, string $stateFile): string
    {
        $state = self::readState($stateFile);
        if ($state && $state['at'] > time() - self::TTL) {
            return $state['store'];
        }

        $store = self::redisAvailable($redis) ? 'redis' : 'file';
        self::writeState($stateFile, $store);

        return $store;
    }

    public static function stateFile(): string
    {
        return storage_path('framework/cache-driver.json');
    }

    /** Forget the remembered result (e.g. after Redis is installed or stops). */
    public static function forget(string $stateFile): void
    {
        @unlink($stateFile);
    }

    public static function hasClient(): bool
    {
        return extension_loaded('redis') || class_exists(Client::class);
    }

    /** True when a PHP Redis client is installed and the server answers PING. */
    public static function redisAvailable(array $redis): bool
    {
        return self::hasClient() && self::ping($redis) === true;
    }

    /**
     * Send PING over a plain socket (works the same for phpredis and Predis).
     * Returns true, or an error message.
     */
    public static function ping(array $redis, float $timeout = 0.3): bool|string
    {
        $conn = $redis['cache'] ?? $redis['default'] ?? [];
        $url = $conn['url'] ?? null;
        if ($url) {
            $parts = parse_url($url) ?: [];
            $conn = [
                'scheme' => $parts['scheme'] ?? 'tcp',
                'host' => $parts['host'] ?? '127.0.0.1',
                'port' => $parts['port'] ?? 6379,
                'username' => isset($parts['user']) ? urldecode($parts['user']) : null,
                'password' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
            ] + $conn;
        }
        $host = (string) ($conn['host'] ?? '127.0.0.1');
        $password = $conn['password'] ?? null;
        $password = in_array($password, [null, '', 'null'], true) ? null : (string) $password;
        $username = in_array($conn['username'] ?? null, [null, '', 'null'], true) ? null : (string) $conn['username'];

        if (str_starts_with($host, '/') || str_starts_with($host, 'unix:')) {
            $target = 'unix://'.preg_replace('#^unix:(//)?#', '', $host);
        } else {
            $tls = in_array($conn['scheme'] ?? 'tcp', ['tls', 'rediss'], true) || str_starts_with($host, 'tls://');
            $target = ($tls ? 'tls://' : 'tcp://').preg_replace('#^(tls|tcp)://#', '', $host).':'.(int) ($conn['port'] ?? 6379);
        }

        try {
            $socket = @stream_socket_client($target, $errno, $error, $timeout);
            if (! $socket) {
                return $error ?: 'Could not connect';
            }
            stream_set_timeout($socket, 0, (int) ($timeout * 1_000_000));
            if ($password !== null) {
                $args = $username !== null ? ['AUTH', $username, $password] : ['AUTH', $password];
                fwrite($socket, self::command($args));
                $reply = (string) fgets($socket);
                if (! str_starts_with($reply, '+OK')) {
                    fclose($socket);

                    return 'Authentication failed';
                }
            }
            fwrite($socket, self::command(['PING']));
            $reply = (string) fgets($socket);
            fclose($socket);

            return str_starts_with($reply, '+PONG') ? true : (trim($reply, "-\r\n ") ?: 'No reply');
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    protected static function command(array $args): string
    {
        return '*'.count($args)."\r\n".implode('', array_map(fn ($a) => '$'.strlen($a)."\r\n{$a}\r\n", $args));
    }

    protected static function readState(string $file): ?array
    {
        $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;

        return is_array($data) && in_array($data['store'] ?? null, ['redis', 'file'], true) ? $data : null;
    }

    protected static function writeState(string $file, string $store): void
    {
        $tmp = $file.'.'.getmypid().'.tmp';
        if (@file_put_contents($tmp, json_encode(['store' => $store, 'at' => time()])) !== false) {
            @rename($tmp, $file);
        }
    }
}
