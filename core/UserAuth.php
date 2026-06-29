<?php

require_once __DIR__ . '/Database.php';

class UserAuth
{
    public static function authenticate($username, $password)
    {
        $username = trim((string) $username);
        $password = (string) $password;
        if ($username === '' || $password === '') {
            return null;
        }

        $user = Database::fetch(
            'SELECT id, nama, username, password_hash, status FROM users WHERE username = ? LIMIT 1',
            array($username)
        );

        if (!$user || (int) $user['status'] !== 1) {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    public static function loginSession($user)
    {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id'] = (int) $user['id'];
        $_SESSION['admin_name'] = $user['username'];
        $_SESSION['admin_display_name'] = $user['nama'];
    }
}
