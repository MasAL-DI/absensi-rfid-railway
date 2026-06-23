<?php

/**
 * Token Helper — JWT logic isolated
 * Gunakan: Token::create($payload) atau Token::verify($tokenString)
 */
class Token {
    
    /**
     * Create JWT token
     */
    public static function create($payload) {
        $secret = getenv('JWT_SECRET') ?: 'rahasia_absensi_smkn1pabelan';
        
        $header = rtrim(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '=');
        $payloadEncoded = rtrim(base64_encode(json_encode($payload)), '=');
        $sig = rtrim(base64_encode(hash_hmac('sha256', "$header.$payloadEncoded", $secret, true)), '=');
        
        return "$header.$payloadEncoded.$sig";
    }

    /**
     * Verify JWT token
     */
    public static function verify($token) {
        $secret = getenv('JWT_SECRET') ?: 'rahasia_absensi_smkn1pabelan';
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $sig] = $parts;
        
        $validSig = rtrim(base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true)), '=');
        if ($sig !== $validSig) {
            return false;
        }

        $data = json_decode(base64_decode($payload), true);
        
        // Check expiry
        if (isset($data['exp']) && $data['exp'] < time()) {
            return false;
        }

        return $data;
    }

    /**
     * Get token from request header
     */
    public static function getFromRequest() {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $auth);
        return $token ?: null;
    }

    /**
     * Verify token from request or respond with error
     */
    public static function verifyOrFail() {
        $token = self::getFromRequest();
        if (!$token) {
            Response::unauthorized('Token tidak ditemukan');
        }

        $user = self::verify($token);
        if (!$user) {
            Response::unauthorized('Token tidak valid atau kadaluarsa');
        }

        return $user;
    }

    /**
     * Create token with standard claims
     */
    public static function forUser($userId, $username, $role, $expiryHours = 8) {
        return self::create([
            'id' => $userId,
            'username' => $username,
            'role' => $role,
            'exp' => time() + ($expiryHours * 3600)
        ]);
    }
}
