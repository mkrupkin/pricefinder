-- backend/database/migrations/001_create_tables.sql
-- Основні таблиці для PriceFinder

USE pricefinder;

-- Користувачі та авторизація
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    country VARCHAR(50) DEFAULT 'Ukraine',
    city VARCHAR(100) DEFAULT 'Kyiv',
    language VARCHAR(10) DEFAULT 'uk',
    subscription_plan ENUM('free', 'explorer', 'universal', 'business', 'enterprise') DEFAULT 'free',
    subscription_status ENUM('active', 'cancelled', 'expired', 'trial') DEFAULT 'active',
    subscription_expires_at DATETIME NULL,
    searches_used_today INT DEFAULT 0,
    searches_used_total INT DEFAULT 0,
    last_search_reset DATE DEFAULT (CURRENT_DATE),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_subscription (subscription_plan, subscription_status),
    INDEX idx_searches (searches_used_today, last_search_reset)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Історія пошуку продуктів
CREATE TABLE IF NOT EXISTS search_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    session_id VARCHAR(64), -- Для анонімних користувачів
    search_type ENUM('photo', 'text') NOT NULL,
    search_query VARCHAR(500),
    product_identified JSON, -- Результати ідентифікації продукту
    search_results JSON NOT NULL, -- Всі знайдені магазини
    user_location JSON, -- Локація користувача
    processing_time_ms INT,
    tokens_used INT DEFAULT 0,
    api_cost_usd DECIMAL(8,4) DEFAULT 0,
    result_count INT,
    user_rating TINYINT NULL, -- Оцінка користувача 1-5
    user_feedback TEXT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_session (session_id, created_at),
    INDEX idx_search_type (search_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Улюблені продукти
CREATE TABLE IF NOT EXISTS favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_image_url VARCHAR(500),
    category VARCHAR(100),
    brand VARCHAR(100),
    best_price_uah DECIMAL(10,2),
    best_store_name VARCHAR(255),
    best_store_url VARCHAR(500),
    price_alert_enabled BOOLEAN DEFAULT FALSE,
    price_alert_threshold DECIMAL(10,2),
    search_data JSON, -- Оригінальні результати пошуку
    notes TEXT,
    tags JSON, -- Теги від користувача
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_name),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_price_alerts (price_alert_enabled, price_alert_threshold)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Системні налаштування
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value JSON NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка базових налаштувань
INSERT INTO system_settings (setting_key, setting_value, description, is_public) VALUES
('search_limits', '{"free": 2, "explorer": 15, "universal": 100, "business": -1, "enterprise": -1}', 'Денні ліміти пошуку по планах', true),
('pricing', '{"explorer": 89, "universal": 199, "business": 799, "enterprise": 2499}', 'Щомісячні ціни в UAH', true),
('features', '{"explorer": ["basic_search", "favorites"], "universal": ["global_search", "price_alerts", "export"], "business": ["api_access", "bulk_search"], "enterprise": ["custom_integration", "priority_support"]}', 'Функції по планах', true),
('openai_config', '{"model_vision": "gpt-4-vision-preview", "model_text": "gpt-4", "max_tokens": 2000, "temperature": 0.3}', 'Конфігурація OpenAI API', false)
ON DUPLICATE KEY UPDATE
setting_value = VALUES(setting_value),
updated_at = CURRENT_TIMESTAMP;

-- Створення тестового користувача
INSERT INTO users (email, password_hash, first_name, last_name, subscription_plan, email_verified) VALUES
('test@pricefinder.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test', 'User', 'universal', true)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;