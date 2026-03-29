# 🚀 Green Rewards Platform - Deployment Guide

## ✅ System Status

### Services Running
- ✅ **MariaDB**: Port 3306
- ✅ **PHP 8.2-FPM**: Socket /var/run/php/php8.2-fpm.sock
- ✅ **Nginx**: Port 8000

### Application URL
**Access the application at:** `http://localhost:8000`

---

## 📊 Database Information

### Database Name
`green_rewards`

### Tables Created (13 total)
1. ✅ users (8 users seeded)
2. ✅ points_log
3. ✅ electricity_logs  
4. ✅ challenges (5 active challenges)
5. ✅ challenge_submissions
6. ✅ quizzes (10 questions)
7. ✅ quiz_attempts
8. ✅ rewards (10 rewards)
9. ✅ redemptions
10. ✅ achievements (10 badges)
11. ✅ user_achievements
12. ✅ eco_tips (6 tips)
13. ✅ settings (5 configuration items)

---

## 🔐 Login Credentials

### Admin
- **URL**: http://localhost:8000/login.php
- **Email**: admin@green.edu
- **Password**: admin123
- **Access**: Full system control

### Moderator
- **Email**: mod@green.edu
- **Password**: mod123
- **Access**: Challenge/quiz management, submission verification

### Student (Test User 1)
- **Email**: student1@green.edu
- **Password**: student123
- **Points**: 450
- **Department**: Computer Science

### Staff
- **Email**: staff1@green.edu
- **Password**: staff123
- **Department**: Computer Science

---

## 🧪 Testing Checklist

### ✅ Student Features Test
- [ ] Login as student1@green.edu
- [ ] View dashboard (should show 450 points)
- [ ] Log electricity usage (/electricity.php)
- [ ] Take a quiz (/quiz.php)
- [ ] View and submit challenge (/challenges.php)
- [ ] Check leaderboard (/leaderboard.php)
- [ ] Browse rewards shop (/rewards.php)
- [ ] View achievements (/achievements.php)
- [ ] Check virtual tree (/tree.php)
- [ ] View points history (/points.php)

### ✅ Staff Features Test
- [ ] Login as staff1@green.edu
- [ ] View staff dashboard
- [ ] Post eco-tip (/eco_tips.php)
- [ ] View department statistics
- [ ] Participate in challenge

### ✅ Moderator Features Test
- [ ] Login as mod@green.edu
- [ ] Create new challenge (/mod/challenges.php)
- [ ] Create quiz question (/mod/quiz.php)
- [ ] Verify submissions (/mod/verify.php)

### ✅ Admin Features Test
- [ ] Login as admin@green.edu
- [ ] View admin dashboard (/admin/dashboard.php)
- [ ] Manage users (/admin/users.php)
- [ ] Create/manage rewards (/admin/rewards.php)
- [ ] View system reports (/admin/reports.php)
- [ ] Update settings (/admin/benchmark.php)

---

## 🛠️ Service Management Commands

### Start All Services
```bash
sudo service mariadb start
sudo service php8.2-fpm start
sudo service nginx start
```

### Stop All Services
```bash
sudo service nginx stop
sudo service php8.2-fpm stop
sudo service mariadb stop
```

### Restart Services
```bash
sudo service mariadb restart
sudo service php8.2-fpm restart
sudo service nginx restart
```

### Check Service Status
```bash
sudo service mariadb status
sudo service php8.2-fpm status
sudo service nginx status
```

---

## 🐛 Troubleshooting

### Issue: Cannot connect to database
**Solution:**
```bash
sudo service mariadb restart
sudo mysql -e "SHOW DATABASES;"
```

### Issue: PHP pages not loading
**Solution:**
```bash
sudo service php8.2-fpm restart
sudo tail -f /var/log/php8.2-fpm.log
```

### Issue: 404 errors
**Solution:**
```bash
sudo nginx -t
sudo service nginx restart
```

### Issue: Cannot upload images
**Solution:**
```bash
sudo chmod 777 /app/uploads
ls -la /app/uploads
```

### Reset Database
```bash
sudo mysql -e "DROP DATABASE IF EXISTS green_rewards;"
sudo mysql < /app/database/schema.sql
sudo mysql < /app/database/seed.sql
```

---

## 📁 Important File Locations

### Configuration
- Database config: `/app/config/db.php`
- Helper functions: `/app/config/functions.php`
- Nginx config: `/etc/nginx/sites-available/green-rewards`

### Uploads
- Challenge images: `/app/uploads/`

### Logs
- PHP-FPM: `/var/log/php8.2-fpm.log`
- Nginx access: `/var/log/nginx/access.log`
- Nginx error: `/var/log/nginx/error.log`

---

## ⚙️ System Settings (Default)

| Setting | Value | Description |
|---------|-------|-------------|
| Electricity Benchmark | 150 kWh | Monthly usage threshold |
| Points per kWh Saved | 5 points | Reward rate |
| Quiz Daily Limit | 5 quizzes | Max per user per day |
| 7-Day Streak Bonus | 50 points | Weekly milestone |
| 30-Day Streak Bonus | 200 points | Monthly milestone |

**Update these in Admin Panel**: http://localhost:8000/admin/benchmark.php

---

## 📊 Sample Data Included

### Users
- 1 Admin
- 1 Moderator
- 4 Students (with varying points)
- 2 Staff members

### Content
- 5 Active challenges (daily & monthly)
- 10 Quiz questions (varying difficulty)
- 10 Rewards (vouchers, merchandise, privileges)
- 10 Achievements (unlockable badges)
- 6 Eco-tips
- Sample electricity logs
- Sample submissions & redemptions

---

## 🎯 Feature Highlights

### Points System
- Electricity savings: 5 pts/kWh saved
- Quiz correct answer: 10 pts
- Challenge completion: 50-200 pts
- Streak bonuses: 50-200 pts

### Gamification
- 🏆 Leaderboards (student & staff)
- 🏅 Achievement badges
- 🔥 Streak tracking
- 🌳 Virtual tree pet (5 growth stages)

### Admin Tools
- User role management
- Points adjustment
- Reward inventory control
- Comprehensive analytics
- System configuration

---

## 🚀 Quick Start Guide

1. **Access Application**
   ```
   http://localhost:8000
   ```

2. **Login as Admin**
   ```
   Email: admin@green.edu
   Password: admin123
   ```

3. **Explore Features**
   - Check dashboard for system overview
   - View reports for analytics
   - Create a test reward
   - Adjust system settings

4. **Test Student Flow**
   - Logout and login as student1@green.edu
   - Complete a quiz
   - Log electricity usage
   - Submit a challenge
   - Redeem a reward

---

## 📞 Support

For issues or questions:
1. Check troubleshooting section above
2. Review logs for error messages
3. Verify all services are running
4. Check database connectivity

---

**🌿 Green Rewards Platform v1.0**  
*Building a sustainable future, one point at a time!* 🌍💚
