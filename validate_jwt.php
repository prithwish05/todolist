<?php
require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function validateJWT() {
    // Temporary secret if not in environment (remove in production)
    if (!isset($_ENV['JWT_SECRET'])) {
        $_ENV['JWT_SECRET'] = '32c55c969d05915ee8fe213243f14ff2b94aea8067fe8bb790ed8ab42c2d3a15';
    }

    if (!isset($_COOKIE['jwt'])) {
        throw new Exception('Authentication required');
    }

    try {
        $decoded = JWT::decode($_COOKIE['jwt'], new Key($_ENV['JWT_SECRET'], 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        throw new Exception('Invalid token: ' . $e->getMessage());
    }
}
?>