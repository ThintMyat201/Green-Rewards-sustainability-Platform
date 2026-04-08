-- Green Rewards System Database Schema
-- MySQL/MariaDB

DROP DATABASE IF EXISTS green_rewards;
CREATE DATABASE green_rewards CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE green_rewards;

-- 1. Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'moderator', 'student', 'staff') NOT NULL DEFAULT 'student',
    department VARCHAR(100) DEFAULT NULL,
    points_total INT DEFAULT 0,
    streak_count INT DEFAULT 0,
    last_activity_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_department (department),
    INDEX idx_points (points_total DESC)
) ENGINE=InnoDB;

-- 1b. Departments Table
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB;

-- 2. Points Log Table
CREATE TABLE points_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    source ENUM('electricity', 'quiz', 'challenge', 'recycling', 'streak_bonus', 'admin_adjust') NOT NULL,
    points_earned INT NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_source (source),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- 3. Electricity Logs Table
CREATE TABLE electricity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    units_used DECIMAL(10,2) NOT NULL,
    benchmark DECIMAL(10,2) DEFAULT 150.00,
    points_awarded INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_month (user_id, month, year),
    INDEX idx_user (user_id),
    INDEX idx_date (year, month)
) ENGINE=InnoDB;

-- 4. Challenges Table
CREATE TABLE challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    type ENUM('daily', 'monthly') NOT NULL,
    points_reward INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_by INT NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type (type),
    INDEX idx_active (is_active),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB;

-- 5. Challenge Submissions Table
CREATE TABLE challenge_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    user_id INT NOT NULL,
    proof_image_path VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    review_note TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    UNIQUE KEY unique_user_challenge (user_id, challenge_id),
    INDEX idx_status (status),
    INDEX idx_challenge (challenge_id)
) ENGINE=InnoDB;

-- 6. Quizzes Table
CREATE TABLE quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    option_a VARCHAR(200) NOT NULL,
    option_b VARCHAR(200) NOT NULL,
    option_c VARCHAR(200) NOT NULL,
    option_d VARCHAR(200) NOT NULL,
    correct_option ENUM('a', 'b', 'c', 'd') NOT NULL,
    points_reward INT DEFAULT 10,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    created_by INT NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_active (is_active),
    INDEX idx_difficulty (difficulty)
) ENGINE=InnoDB;

-- 7. Quiz Attempts Table
CREATE TABLE quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    selected_option ENUM('a', 'b', 'c', 'd') NOT NULL,
    is_correct BOOLEAN NOT NULL,
    points_earned INT DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_quiz (quiz_id),
    INDEX idx_date (attempted_at)
) ENGINE=InnoDB;

-- 8. Rewards Table
CREATE TABLE rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    type ENUM('voucher', 'merchandise', 'privilege') NOT NULL,
    points_cost INT NOT NULL,
    stock_qty INT DEFAULT 0,
    image_path VARCHAR(255),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_type (type),
    INDEX idx_cost (points_cost)
) ENGINE=InnoDB;

-- 9. Redemptions Table
CREATE TABLE redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reward_id INT NOT NULL,
    points_spent INT NOT NULL,
    status ENUM('pending', 'fulfilled', 'cancelled') DEFAULT 'pending',
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fulfilled_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES rewards(id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_date (redeemed_at)
) ENGINE=InnoDB;

-- 10. Achievements Table
CREATE TABLE achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    badge_icon VARCHAR(50),
    condition_type ENUM('points_total', 'electricity_saves', 'quiz_streak', 'challenges_completed', 'first_action') NOT NULL,
    condition_value INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_condition (condition_type)
) ENGINE=InnoDB;

-- 11. User Achievements Table
CREATE TABLE user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user (user_id),
    INDEX idx_date (unlocked_at)
) ENGINE=InnoDB;

-- 12. Eco Tips Table
CREATE TABLE eco_tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    posted_by INT NOT NULL,
    content TEXT NOT NULL,
    category ENUM('recycling', 'energy', 'water', 'waste', 'transportation', 'general') DEFAULT 'general',
    is_pinned BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id),
    INDEX idx_posted_by (posted_by),
    INDEX idx_category (category),
    INDEX idx_pinned (is_pinned),
    INDEX idx_date (created_at DESC)
) ENGINE=InnoDB;

-- 13. Settings Table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255),
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('electricity_benchmark', '150', 'Monthly electricity usage benchmark in kWh'),
('points_per_kwh_saved', '5', 'Points awarded per kWh saved below benchmark'),
('quiz_daily_limit', '5', 'Maximum quizzes per day per user'),
('streak_bonus_7days', '50', 'Bonus points for 7-day streak'),
('streak_bonus_30days', '200', 'Bonus points for 30-day streak');

INSERT INTO departments (name, is_active) VALUES
('Computer Science', 1),
('Engineering', 1),
('Business', 1);
