<?php
// src/Services/UserService.php

class UserService {
    private $db;
    private $auth;

    public function __construct($database, $authService) {
        $this->db = $database;
        $this->auth = $authService;
    }

    /**
     * REGISTER NEW USER
     */
    public function register($email, $password, $userData = []) {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email format');
        }

        // Check if user exists
        if ($this->getUserByEmail($email)) {
            throw new \Exception('User with this email already exists');
        }

        // Validate password strength
        if (strlen($password) < 8) {
            throw new \Exception('Password must be at least 8 characters long');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (
            email, password_hash, first_name, last_name, 
            country, city, language, subscription_plan, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'free', NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $email,
            $passwordHash,
            $userData['first_name'] ?? '',
            $userData['last_name'] ?? '',
            $userData['country'] ?? 'Ukraine',
            $userData['city'] ?? '',
            $userData['language'] ?? 'uk'
        ]);

        $userId = $this->db->lastInsertId();

        // Create initial free subscription
        $this->createFreeSubscription($userId);

        // Generate JWT token
        $token = $this->auth->generateToken([
            'id' => $userId,
            'email' => $email,
            'plan' => 'free'
        ]);

        return [
            'user_id' => $userId,
            'email' => $email,
            'token' => $token,
            'subscription_plan' => 'free'
        ];
    }

    /**
     * LOGIN USER
     */
    public function login($email, $password) {
        $user = $this->getUserByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new \Exception('Invalid email or password');
        }

        if (!$user['is_active']) {
            throw new \Exception('Account is deactivated');
        }

        // Update last login
        $sql = "UPDATE users SET updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user['id']]);

        // Generate JWT token
        $token = $this->auth->generateToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'plan' => $user['subscription_plan']
        ]);

        return [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'token' => $token,
            'subscription_plan' => $user['subscription_plan'],
            'searches_remaining' => $this->getSearchesRemaining($user['id'])
        ];
    }

    /**
     * CHECK SEARCH LIMITS
     */
    public function canUserSearch($userId) {
        $user = $this->getUserById($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Reset daily counter if needed
        if ($user['last_search_reset'] < date('Y-m-d')) {
            $this->resetDailySearchCount($userId);
            $user['searches_used_today'] = 0;
        }

        $limits = [
            'free' => 2,
            'explorer' => 15,
            'universal' => 100,
            'business' => -1,  // unlimited
            'enterprise' => -1  // unlimited
        ];

        $userLimit = $limits[$user['subscription_plan']] ?? 0;

        if ($userLimit === -1) {
            return ['allowed' => true, 'remaining' => -1];
        }

        $remaining = $userLimit - $user['searches_used_today'];

        return [
            'allowed' => $remaining > 0,
            'remaining' => max(0, $remaining),
            'limit' => $userLimit,
            'plan' => $user['subscription_plan']
        ];
    }

    /**
     * INCREMENT SEARCH USAGE
     */
    public function incrementSearchUsage($userId, $tokensUsed = 0, $costUsd = 0) {
        // Update daily counter
        $sql = "UPDATE users SET 
                searches_used_today = searches_used_today + 1,
                searches_used_total = searches_used_total + 1,
                last_search_reset = CASE 
                    WHEN last_search_reset < CURDATE() THEN CURDATE()
                    ELSE last_search_reset 
                END
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        // Update usage statistics
        $this->updateUsageStats($userId, $tokensUsed, $costUsd);
    }

    /**
     * UPDATE SUBSCRIPTION
     */
    public function updateSubscription($userId, $plan, $stripeSubscriptionId = null) {
        $validPlans = ['free', 'explorer', 'universal', 'business', 'enterprise'];
        if (!in_array($plan, $validPlans)) {
            throw new \Exception('Invalid subscription plan');
        }

        $pricing = [
            'free' => 0,
            'explorer' => 89,
            'universal' => 199,
            'business' => 799,
            'enterprise' => 2499
        ];

        $price = $pricing[$plan];
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));

        // Start transaction
        $this->db->beginTransaction();

        try {
            // Update user subscription
            $sql = "UPDATE users SET 
                    subscription_plan = ?,
                    subscription_status = 'active',
                    subscription_expires_at = ?
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$plan, $expiresAt, $userId]);

            // Create subscription record
            $sql = "INSERT INTO subscriptions (
                user_id, plan, status, price_uah, starts_at, expires_at, 
                stripe_subscription_id, auto_renew, created_at
            ) VALUES (?, ?, 'active', ?, NOW(), ?, ?, TRUE, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $plan, $price, $expiresAt, $stripeSubscriptionId]);

            $this->db->commit();

            return [
                'plan' => $plan,
                'price' => $price,
                'expires_at' => $expiresAt,
                'status' => 'active'
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function getUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ? AND is_active = TRUE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function getUserById($id) {
        $sql = "SELECT * FROM users WHERE id = ? AND is_active = TRUE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function createFreeSubscription($userId) {
        $sql = "INSERT INTO subscriptions (
            user_id, plan, status, price_uah, starts_at, expires_at, auto_renew
        ) VALUES (?, 'free', 'active', 0, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), FALSE)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
    }

    private function resetDailySearchCount($userId) {
        $sql = "UPDATE users SET 
                searches_used_today = 0, 
                last_search_reset = CURDATE() 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
    }

    private function updateUsageStats($userId, $tokensUsed, $costUsd) {
        $sql = "INSERT INTO usage_stats (user_id, date, searches_count, tokens_used, api_cost_usd) 
                VALUES (?, CURDATE(), 1, ?, ?)
                ON DUPLICATE KEY UPDATE 
                searches_count = searches_count + 1,
                tokens_used = tokens_used + VALUES(tokens_used),
                api_cost_usd = api_cost_usd + VALUES(api_cost_usd)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $tokensUsed, $costUsd]);
    }

    private function getSearchesRemaining($userId) {
        $check = $this->canUserSearch($userId);
        return $check['remaining'];
    }
}
?>