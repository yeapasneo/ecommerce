<?php

namespace Tests\Feature;

use App\Support\CacheDriver;
use Tests\TestCase;

class CacheDriverTest extends TestCase
{
    protected string $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = sys_get_temp_dir().'/cache-driver-test-'.uniqid().'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->state);
        parent::tearDown();
    }

    protected function redis(int $port): array
    {
        return ['cache' => ['host' => '127.0.0.1', 'port' => $port, 'password' => null]];
    }

    public function test_falls_back_to_file_when_redis_is_not_reachable(): void
    {
        $this->assertSame('file', CacheDriver::resolve($this->redis(1), $this->state));
        $this->assertIsString(CacheDriver::ping($this->redis(1)));
    }

    public function test_result_is_remembered_then_rechecked(): void
    {
        file_put_contents($this->state, json_encode(['store' => 'redis', 'at' => time()]));
        $this->assertSame('redis', CacheDriver::resolve($this->redis(1), $this->state)); // trusted while fresh

        file_put_contents($this->state, json_encode(['store' => 'redis', 'at' => time() - CacheDriver::TTL - 1]));
        $this->assertSame('file', CacheDriver::resolve($this->redis(1), $this->state)); // stale: probed again

        CacheDriver::forget($this->state);
        $this->assertFileDoesNotExist($this->state);
    }

    public function test_uses_redis_when_a_server_answers(): void
    {
        $port = (int) env('REDIS_PORT', 6379);
        if (! CacheDriver::hasClient() || CacheDriver::ping($this->redis($port)) !== true) {
            $this->markTestSkipped('No Redis server on this machine.');
        }
        $this->assertSame('redis', CacheDriver::resolve($this->redis($port), $this->state));
    }

    public function test_failover_store_keeps_working_when_redis_stops(): void
    {
        config([
            'database.redis.cache.port' => 1, 'database.redis.cache.timeout' => 0.2, 'database.redis.cache.max_retries' => 0,
            'cache.stores.file.path' => sys_get_temp_dir().'/cache-driver-test-files',
        ]);
        $cache = cache()->store('redis_or_file');
        $cache->put('greeting', 'hello', 60);
        $this->assertSame('hello', $cache->get('greeting'));
    }

    public function test_session_driver_falls_back_to_the_database_when_redis_stops(): void
    {
        config([
            'database.redis.session.port' => 1, 'database.redis.session.timeout' => 0.2, 'database.redis.session.max_retries' => 0,
            'cache.stores.database.connection' => null,
        ]);
        $handler = app('session')->driver('redis_or_database')->getHandler();
        $handler->write('abc123', 'payload');
        $this->assertSame('payload', $handler->read('abc123'));
    }
}
