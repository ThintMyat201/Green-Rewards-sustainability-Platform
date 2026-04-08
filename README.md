# Green Rewards Sustainability Platform

Green Rewards is a role-based, gamified campus sustainability platform built with vanilla PHP and MySQL.

Users can log eco activities, earn points, complete challenges, take quizzes, and redeem rewards.

## Core Features

- Role-based access for admin, moderator, student, and staff
- Session-based authentication with password hashing
- Electricity logging with benchmark-based point rewards
- Daily/monthly challenges with image proof submission
- Quiz system with scoring and attempt history
- Leaderboards and points history
- Rewards catalog and redemption flow
- Achievement badges, streak tracking, and virtual tree progress
- Admin analytics, user management, and settings

## Tech Stack

- PHP 8+
- MySQL / MariaDB
- HTML/CSS/JavaScript (no framework)

## Project Structure

```text
green-rewards/
	admin/         # Admin pages
	auth/          # Login, register, logout
	community/     # Shared pages (quiz, challenges, rewards, points, tips)
	config/        # DB connection and helper functions
	css/           # Styles
	database/      # schema.sql and seed.sql
	includes/      # Shared header/footer templates
	js/            # Frontend scripts
	mod/           # Moderator pages
	staff/         # Staff dashboard
	student/       # Student pages
	uploads/       # Challenge proof uploads
	index.php      # Entry point and role-based redirect
```

## Quick Start (macOS + MAMP)

1. Start MAMP (Apache and MySQL).
2. Import database schema and seed data:

```bash
/Applications/MAMP/Library/bin/mysql -uroot -proot < database/schema.sql
/Applications/MAMP/Library/bin/mysql -uroot -proot green_rewards < database/seed.sql
```

3. Ensure upload directory is writable:

```bash
chmod -R 775 uploads
```

4. Run with PHP built-in server from project root:

```bash
php -S 127.0.0.1:8000
```

5. Open:

```text
http://127.0.0.1:8000
```

## Alternative Run Mode (MAMP Apache)

If this project is inside MAMP htdocs, you can also run it via Apache:

```text
http://localhost:8888/green-rewards
```

## Database Configuration

Database settings are in `config/db.php`.

Defaults are compatible with local MAMP:

- Host: `localhost`
- Port: `3306`
- User: `root`
- Password: `root` or empty string (the app attempts both)
- Database: `green_rewards`
- Socket: `/Applications/MAMP/tmp/mysql/mysql.sock`

You can override via environment variables:

- `DB_HOST`
- `DB_PORT`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`
- `DB_SOCKET`

## Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@mail.apu.edu.my | admin123 |
| Moderator | mod@mail.apu.edu.my | mod123 |
| Student | student1@mail.apu.edu.my | student123 |
| Staff | staff1@mail.apu.edu.my | staff123 |

## Role Overview

- Admin: user management, rewards, reports, benchmark/settings
- Moderator: challenge/quiz creation, submission verification
- Student: log electricity, join challenges, take quizzes, redeem rewards
- Staff: post eco tips, participate in community activities

## Security Notes

- Passwords are hashed using PHP password hashing APIs
- SQL operations use prepared statements (PDO)
- Access control is enforced by role checks and session state
- Uploads are validated and stored in `uploads/`

## Troubleshooting

### "Database connection failed"

- Confirm MySQL is running in MAMP
- Verify credentials in `config/db.php`
- Confirm the `green_rewards` database exists

Quick check:

```bash
/Applications/MAMP/Library/bin/mysql -uroot -proot -e "SHOW DATABASES LIKE 'green_rewards';"
```

### Schema import error around departments

If your schema import fails near the departments seed, open `database/schema.sql` and ensure departments seed columns match the table definition before re-importing.

### Upload problems

- Ensure `uploads/` exists and is writable by the web server user.

## Notes

- Base URL handling supports running from root or a subdirectory through helper functions in `config/functions.php`.
- Registration currently enforces the `@mail.apu.edu.my` domain.

## License

This project is for educational use.
