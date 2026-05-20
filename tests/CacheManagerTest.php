<?php
use PHPUnit\Framework\TestCase;

class CacheManagerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function testSessionCacheSetGet()
    {
        $c = CacheManager::getInstance();
        $c->setSessionCache('ut_key', 'ut_value', 2);
        $this->assertEquals('ut_value', $c->getSessionCache('ut_key'));
    }

    public function testFileCacheSetGet()
    {
        $c = CacheManager::getInstance();
        $c->setFileCache('ut_file', ['a' => 1], 2);
        $this->assertEquals(['a' => 1], $c->getFileCache('ut_file'));
    }
}
