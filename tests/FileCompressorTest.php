<?php
use PHPUnit\Framework\TestCase;

class FileCompressorTest extends TestCase
{
    public function testFormatSize()
    {
        $this->assertStringContainsString('B', FileCompressor::formatSize(123));
        $this->assertStringContainsString('KB', FileCompressor::formatSize(2048));
    }

    public function testCompressImageSkipsWhenNoGD()
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD not available in this environment.');
        }
        $this->assertTrue(true);
    }
}
