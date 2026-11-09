<?php

namespace Q\Tools\Tests;

use PHPUnit\Framework\TestCase;
use Q\Tools\StringStore;

require_once __DIR__ . '/../_src/StringStore.php';

final class StringStoreTest extends TestCase
{
    public function testStoresAndReturnsAString(): void
    {
        $store = new StringStore();

        self::assertSame('', $store->get());

        $store->set('Hello, world!');

        self::assertSame('Hello, world!', $store->get());
    }
}
