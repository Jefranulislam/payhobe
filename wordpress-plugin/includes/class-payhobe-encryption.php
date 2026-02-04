<?php
/**
 * PayHobe Encryption Handler
 *
 * Handles encryption and decryption of sensitive data
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption class
 */
class PayHobe_Encryption {
    
    /**
     * Encryption method
     */
    private static $cipher = 'aes-256-cbc';
    
    /**
     * Get encryption key
     */
    private static function get_key() {
        $key = defined('PAYHOBE_ENCRYPTION_KEY') ? PAYHOBE_ENCRYPTION_KEY : 'payhobe_default_key';
        return hash('sha256', $key, true);
    }
    
    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @return string|false Encrypted data or false on failure
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $iv_length = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt(
            $data,
            self::$cipher,
            self::get_key(),
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            return false;
        }
        
        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data
     *
     * @param string $data Encrypted data
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        // Check if data might be unencrypted (plain text)
        $decoded = base64_decode($data, true);
        
        if ($decoded === false) {
            // Not base64 encoded, return as-is (might be plain text)
            return $data;
        }
        
        $iv_length = openssl_cipher_iv_length(self::$cipher);
        
        // Check if decoded data is long enough to contain IV + encrypted data
        if (strlen($decoded) < $iv_length) {
            // Data too short, might be plain text or corrupted - return original
            return $data;
        }
        
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);
        
        // Suppress warnings for corrupted data
        $decrypted = @openssl_decrypt(
            $encrypted,
            self::$cipher,
            self::get_key(),
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // If decryption failed, return original data (might be plain text)
        if ($decrypted === false) {
            return $data;
        }
        
        return $decrypted;
    }
    
    /**
     * Hash data (one-way)
     *
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public static function hash($data) {
        return hash('sha256', $data . self::get_key());
    }
    
    /**
     * Generate secure random token
     *
     * @param int $length Token length
     * @return string Random token
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Mask sensitive data for display
     *
     * @param string $data Data to mask
     * @param int $visible_chars Number of visible characters at end
     * @return string Masked data
     */
    public static function mask($data, $visible_chars = 4) {
        $length = strlen($data);
        if ($length <= $visible_chars) {
            return str_repeat('*', $length);
        }
        return str_repeat('*', $length - $visible_chars) . substr($data, -$visible_chars);
    }
    
    /**
     * Mask phone number
     *
     * @param string $phone Phone number
     * @return string Masked phone
     */
    public static function mask_phone($phone) {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($clean) >= 11) {
            return substr($clean, 0, 3) . '****' . substr($clean, -4);
        }
        return self::mask($clean);
    }
}
