<?php
/**
 * Implementação simplificada de TOTP (Time-Based One-Time Password)
 * Sem dependências externas (usa apenas hash_hmac padrão do PHP)
 */
class SimpleTOTP {
    
    /**
     * Gera um novo segredo Base32 (16 caracteres)
     */
    public static function generateSecret($length = 16) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Verifica um código TOTP com base no segredo
     */
    public static function verify($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::calculateCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calcula o código TOTP para um determinado momento (time slice)
     */
    private static function calculateCode($secret, $timeSlice) {
        $secretKey = self::base32Decode($secret);
        
        // Converte o time slice para binário de 8 bytes (big-endian)
        $timeBytes = pack('J', $timeSlice);
        
        // Calcula HMAC-SHA1
        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);
        
        // Dynamic Truncation (pegar o offset no último nibble)
        $offset = ord($hash[19]) & 0xf;
        
        // Extrai 4 bytes a partir do offset
        $value = (
            ((ord($hash[$offset+0]) & 0x7f) << 24) |
            ((ord($hash[$offset+1]) & 0xff) << 16) |
            ((ord($hash[$offset+2]) & 0xff) << 8) |
            (ord($hash[$offset+3]) & 0xff)
        );
        
        // Obtém o código numérico (6 dígitos)
        $totp = $value % 1000000;
        
        return str_pad($totp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decodifica uma string Base32 para bytes
     */
    private static function base32Decode($base32) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper($base32);
        $decoded = '';
        $buffer = 0;
        $bufferBits = 0;
        
        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue; // Ignora caracteres inválidos
            
            $buffer = ($buffer << 5) | $pos;
            $bufferBits += 5;
            
            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $decoded .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }
        return $decoded;
    }
}

/**
 * Função global chamada no auth.php
 */
if (!function_exists('verifyTotp')) {
    function verifyTotp($secret, $code) {
        return SimpleTOTP::verify($secret, $code);
    }
}
