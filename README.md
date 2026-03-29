# 🌿 Green Rewards Sustainability Platform

A comprehensive gamified eco-awareness system for campus sustainability.

## 🎯 Features

### 4 User Roles
- **Admin** - System management, user control, rewards, reports
- **Moderator** - Challenge/quiz creation, submission verification  
- **Student** - Earn points, complete challenges, redeem rewards
- **Staff** - Post eco-tips, participate in challenges

### Core Functionality
✅ **Authentication** - JWT-based with bcrypt password hashing
⚡ **Electricity Logging** - Track usage vs 150 kWh benchmark
🏆 **Points & Rewards Engine** - Central calculation system
🎯 **Challenges** - Daily/monthly with photo proof submission
📝 **Quiz System** - Daily eco-awareness quizzes
📊 **Leaderboards** - Separate student/staff rankings
🎁 **Rewards Shop** - Redeem points for campus perks
🏅 **Achievements** - Auto-unlock milestone badges
🔥 **Streak System** - Consecutive activity bonuses
🌳 **Virtual Tree Pet** - Grows with your points
💡 **Eco Tips Feed** - Community sustainability tips
📈 **Admin Analytics** - Comprehensive reporting

## 🗄️ Database Schema

13 MySQL tables:
- `users` - User accounts with roles
- `points_log` - All point transactions
- `electricity_logs` - Monthly electricity tracking
- `challenges` - Challenge definitions
- `challenge_submissions` - User submissions with images
- `quizzes` - Quiz questions database
- `quiz_attempts` - User quiz history
- `rewards` - Reward catalog
- `redemptions` - Redemption transactions
- `achievements` - Badge definitions
- `user_achievements` - Unlocked badges
- `eco_tips` - Community tips feed
- `settings` - System configuration

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+
- MySQL/MariaDB
- Nginx

### Installation

1. **Database Setup**
```bash
sudo mysql < /app/database/schema.sql
sudo mysql < /app/database/seed.sql
```

2. **Configure Database**
Edit `/app/config/db.php` if needed (default uses root@localhost)

3. **Set Permissions**
```bash
chmod 777 /app/uploads
```

4. **Start Services**
```bash
sudo service mariadb start
sudo service php8.2-fpm start
sudo service nginx start
```

5. **Access Application**
Open browser to: `http://localhost:8000`

## 👥 Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@green.edu | admin123 |
| **Moderator** | mod@green.edu | mod123 |
| **Student** | student1@green.edu | student123 |
| **Staff** | staff1@green.edu | staff123 |

## 📁 Project Structure

```
/app/
├── config/
│   ├── db.php              # Database connection
│   └── functions.php       # Helper functions
├── includes/
│   ├── header.php          # Navigation header
│   └── footer.php          # Footer template
├── admin/
│   ├── dashboard.php       # Admin overview
│   ├── users.php           # User management
│   ├── rewards.php         # Reward management
│   ├── reports.php         # Analytics & reports
│   └── benchmark.php       # System settings
├── mod/
│   ├── verify.php          # Verify submissions
│   ├── challenges.php      # Create challenges
│   └── quiz.php            # Create quizzes
├── student/
│   └── dashboard.php       # Student dashboard
├── staff/
│   └── dashboard.php       # Staff dashboard
├── uploads/                # Challenge proof images
├── css/
│   └── style.css           # Main stylesheet
├── js/
│   └── main.js             # JavaScript
├── database/
│   ├── schema.sql          # Database structure
│   └── seed.sql            # Sample data
├── login.php
├── register.php
├── electricity.php
├── quiz.php
├── challenges.php
├── leaderboard.php
├── rewards.php
├── achievements.php
├── tree.php
├── eco_tips.php
└── points.php
```

## ⚙️ Configuration

### System Settings (Admin Panel)

- **Electricity Benchmark**: Default 150 kWh/month
- **Points per kWh Saved**: 5 points
- **Quiz Daily Limit**: 5 per user
- **7-Day Streak Bonus**: 50 points
- **30-Day Streak Bonus**: 200 points

### Departments Available
- Computer Science
- Engineering  
- Business

## 🎮 How to Use

### For Students:
1. Register with student role
2. Log monthly electricity usage
3. Take daily quizzes
4. Complete challenges (with photo proof)
5. Earn points and climb leaderboard
6. Redeem points for rewards
7. Unlock achievements
8. Watch your virtual tree grow!

### For Staff:
1. Post eco-awareness tips
2. Participate in challenges
3. View department statistics
4. Compete on staff leaderboard

### For Moderators:
1. Create daily/monthly challenges
2. Create quiz questions
3. Verify challenge submissions
4. Approve/reject with notes

### For Admins:
1. Manage all users
2. Assign roles
3. Adjust points
4. Create/manage rewards
5. Set electricity benchmarks
6. View comprehensive reports
7. Monitor system activity

## 🔒 Security Features

- Bcrypt password hashing
- Prepared statements (SQL injection prevention)
- Session-based authentication
- File upload validation (images only)
- Role-based access control
- Input sanitization

## 📊 Reporting Features

- Total points distributed
- Redemption rates
- Department performance
- Monthly activity trends
- Challenge completion rates
- Quiz accuracy statistics
- Top contributor rankings

## 🎨 UI/UX Features

- Responsive design (mobile-friendly)
- Modern gradient cards
- Progress bars
- Badge system
- Color-coded roles
- Flash messages
- Modal dialogs
- Image preview on upload

## 🐛 Troubleshooting

### Database Connection Issues
```bash
# Check MySQL is running
sudo service mariadb status

# Restart if needed
sudo service mariadb restart
```

### PHP Errors
```bash
# Check PHP-FPM
sudo service php8.2-fpm status

# View logs
sudo tail -f /var/log/php8.2-fpm.log
```

### Nginx Issues
```bash
# Test configuration
sudo nginx -t

# Restart nginx
sudo service nginx restart
```

### File Upload Issues
```bash
# Ensure uploads directory is writable
sudo chmod 777 /app/uploads
```

## 📝 License

MIT License - Free to use and modify

## 🤝 Contributing

Contributions welcome! This is an educational project.

## 📧 Support

For issues or questions, contact your system administrator.

---

**Built with ❤️ for a sustainable future** 🌍💚
