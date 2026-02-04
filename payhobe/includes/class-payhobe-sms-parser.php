<?php
/**
 * PayHobe SMS Parser
 *
 * Parses SMS messages from various MFS providers to extract payment details
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMS Parser class
 */
class PayHobe_SMS_Parser {
    
    /**
     * Parse an SMS message
     *
     * @param string $message SMS message body
     * @return array Parsed data
     */
    public function parse($message) {
        $result = array(
            'method' => 'unknown',
            'transaction_id' => null,
            'amount' => null,
            'sender_number' => null,
            'is_payment' => false
        );
        
        // Normalize message
        $message = $this->normalize_message($message);
        
        // Detect provider and parse
        if ($this->is_bkash($message)) {
            $result = array_merge($result, $this->parse_bkash($message));
        } elseif ($this->is_nagad($message)) {
            $result = array_merge($result, $this->parse_nagad($message));
        } elseif ($this->is_rocket($message)) {
            $result = array_merge($result, $this->parse_rocket($message));
        } elseif ($this->is_upay($message)) {
            $result = array_merge($result, $this->parse_upay($message));
        }
        
        return $result;
    }
    
    /**
     * Normalize message for parsing
     *
     * @param string $message Raw message
     * @return string Normalized message
     */
    private function normalize_message($message) {
        // Convert to UTF-8 if needed
        $message = mb_convert_encoding($message, 'UTF-8', 'auto');
        
        // Remove extra whitespace
        $message = preg_replace('/\s+/', ' ', $message);
        
        // Trim
        $message = trim($message);
        
        return $message;
    }
    
