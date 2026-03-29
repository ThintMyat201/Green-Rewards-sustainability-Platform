-- Green Rewards System - Seed Data
USE green_rewards;

-- Seed Users (passwords are hashed with bcrypt)
-- Admin: admin@green.edu / admin123
-- Mod: mod@green.edu / mod123
-- Students: student1@green.edu / student123, student2@green.edu / student123
-- Staff: staff1@green.edu / staff123

INSERT INTO users (name, email, password_hash, role, department, points_total, streak_count) VALUES
-- Admin (password: admin123)
('Admin User', 'admin@green.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, 0, 0),

-- Moderator (password: mod123)
('John Moderator', 'mod@green.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator', NULL, 0, 0),

-- Students (password: student123)
('Alice Student', 'student1@green.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Computer Science', 450, 5),
('Bob Student', 'student2@green.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Engineering', 320, 3),
('Carol Student', 'student3@green.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Business', 580, 7),
('David Student', 'student4@green.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Computer Science', 210, 2),

-- Staff (password: staff123)
('Dr. Smith', 'staff1@green.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'Computer Science', 180, 4),
('Prof. Johnson', 'staff2@green.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'Engineering', 220, 6);

-- Seed Challenges
INSERT INTO challenges (title, description, type, points_reward, start_date, end_date, created_by, is_active) VALUES
('Reusable Water Bottle Challenge', 'Use a reusable water bottle for a week. Submit a photo with your bottle!', 'daily', 50, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 2, 1),
('Zero Waste Lunch', 'Pack a completely zero-waste lunch (no plastic, no disposables). Show us your eco-friendly meal!', 'daily', 75, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 2, 1),
('Campus Clean-Up', 'Participate in campus clean-up activities. Submit a photo of your team in action!', 'monthly', 150, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 1),
('Bike to Campus Week', 'Bike or walk to campus instead of driving for one week', 'monthly', 200, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 1),
('Plant a Tree', 'Plant a tree or help in campus gardening. Document your green contribution!', 'monthly', 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 1);

-- Seed Quizzes
INSERT INTO quizzes (question, option_a, option_b, option_c, option_d, correct_option, points_reward, difficulty, created_by, is_active) VALUES
('What is the average temperature increase needed to be considered "global warming"?', '1°C', '2°C', '5°C', '10°C', 'a', 10, 'medium', 2, 1),
('Which gas is most responsible for global warming?', 'Oxygen', 'Carbon Dioxide', 'Nitrogen', 'Hydrogen', 'b', 10, 'easy', 2, 1),
('What percentage of Earth\'s water is freshwater?', '2.5%', '10%', '25%', '50%', 'a', 10, 'medium', 2, 1),
('How long does it take for a plastic bottle to decompose?', '10 years', '50 years', '450 years', '1000 years', 'c', 10, 'medium', 2, 1),
('Which renewable energy source is the most widely used globally?', 'Solar', 'Wind', 'Hydroelectric', 'Geothermal', 'c', 10, 'hard', 2, 1),
('What does the term "carbon footprint" refer to?', 'Amount of carbon in soil', 'Total greenhouse gas emissions', 'Size of carbon atoms', 'Carbon in atmosphere', 'b', 10, 'easy', 2, 1),
('Which country produces the most solar energy?', 'USA', 'Germany', 'China', 'India', 'c', 10, 'hard', 2, 1),
('What is composting?', 'Burning waste', 'Recycling plastic', 'Decomposing organic matter', 'Filtering water', 'c', 10, 'easy', 2, 1),
('LED bulbs use how much less energy than incandescent bulbs?', '25%', '50%', '75%', '90%', 'c', 10, 'medium', 2, 1),
('What is the main cause of deforestation?', 'Natural disasters', 'Agriculture', 'Urban development', 'Tourism', 'b', 10, 'medium', 2, 1);

-- Seed Rewards
INSERT INTO rewards (name, description, type, points_cost, stock_qty, is_active) VALUES
('Campus Cafeteria Voucher - $5', 'Redeem for $5 off at any campus cafeteria', 'voucher', 100, 50, 1),
('Campus Cafeteria Voucher - $10', 'Redeem for $10 off at any campus cafeteria', 'voucher', 180, 30, 1),
('Eco-Friendly Tote Bag', 'Stylish reusable tote bag with Green Rewards logo', 'merchandise', 250, 25, 1),
('Bamboo Cutlery Set', 'Reusable bamboo fork, spoon, and knife set', 'merchandise', 200, 20, 1),
('Stainless Steel Water Bottle', 'Premium 750ml insulated water bottle', 'merchandise', 350, 15, 1),
('Campus T-Shirt (Organic Cotton)', 'Eco-friendly organic cotton t-shirt', 'merchandise', 300, 20, 1),
('Library Late Fee Waiver', 'One-time waiver for library late fees', 'privilege', 150, 100, 1),
('Priority Parking Pass (1 Month)', 'Reserved parking spot for one month', 'privilege', 500, 10, 1),
('Free Printing Credits (100 pages)', '100 pages of free printing at campus labs', 'privilege', 120, 40, 1),
('Campus Gym - Free Month', 'One month free access to campus fitness center', 'privilege', 400, 15, 1);

-- Seed Achievements
INSERT INTO achievements (name, description, badge_icon, condition_type, condition_value) VALUES
('First Steps', 'Welcome! You\'ve joined the Green Rewards platform', 'seedling', 'first_action', 1),
('Quiz Novice', 'Complete your first quiz', 'book', 'first_action', 1),
('Energy Saver', 'Log electricity usage below benchmark', 'bolt', 'electricity_saves', 1),
('Quiz Master', 'Answer 50 quizzes correctly', 'graduation', 'quiz_streak', 50),
('Point Collector', 'Earn 500 total points', 'coin', 'points_total', 500),
('Sustainability Champion', 'Earn 1000 total points', 'trophy', 'points_total', 1000),
('Challenge Accepted', 'Complete your first challenge', 'target', 'challenges_completed', 1),
('Challenge Warrior', 'Complete 10 challenges', 'sword', 'challenges_completed', 10),
('Week Warrior', 'Maintain a 7-day activity streak', 'fire', 'quiz_streak', 7),
('Eco Legend', 'Earn 2000 total points', 'crown', 'points_total', 2000);

-- Seed Eco Tips
INSERT INTO eco_tips (posted_by, content, category, is_pinned) VALUES
(7, 'Always carry a reusable water bottle! Campus has water fountains everywhere - save money and reduce plastic waste.', 'waste', 1),
(7, 'Turn off lights and unplug devices when leaving your dorm room. Even small actions make a big difference!', 'energy', 1),
(8, 'Consider carpooling or using public transportation. It reduces emissions and saves you money on gas!', 'transportation', 0),
(8, 'Use both sides of paper when printing. Better yet, go digital whenever possible!', 'waste', 0),
(7, 'Take shorter showers to conserve water. Every gallon saved helps our environment!', 'water', 0),
(8, 'Recycle your aluminum cans and bottles. Aluminum can be recycled infinitely!', 'recycling', 0);

-- Seed some sample electricity logs for students
INSERT INTO electricity_logs (user_id, month, year, units_used, benchmark, points_awarded) VALUES
(3, 1, 2025, 120.5, 150, 150),  -- Alice saved 29.5 kWh
(3, 2, 2025, 135.0, 150, 75),   -- Alice saved 15 kWh
(4, 1, 2025, 145.0, 150, 25),   -- Bob saved 5 kWh
(4, 2, 2025, 160.0, 150, 0),    -- Bob exceeded benchmark
(5, 1, 2025, 110.0, 150, 200),  -- Carol saved 40 kWh
(5, 2, 2025, 125.0, 150, 125);  -- Carol saved 25 kWh

-- Seed some points log entries
INSERT INTO points_log (user_id, source, points_earned, description) VALUES
(3, 'electricity', 150, 'Electricity savings for January 2025'),
(3, 'electricity', 75, 'Electricity savings for February 2025'),
(3, 'quiz', 10, 'Correct answer: What is global warming?'),
(3, 'quiz', 10, 'Correct answer: Carbon footprint'),
(3, 'challenge', 50, 'Completed: Reusable Water Bottle Challenge'),
(4, 'electricity', 25, 'Electricity savings for January 2025'),
(4, 'quiz', 10, 'Correct answer: Renewable energy'),
(5, 'electricity', 200, 'Electricity savings for January 2025'),
(5, 'electricity', 125, 'Electricity savings for February 2025'),
(5, 'quiz', 10, 'Correct answer: LED bulbs'),
(5, 'challenge', 75, 'Completed: Zero Waste Lunch'),
(5, 'streak_bonus', 50, '7-day streak bonus!');

-- Seed some quiz attempts
INSERT INTO quiz_attempts (user_id, quiz_id, selected_option, is_correct, points_earned) VALUES
(3, 1, 'a', 1, 10),
(3, 2, 'b', 1, 10),
(3, 3, 'a', 1, 10),
(4, 1, 'b', 0, 0),
(4, 2, 'b', 1, 10),
(5, 4, 'c', 1, 10),
(5, 9, 'c', 1, 10);

-- Seed some user achievements
INSERT INTO user_achievements (user_id, achievement_id) VALUES
(3, 1), -- First Steps
(3, 2), -- Quiz Novice
(3, 3), -- Energy Saver
(3, 5), -- Point Collector
(4, 1), -- First Steps
(4, 2), -- Quiz Novice
(5, 1), -- First Steps
(5, 2), -- Quiz Novice
(5, 3), -- Energy Saver
(5, 5), -- Point Collector
(5, 7), -- Challenge Accepted
(5, 9); -- Week Warrior

-- Seed some challenge submissions
INSERT INTO challenge_submissions (challenge_id, user_id, proof_image_path, status, reviewed_by) VALUES
(1, 3, 'uploads/challenge_3_1.jpg', 'approved', 2),
(2, 5, 'uploads/challenge_5_2.jpg', 'approved', 2),
(1, 4, NULL, 'pending', NULL);

-- Seed some redemptions
INSERT INTO redemptions (user_id, reward_id, points_spent, status) VALUES
(5, 1, 100, 'fulfilled'),
(3, 9, 120, 'fulfilled');
