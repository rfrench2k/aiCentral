<?php
/**
 * AI Central System - Key Manager
 * Encrypts/decrypts user API keys using AES-256-CBC
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../common/common_ai.php';

class KeyManager {
    private $encryptionKey;
    private $cipher = 'AES-256-CBC';

    public function __construct() {
        $this->encryptionKey = AI_ENCRYPTION_KEY;

        if (strlen($this->encryptionKey) < 32) {
            throw new Exception("Encryption key must be at least 32 characters");
        }
    }

    /**
     * Encrypt an API key
     *
     * @param string $apiKey Plain API key
     * @return array ['encrypted' => string, 'iv' => string]
     */
    public function encryptKey($apiKey) {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = random_bytes($ivLength);

        $encrypted = openssl_encrypt(
            $apiKey,
            $this->cipher,
            substr(hash('sha256', $this->encryptionKey, true), 0, 32),
            0,
            $iv
        );

        if ($encrypted === false) {
            aiCentral_logMessage("Encryption failed", 'ERROR');
            throw new Exception("Encryption failed");
        }

        return [
            'encrypted' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ];
    }

    /**
     * Decrypt an API key
     *
     * @param string $encryptedKey Base64 encoded encrypted key
     * @param string $iv Base64 encoded initialization vector
     * @return string|false Plain API key or false on failure
     */
    public function decryptKey($encryptedKey, $iv) {
        $decrypted = openssl_decrypt(
            base64_decode($encryptedKey),
            $this->cipher,
            substr(hash('sha256', $this->encryptionKey, true), 0, 32),
            0,
            base64_decode($iv)
        );

        if ($decrypted === false) {
            aiCentral_logMessage("Decryption failed", 'ERROR');
            return false;
        }

        return $decrypted;
    }

    /**
     * Get display prefix for an API key (first 8 and last 4 chars)
     *
     * @param string $apiKey Plain API key
     * @return string Key prefix for display (e.g., "sk-proj-...hkCu")
     */
    public function getKeyPrefix($apiKey) {
        if (strlen($apiKey) < 12) {
            return substr($apiKey, 0, 4) . '...';
        }

        $start = substr($apiKey, 0, 8);
        $end = substr($apiKey, -4);

        return $start . '...' . $end;
    }

    /**
     * Test if an API key works with a provider
     *
     * @param string $provider Provider code (claude, openai, kimi)
     * @param string $model Model to test with
     * @param string $apiKey API key to test
     * @return array ['success' => bool, 'error' => string]
     */
    public function testKey($provider, $model, $apiKey) {
        require_once __DIR__ . '/AIProviderManager.php';

        try {
            $manager = new AIProviderManager($provider, $model, $apiKey, 'TEST_USER', 'TEST_PROGRAM', 'test_feature');

            $result = $manager->makeRequest(
                'Respond with only the word "OK"',
                ['max_tokens' => 10]
            );

            if ($result['success']) {
                return ['success' => true, 'error' => null];
            } else {
                return ['success' => false, 'error' => $result['error']];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

?>
