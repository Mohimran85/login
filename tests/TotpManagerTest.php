<?php
use PHPUnit\Framework\TestCase;

class TotpManagerTest extends TestCase
{
    private TotpManager $mgr;

    protected function setUp(): void
    {
        putenv('TOTP_ENCRYPTION_KEY=unit_test_key_123456');
        $this->mgr = new TotpManager();
    }

    public function testEncryptDecryptSecret()
    {
        $secret = 'TESTSECRET123';
        $enc    = $this->mgr->encryptSecret($secret);
        $this->assertNotFalse($enc);
        $dec = $this->mgr->decryptSecret($enc);
        $this->assertEquals($secret, $dec);
    }

    public function testRecoveryCodesHashAndVerify()
    {
        $codes = $this->mgr->generateRecoveryCodes(4);
        $this->assertCount(4, $codes);
        $json = $this->mgr->hashRecoveryCodes($codes);
        $this->assertIsString($json);
        $res = $this->mgr->verifyRecoveryCode($codes[0], $json);
        $this->assertTrue($res['valid']);
        $this->assertIsString($res['remaining_codes']);
    }
}
