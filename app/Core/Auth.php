<?php
namespace App\Core;

class Auth
{
    public static function attempt(string $email, string $password): array|false
    {
        $user = DB::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user) return false;
        if (!password_verify($password, $user['password'])) return false;
        return $user;
    }

    public static function createToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);

        DB::query(
            'INSERT INTO api_tokens (user_id, token, name, created_at) VALUES (?, ?, ?, ?)',
            [$userId, $hash, 'api-token', date('Y-m-d H:i:s')]
        );

        return $token;
    }

    public static function userFromToken(string $token): array|false
    {
        $hash = hash('sha256', $token);
        $row = DB::fetchOne(
            'SELECT u.* FROM api_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token = ?
             AND (t.expires_at IS NULL OR t.expires_at > NOW())',
            [$hash]
        );

        if ($row) {
            // Update last_used_at
            DB::query('UPDATE api_tokens SET last_used_at = NOW() WHERE token = ?', [$hash]);
        }

        return $row ?: false;
    }

    public static function revokeToken(string $token): void
    {
        $hash = hash('sha256', $token);
        DB::delete('api_tokens', 'token = ?', [$hash]);
    }

    /** Guard: call at the top of protected API methods. Exits with 401 on failure. */
    public static function guard(\App\Core\Request $request): array
    {
        $token = $request->bearerToken();
        if (!$token) {
            \App\Core\Response::error('Unauthenticated.', 401);
            exit;
        }
        $user = self::userFromToken($token);
        if (!$user) {
            \App\Core\Response::error('Unauthenticated.', 401);
            exit;
        }
        return $user;
    }
}