    /**
     * Check if message is from bKash
     *
     * @param string $message Message
     * @return bool
     */
    private function is_bkash($message) {
        $keywords = array('bkash', 'bKash', 'BKASH', 'TrxID', 'বিকাশ');
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Parse bKash SMS
     *
     * @param string $message Message
     * @return array Parsed data
     */
    private function parse_bkash($message) {
        $result = array(
            'method' => 'bkash',
            'is_payment' => false
        );
        
        // Check if it's a received payment (not sent)
        if (preg_match('/received|প্রাপ্ত|জমা/iu', $message)) {
            $result['is_payment'] = true;
        }
        
        // Extract Transaction ID
        // Pattern: TrxID XXXXXXXXXX or TrxID: XXXXXXXXXX
        if (preg_match('/TrxID[:\s]*([A-Z0-9]+)/i', $message, $matches)) {
            $result['transaction_id'] = strtoupper($matches[1]);
        }
        
        // Extract amount
        // Patterns: Tk 1,000.00 or Tk. 1000 or ৳1000
        if (preg_match('/(?:Tk\.?|৳|BDT)\s*([\d,]+\.?\d*)/i', $message, $matches)) {
            $result['amount'] = floatval(str_replace(',', '', $matches[1]));
        }
        
        // Extract sender number
        // Pattern: from 01XXXXXXXXX or 01XXXXXXXXX থেকে
        if (preg_match('/(?:from|থেকে)\s*(01\d{9})/i', $message, $matches)) {
            $result['sender_number'] = $matches[1];
        } elseif (preg_match('/(01\d{9})/', $message, $matches)) {
            // Just find any phone number
            $result['sender_number'] = $matches[1];
        }
        
        return $result;
    }
    
    /**
     * Check if message is from Nagad
     *
     * @param string $message Message
     * @return bool
     */
    private function is_nagad($message) {
        $keywords = array('nagad', 'Nagad', 'NAGAD', 'TxnNo', 'নগদ');
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Parse Nagad SMS
     *
     * @param string $message Message
     * @return array Parsed data
     */
    private function parse_nagad($message) {
        $result = array(
            'method' => 'nagad',
            'is_payment' => false
        );
        
        // Check if it's a received payment
        if (preg_match('/received|প্রাপ্ত|জমা|credited/iu', $message)) {
            $result['is_payment'] = true;
        }
        
        // Extract Transaction ID
        // Pattern: TxnNo: XXXXXXXXXX or TxnNo XXXXXXXXXX
        if (preg_match('/TxnNo[:\s]*([A-Z0-9]+)/i', $message, $matches)) {
            $result['transaction_id'] = strtoupper($matches[1]);
        }
        
        // Extract amount
        if (preg_match('/(?:Tk\.?|৳|BDT)\s*([\d,]+\.?\d*)/i', $message, $matches)) {
            $result['amount'] = floatval(str_replace(',', '', $matches[1]));
        }
        
        // Extract sender number
        if (preg_match('/(?:from|থেকে)\s*(01\d{9})/i', $message, $matches)) {
            $result['sender_number'] = $matches[1];
        } elseif (preg_match('/(01\d{9})/', $message, $matches)) {
            $result['sender_number'] = $matches[1];
        }
        
        return $result;
    }
    
    /**
     * Check if message is from Rocket
     *
     * @param string $message Message
     * @return bool
     */
    private function is_rocket($message) {
        $keywords = array('rocket', 'Rocket', 'ROCKET', 'DBBL', 'TxnId', 'রকেট');
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Parse Rocket SMS
     *
     * @param string $message Message
     * @return array Parsed data
     */
    private function parse_rocket($message) {
        $result = array(
            'method' => 'rocket',
            'is_payment' => false
        );
        
        // Check if it's a received payment
        if (preg_match('/received|প্রাপ্ত|জমা|credited/iu', $message)) {
            $result['is_payment'] = true;
        }
        
        // Extract Transaction ID
        // Pattern: TxnId: XXXXXXXXXX or Txn XXXXXXXXXX
        if (preg_match('/(?:TxnId|Txn)[:\s]*(\d+)/i', $message, $matches)) {
            $result['transaction_id'] = $matches[1];
        }
        
        // Extract amount
        if (preg_match('/(?:Tk\.?|৳|BDT)\s*([\d,]+\.?\d*)/i', $message, $matches)) {
            $result['amount'] = floatval(str_replace(',', '', $matches[1]));
        }
        
        // Extract sender number (Rocket uses 018XXXXXXXX format)
        if (preg_match('/(?:from|থেকে)\s*(01\d{9})/i', $message, $matches)) {
            $result['sender_number'] = $matches[1];
        } elseif (preg_match('/(018\d{8})/', $message, $matches)) {
            $result['sender_number'] = $matches[1];
        }
        
        return $result;
    }
    
    /**
     * Check if message is from Upay
     *
     * @param string $message Message
     * @return bool
     */
    private function is_upay($message) {
        $keywords = array('upay', 'Upay', 'UPAY', 'TxnID', 'উপায়');
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Parse Upay SMS
     *
     * @param string $message Message
     * @return array Parsed data
     */
    private function parse_upay($message) {
        $result = array(
            'method' => 'upay',
            'is_payment' => false
        );
        
        // Check if it's a received payment
        if (preg_match('/received|প্রাপ্ত|জমা|credited/iu', $message)) {
            $result['is_payment'] = true;
        }
        
        // Extract Transaction ID
        if (preg_match('/TxnID[:\s]*([A-Z0-9]+)/i', $message, $matches)) {
            $result['transaction_id'] = strtoupper($matches[1]);
        }
        
        // Extract amount
        if (preg_match('/(?:Tk\.?|৳|BDT)\s*([\d,]+\.?\d*)/i', $message, $matches)) {
            $result['amount'] = floatval(str_replace(',', '', $matches[1]));
        }
        
        // Extract sender number
        if (preg_match('/(?:from|থেকে)\s*(01\d{9})/i', $message, $matches)) {
            $result['sender_number'] = $matches[1];
        } elseif (preg_match('/(01\d{9})/', $message, $matches)) {
            $result['sender_number'] = $matches[1];
        }
        
        return $result;
    }
    
    /**
     * Parse message with custom keywords
     *
     * @param string $message Message
     * @param string $keywords Comma-separated keywords
     * @return array Parsed data
     */
    public function parse_with_keywords($message, $keywords) {
        $keyword_array = array_map('trim', explode(',', $keywords));
        
        $result = array(
            'method' => 'custom',
            'is_payment' => false,
            'matched_keywords' => array()
        );
        
        foreach ($keyword_array as $keyword) {
            if (!empty($keyword) && stripos($message, $keyword) !== false) {
                $result['matched_keywords'][] = $keyword;
                $result['is_payment'] = true;
            }
        }
        
        // Try to extract transaction ID (generic patterns)
        if (preg_match('/(?:TrxID|TxnID|TxnNo|Txn|Ref)[:\s]*([A-Z0-9]+)/i', $message, $matches)) {
            $result['transaction_id'] = strtoupper($matches[1]);
        }
        
        // Try to extract amount
        if (preg_match('/(?:Tk\.?|৳|BDT)\s*([\d,]+\.?\d*)/i', $message, $matches)) {
            $result['amount'] = floatval(str_replace(',', '', $matches[1]));
        }
        
        return $result;
    }
    
    /**
     * Detect MFS provider from sender number or message
     *
     * @param string $sender Sender number/ID
     * @param string $message Message content
     * @return string Provider name or 'unknown'
     */
    public function detect_provider($sender, $message) {
        // Check by sender ID
        $sender_lower = strtolower($sender);
        
        if (strpos($sender_lower, 'bkash') !== false) {
            return 'bkash';
        }
        if (strpos($sender_lower, 'nagad') !== false) {
            return 'nagad';
        }
        if (strpos($sender_lower, 'rocket') !== false || strpos($sender_lower, 'dbbl') !== false) {
            return 'rocket';
        }
        if (strpos($sender_lower, 'upay') !== false) {
            return 'upay';
        }
        
        // Check by message content
        if ($this->is_bkash($message)) {
            return 'bkash';
        }
        if ($this->is_nagad($message)) {
            return 'nagad';
        }
        if ($this->is_rocket($message)) {
            return 'rocket';
        }
        if ($this->is_upay($message)) {
            return 'upay';
        }
        
        return 'unknown';
    }
    
    /**
     * Validate parsed transaction ID against expected format
     *
     * @param string $trx_id Transaction ID
     * @param string $method Payment method
     * @return bool
     */
    public function validate_parsed_trx_id($trx_id, $method) {
        if (empty($trx_id)) {
            return false;
        }
        
        return PayHobe_REST_API::validate_transaction_id($trx_id, $method);
    }
}
