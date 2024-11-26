<?php
// auth_check.php
session_start();

class AuthenticationManager {
    private static $instance = null;
    private $isAuthenticated = false;
    private $lastActivity = null;
    private $sessionTimeout = 1800; // 30 minutes en secondes
    
    private function __construct() {
        $this->checkAuthentication();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new AuthenticationManager();
        }
        return self::$instance;
    }
    
    private function checkAuthentication() {
        // Vérifier si l'utilisateur est connecté
        if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
            // Vérifier le timeout de session
            if (isset($_SESSION['last_activity'])) {
                if (time() - $_SESSION['last_activity'] > $this->sessionTimeout) {
                    $this->logout();
                    return;
                }
            }
            
            $this->isAuthenticated = true;
            $_SESSION['last_activity'] = time();
            $this->lastActivity = $_SESSION['last_activity'];
        }
    }
    
    public function isUserAuthenticated() {
        return $this->isAuthenticated;
    }
    
    public function enforceAuthentication() {
        if (!$this->isAuthenticated) {
            header('Location: login.php');
            exit();
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
        $this->isAuthenticated = false;
        header('Location: login.php');
        exit();
    }
    
    public function getCurrentUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    public function getCurrentUsername() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }
    
    public function getLastActivity() {
        return $this->lastActivity;
    }
    
    public function refreshActivity() {
        $_SESSION['last_activity'] = time();
        $this->lastActivity = $_SESSION['last_activity'];
    }
}