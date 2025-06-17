<?php
session_start();

class Auth
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function login($username, $password)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, username, password, role, nama_lengkap FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Debug log
            error_log("Login attempt - Username: " . $username);
            error_log("User found: " . ($user ? "Yes" : "No"));
            if ($user) {
                error_log("Password verify result: " . (password_verify($password, $user['password']) ? "True" : "False"));
            }

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['last_activity'] = time();

                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            return false;
        }
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    public function checkSession()
    {
        if (!$this->isLoggedIn()) {
            header("Location: /SMPedia/login.php");
            exit();
        }

        // Check session timeout (30 minutes)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            $this->logout();
            header("Location: /SMPedia/login.php?msg=timeout");
            exit();
        }
        $_SESSION['last_activity'] = time();
    }

    public function hasRole($role)
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    public function requireRole($role)
    {
        if (!$this->hasRole($role)) {
            header("Location: /SMPedia/unauthorized.php");
            exit();
        }
    }

    public function logout()
    {
        session_unset();
        session_destroy();
    }

    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("SELECT id, username, role, nama_lengkap FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get User Error: " . $e->getMessage());
            return null;
        }
    }
}
?>