<?php
/**
 * TOTP Two-Factor Authentication Manager
 *
 * Wrapper around robthree/twofactorauth library for managing 2FA.
 * Provides secret generation, QR code rendering, code verification,
 * and recovery code management.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use RobThree\Auth\Algorithm;
use RobThree\Auth\Providers\Qr\QRServerProvider;
use RobThree\Auth\TwoFactorAuth;

class TotpManager
{
    private TwoFactorAuth $tfa;
    private string $issuer = 'Sona Event Management';

    public function __construct()
    {
        $qrProvider = new QRServerProvider();
        $this->tfa  = new TwoFactorAuth(
            qrcodeprovider: $qrProvider,
            issuer: $this->issuer,
            digits: 6,
            period: 30,
            algorithm: Algorithm::Sha1
        );
    }

    /**
     * Generate a new TOTP secret
     *
     * @return string Base32-encoded secret
     */
    public function generateSecret(): string
    {
        return $this->tfa->createSecret();
    }

    /**
     * Get a QR code data URI for the given secret
     *
     * @param string $username The user's display label in the authenticator app
     * @param string $secret The TOTP secret
     * @return string Data URI (image/png base64) for the QR code
     */
    public function getQRCodeDataUri(string $username, string $secret): string
    {
        return $this->tfa->getQRCodeImageAsDataUri($username, $secret);
    }

    /**
     * Verify a TOTP code against a secret
     *
     * @param string $secret The stored TOTP secret
     * @param string $code The 6-digit code to verify
     * @param int $discrepancy Number of 30-second periods to check before/after (default 1 = ±30s)
     * @return bool True if the code is valid
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        return $this->tfa->verifyCode($secret, $code, $discrepancy);
    }

    /**
     * Generate a set of one-time recovery codes
     *
     * @param int $count Number of recovery codes to generate (default 8)
     * @return array Array of plaintext recovery codes
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // Format: XXXX-XXXX (8 alphanumeric chars with dash)
            $codes[] = strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
        }
        return $codes;
    }

    /**
     * Hash recovery codes for safe storage
     *
     * @param array $codes Array of plaintext recovery codes
     * @return string JSON-encoded array of hashed codes
     */
    public function hashRecoveryCodes(array $codes): string
    {
        $hashed = [];
        foreach ($codes as $code) {
            $hashed[] = password_hash($code, PASSWORD_DEFAULT);
        }
        return json_encode($hashed);
    }

    /**
     * Verify a recovery code against stored hashed codes
     *
     * @param string $inputCode The recovery code to check
     * @param string $storedCodesJson JSON string of hashed recovery codes
     * @return array ['valid' => bool, 'remaining_codes' => string] Updated codes JSON with used code removed
     */
    public function verifyRecoveryCode(string $inputCode, string $storedCodesJson): array
    {
        $hashedCodes = json_decode($storedCodesJson, true);

        if (! is_array($hashedCodes) || empty($hashedCodes)) {
            return ['valid' => false, 'remaining_codes' => $storedCodesJson];
        }

        $inputCode = strtoupper(trim($inputCode));

        foreach ($hashedCodes as $index => $hashedCode) {
            if (password_verify($inputCode, $hashedCode)) {
                // Remove the used code
                unset($hashedCodes[$index]);
                $hashedCodes = array_values($hashedCodes); // Re-index
                return [
                    'valid'           => true,
                    'remaining_codes' => json_encode($hashedCodes),
                ];
            }
        }

        return ['valid' => false, 'remaining_codes' => $storedCodesJson];
    }

    /**
     * Encrypt a TOTP secret for database storage
     *
     * @param string $secret The plaintext TOTP secret
     * @return string Encrypted secret (base64-encoded)
     */
    public function encryptSecret(string $secret): string
    {
        $key       = $this->getEncryptionKey();
        $iv        = random_bytes(16);
        $encrypted = openssl_encrypt($secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a TOTP secret from database storage
     *
     * @param string $encryptedSecret The encrypted secret (base64-encoded)
     * @return string|false The plaintext secret, or false on failure
     */
    public function decryptSecret(string $encryptedSecret): string | false
    {
        $key  = $this->getEncryptionKey();
        $data = base64_decode($encryptedSecret);

        if ($data === false || strlen($data) < 17) {
            return false;
        }

        $iv        = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Get the encryption key from environment or generate a default
     *
     * @return string 32-byte encryption key
     */
    private function getEncryptionKey(): string
    {
        $appKey = getenv('TOTP_ENCRYPTION_KEY');

        if (! $appKey || strlen($appKey) < 16) {
            // Fallback: derive from a combination of DB credentials
            // This is stable across requests but unique per installation
            $appKey = getenv('DB_PASS') . getenv('DB_NAME') . 'totp_2fa_secret_key';
        }

        // Derive a consistent 32-byte key using SHA-256
        return hash('sha256', $appKey, true);
    }

    /**
     * Check if a user has 2FA enabled
     *
     * @param mysqli $conn Database connection
     * @param string $username Username to check
     * @param string $table Table name (student_register or teacher_register)
     * @return bool True if 2FA is enabled
     */
    public function isEnabled(mysqli $conn, string $username, string $table): bool
    {
        $sql  = "SELECT totp_enabled FROM $table WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int) $row['totp_enabled'] === 1;
        }

        return false;
    }

    /**
     * Get the encrypted TOTP secret for a user
     *
     * @param mysqli $conn Database connection
     * @param string $username Username
     * @param string $table Table name
     * @return string|null Encrypted secret or null if not set
     */
    public function getSecret(mysqli $conn, string $username, string $table): ?string
    {
        $sql  = "SELECT totp_secret FROM $table WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['totp_secret'];
        }

        return null;
    }

    /**
     * Get the stored recovery codes JSON for a user
     *
     * @param mysqli $conn Database connection
     * @param string $username Username
     * @param string $table Table name
     * @return string|null Recovery codes JSON or null
     */
    public function getRecoveryCodes(mysqli $conn, string $username, string $table): ?string
    {
        $sql  = "SELECT totp_recovery_codes FROM $table WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['totp_recovery_codes'];
        }

        return null;
    }

    /**
     * Enable 2FA for a user: store encrypted secret and hashed recovery codes
     *
     * @param mysqli $conn Database connection
     * @param string $username Username
     * @param string $table Table name
     * @param string $secret Plaintext TOTP secret
     * @param array $recoveryCodes Plaintext recovery codes
     * @return bool True on success
     */
    public function enable(mysqli $conn, string $username, string $table, string $secret, array $recoveryCodes): bool
    {
        $encryptedSecret = $this->encryptSecret($secret);
        $hashedCodes     = $this->hashRecoveryCodes($recoveryCodes);

        $sql  = "UPDATE $table SET totp_secret = ?, totp_enabled = 1, totp_recovery_codes = ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $encryptedSecret, $hashedCodes, $username);
        return $stmt->execute();
    }

    /**
     * Disable 2FA for a user
     *
     * @param mysqli $conn Database connection
     * @param string $username Username
     * @param string $table Table name
     * @return bool True on success
     */
    public function disable(mysqli $conn, string $username, string $table): bool
    {
        $sql  = "UPDATE $table SET totp_secret = NULL, totp_enabled = 0, totp_recovery_codes = NULL WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        return $stmt->execute();
    }

    /**
     * Update recovery codes in the database (e.g., after one is used)
     *
     * @param mysqli $conn Database connection
     * @param string $username Username
     * @param string $table Table name
     * @param string $codesJson Updated JSON of remaining hashed codes
     * @return bool True on success
     */
    public function updateRecoveryCodes(mysqli $conn, string $username, string $table, string $codesJson): bool
    {
        $sql  = "UPDATE $table SET totp_recovery_codes = ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $codesJson, $username);
        return $stmt->execute();
    }
}
