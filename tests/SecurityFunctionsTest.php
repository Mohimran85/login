<?php
use PHPUnit\Framework\TestCase;

class SecurityFunctionsTest extends TestCase
{
    public function testSanitizeFilenameAndExtension()
    {
        $out = sanitizeFilename('my unsafe file!.PDF');
        $this->assertStringContainsString('.', $out);
        $this->assertMatchesRegularExpression('/\.(txt|pdf|jpg|jpeg|png|gif|webp|doc|docx|xls|xlsx|csv|zip)$/', $out);
    }

    public function testValidateFilePathPreventsTraversal()
    {
        $base = sys_get_temp_dir() . '/ems_test_base';
        @mkdir($base);
        file_put_contents($base . '/good.txt', 'ok');

        $good = validateFilePath('good.txt', $base);
        $this->assertNotFalse($good);

        $bad = validateFilePath('../etc/passwd', $base);
        $this->assertFalse($bad);
    }
}
