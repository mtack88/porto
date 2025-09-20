<?php
declare(strict_types=1);

class RateLimiter {
    private PDO $pdo;
    private int $maxAttempts = 5;
    private int $windowMinutes = 15;
    private int $lockoutMinutes = 30;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->cleanOldAttempts();
    }
    
    /**
     * Verifica se l'IP è bloccato
     */
    public function isBlocked(string $ip, ?string $email = null): bool {
        // USA MYSQL PER TUTTI I CALCOLI DI TEMPO
        $sql = "
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = :ip 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
            AND success = 0
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ip' => $ip,
            ':minutes' => $this->windowMinutes
        ]);
        $ipAttempts = (int)$stmt->fetchColumn();
        
        if ($ipAttempts >= $this->maxAttempts) {
            return true;
        }
        
        // Controlla anche per email se fornita
        if ($email) {
            $sql = "
                SELECT COUNT(*) as attempts 
                FROM login_attempts 
                WHERE email = :email 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                AND success = 0
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':email' => $email,
                ':minutes' => $this->windowMinutes
            ]);
            $emailAttempts = (int)$stmt->fetchColumn();
            
            if ($emailAttempts >= $this->maxAttempts) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Registra un tentativo di login
     */
    public function recordAttempt(string $ip, ?string $email, bool $success): void {
        // USA NOW() di MySQL invece di PHP
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (ip_address, email, success, attempt_time) 
            VALUES (:ip, :email, :success, NOW())
        ");
        $stmt->execute([
            ':ip' => $ip,
            ':email' => $email,
            ':success' => $success ? 1 : 0
        ]);
    }
    
    /**
     * Ottieni tempo rimanente del blocco in secondi
     */
    public function getBlockedTimeRemaining(string $ip): int {
        $sql = "
            SELECT TIMESTAMPDIFF(SECOND, NOW(), 
                DATE_ADD(MAX(attempt_time), INTERVAL :lockout MINUTE)) as remaining
            FROM login_attempts 
            WHERE ip_address = :ip 
            AND success = 0
            AND attempt_time > DATE_SUB(NOW(), INTERVAL :window MINUTE)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ip' => $ip,
            ':lockout' => $this->lockoutMinutes,
            ':window' => $this->windowMinutes
        ]);
        
        $remaining = (int)$stmt->fetchColumn();
        return max(0, $remaining);
    }
    
    /**
     * Pulisce vecchi tentativi (più vecchi di 24 ore)
     */
    private function cleanOldAttempts(): void {
        $stmt = $this->pdo->prepare("
            DELETE FROM login_attempts 
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
    }
    
    /**
     * Reset tentativi per un IP dopo login riuscito
     */
    public function resetAttempts(string $ip, string $email): void {
        $stmt = $this->pdo->prepare("
            UPDATE login_attempts 
            SET success = 1 
            WHERE (ip_address = :ip OR email = :email) 
            AND success = 0
        ");
        $stmt->execute([':ip' => $ip, ':email' => $email]);
    }
}