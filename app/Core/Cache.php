<?php
namespace App\Core;

class Cache
{
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $row = DB::fetchOne(
            'SELECT value, expiration FROM cache_store WHERE cache_key = ?',
            [$key]
        );

        if ($row && $row['expiration'] > time()) {
            return unserialize($row['value']);
        }

        $value      = $callback();
        $expiration = time() + $ttl;
        $serialized = serialize($value);

        DB::query(
            'REPLACE INTO cache_store (cache_key, value, expiration) VALUES (?, ?, ?)',
            [$key, $serialized, $expiration]
        );

        return $value;
    }

    public static function forget(string $key): void
    {
        DB::delete('cache_store', 'cache_key = ?', [$key]);
    }

    public static function flush(): void
    {
        DB::query('DELETE FROM cache_store');
    }
}
