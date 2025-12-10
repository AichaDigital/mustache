<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Cache\ArrayCache;
use AichaDigital\MustacheResolver\Cache\NullCache;
use AichaDigital\MustacheResolver\Contracts\CacheInterface;

describe('ArrayCache', function () {
    it('implements CacheInterface', function () {
        $cache = new ArrayCache;

        expect($cache)->toBeInstanceOf(CacheInterface::class);
    });

    it('stores and retrieves values', function () {
        $cache = new ArrayCache;

        $cache->set('key', 'value');

        expect($cache->get('key'))->toBe('value');
    });

    it('returns null for non-existent keys', function () {
        $cache = new ArrayCache;

        expect($cache->get('nonexistent'))->toBeNull();
    });

    it('checks if key exists', function () {
        $cache = new ArrayCache;

        expect($cache->has('key'))->toBeFalse();

        $cache->set('key', 'value');

        expect($cache->has('key'))->toBeTrue();
    });

    it('forgets a key', function () {
        $cache = new ArrayCache;
        $cache->set('key', 'value');

        expect($cache->has('key'))->toBeTrue();

        $cache->forget('key');

        expect($cache->has('key'))->toBeFalse();
    });

    it('flushes all keys', function () {
        $cache = new ArrayCache;
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        expect($cache->has('key1'))->toBeTrue();
        expect($cache->has('key2'))->toBeTrue();

        $cache->flush();

        expect($cache->has('key1'))->toBeFalse();
        expect($cache->has('key2'))->toBeFalse();
    });

    it('stores values with ttl', function () {
        $cache = new ArrayCache;

        $cache->set('key', 'value', 3600);

        expect($cache->get('key'))->toBe('value');
    });

    it('stores complex values', function () {
        $cache = new ArrayCache;
        $data = ['nested' => ['data' => 'value']];

        $cache->set('complex', $data);

        expect($cache->get('complex'))->toBe($data);
    });

    it('stores objects', function () {
        $cache = new ArrayCache;
        $obj = new stdClass;
        $obj->name = 'test';

        $cache->set('object', $obj);

        expect($cache->get('object'))->toBe($obj);
    });

    it('expires entries after ttl', function () {
        $cache = new ArrayCache;

        // Set with TTL of 0 (expired immediately)
        $cache->set('expired_key', 'value', -1);

        // Wait for expiration
        expect($cache->has('expired_key'))->toBeFalse();
        expect($cache->get('expired_key'))->toBeNull();
    });
});

describe('NullCache', function () {
    it('implements CacheInterface', function () {
        $cache = new NullCache;

        expect($cache)->toBeInstanceOf(CacheInterface::class);
    });

    it('always returns null for get', function () {
        $cache = new NullCache;
        $cache->set('key', 'value');

        expect($cache->get('key'))->toBeNull();
    });

    it('always returns false for has', function () {
        $cache = new NullCache;
        $cache->set('key', 'value');

        expect($cache->has('key'))->toBeFalse();
    });

    it('does nothing on set', function () {
        $cache = new NullCache;
        $cache->set('key', 'value');

        expect($cache->get('key'))->toBeNull();
    });

    it('does nothing on set with ttl', function () {
        $cache = new NullCache;
        $cache->set('key', 'value', 3600);

        expect($cache->get('key'))->toBeNull();
    });

    it('does nothing on forget', function () {
        $cache = new NullCache;
        $cache->forget('key');

        expect($cache->has('key'))->toBeFalse();
    });

    it('does nothing on flush', function () {
        $cache = new NullCache;
        $cache->flush();

        expect($cache->has('key'))->toBeFalse();
    });
});
