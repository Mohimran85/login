<?php
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['csrf_token']);
    }

    public function testGenerateAndValidateToken()
    {
        $token = generateCSRFToken();
        $this->assertNotEmpty($token);
        $this->assertTrue(validateCSRFToken($token));
        $this->assertFalse(validateCSRFToken('invalid_token'));
    }

    public function testRegenerateTokenChangesValue()
    {
        $t1 = generateCSRFToken();
        regenerateCSRFToken();
        $t2 = getCSRFToken();
        $this->assertNotEquals($t1, $t2);
    }
}
