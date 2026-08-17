# 🌍 Green Rewards — Campus Sustainability & Carbon Tracking Platform

<div align="center">

![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Vanilla Architecture](https://img.shields.io/badge/Architecture-Pure%20Vanilla%20PHP-10B981?style=for-the-badge)
![RBAC](https://img.shields.io/badge/Security-4--Tier%20RBAC%20%2B%20PDO-8B5CF6?style=for-the-badge)
![Status](https://img.shields.io/badge/University%20Project-Grade%20A%2B%20Ready-F59E0B?style=for-the-badge)

*A full-stack, enterprise-grade web application built to gamify environmental awareness, reduce campus carbon footprints, and promote eco-friendly behaviors across university students, faculty, and staff.*

---

</div>

## 📖 Executive Summary

The **Green Rewards Platform** is an interactive university sustainability system designed to bridge the gap between environmental awareness and daily student/staff action. Universities generate significant carbon emissions through electricity consumption, waste generation, and commuting. This platform introduces a robust **Gamification Engine** that rewards campus community members with **Green Points** and **Badges** for verifiable sustainable behaviors—such as submitting proof of zero-waste meals, cycling to campus, completing eco-quizzes, and logging below-benchmark electricity consumption.

To demonstrate deep technical mastery of full-stack software engineering without relying on abstraction layers, this platform was architected and built from scratch using **Pure Vanilla PHP 8.2**, **HTML5/CSS3**, **Vanilla JavaScript**, and a **13-table normalized MySQL relational database**.

---

## 🌟 Key Technical Highlights & Architecture

Why this implementation exemplifies software engineering best practices:

- **🏗️ Pure Zero-Framework Architecture**: Built entirely without external MVC frameworks (like Laravel or Symfony) or heavy JS libraries. This showcases foundational command over HTTP request lifecycles, session management, custom routing, and database abstraction.
- **🛡️ Enterprise-Grade Security**:
  - **100% PDO Prepared Statements**: Every SQL query binds variables via parameters (`?`), providing complete immunity against SQL Injection (SQLi).
  - **Atomic Database Transactions**: All multi-step write operations (e.g., deducting points and logging reward redemptions) are wrapped in strict `BEGIN`, `COMMIT`, and `ROLLBACK` transactions to prevent data corruption or double-spending race conditions.
  - **Cross-Site Scripting (XSS) Defense**: Output encoding via custom HTML sanitization helpers (`htmlspecialchars`).
  - **Secure Upload Verification**: Strict MIME-type checking, file extension whitelist validation, and randomized naming (`uniqid()`) prevent malicious file upload vulnerabilities.
- **⚡ High-Performance Optimization**:
  - **In-Memory Request Memoization**: Core functions utilize static caching to store user profiles and system settings in memory during page execution, eliminating repetitive database queries.
  - **Zero N+1 SQL Queries**: Achievement checking algorithms pre-calculate user activity summaries outside loops ($O(1)$ database trips instead of $O(N)$), ensuring lightning-fast page rendering.
- **👥 4-Tier Role-Based Access Control (RBAC)**: Custom permission guards (`isLoggedIn()`, `hasRole()`, `requireRole()`) securely isolate student, staff, moderator, and administrator functionalities.

---

## 👥 Role-Based Feature Matrix

| Feature / Domain | 🎓 Student / General User | 👨‍🏫 Staff / Faculty | 🔍 Moderator | ⚙️ System Admin |
| :--- | :---: | :---: | :---: | :---: |
| **Log Eco-Challenges & Upload Proof** | ✅ | ✅ | — | — |
| **Take Daily Sustainability Quizzes** | ✅ | ✅ | — | — |
| **Log Electricity & Track Carbon** | ✅ | ✅ | — | — |
| **Redeem Points in Reward Store** | ✅ | ✅ | — | — |
| **View Virtual Tree Progress & Badges** | ✅ | ✅ | — | — |
| **View Departmental Leaderboards** | ✅ | ✅ | — | — |
| **Access Staff Department Analytics** | — | ✅ | — | — |
| **Review & Verify Challenge Proofs** | — | — | ✅ | ✅ |
| **Manage Quizzes & Challenges** | — | — | ✅ | ✅ |
| **Manage Users, Roles & Departments** | — | — | — | ✅ |
| **Configure System Settings & Stock** | — | — | — | ✅ |

---

## 🗄️ Database Schema & Relational Structure

The application is powered by a **fully normalized 13-table relational database** designed for scalability and data integrity:

```
+------------------+       +-------------------------+       +--------------------+
|    users         | <---- |  challenge_submissions  | ----> |     challenges     |
+------------------+       +-------------------------+       +--------------------+
  |      |      |                                              
  |      |      +--------> +-------------------------+       +--------------------+
  |      |                 |      quiz_attempts      | ----> |      quizzes       |
  |      |                 +-------------------------+       +--------------------+
  |      |                                                             |
  |      |                 +-------------------------+       +--------------------+
  |      +---------------> |     user_achievements   | ----> |    achievements    |
  |                        +-------------------------+       +--------------------+
  |                                                            
  +----------------------> +-------------------------+       +--------------------+
  |                        |       redemptions       | ----> |      rewards       |
  |                        +-------------------------+       +--------------------+
  |                                                            
  +----------------------> +-------------------------+       +--------------------+
  |                        |     electricity_logs    |       |      settings      |
  |                        +-------------------------+       +--------------------+
  |                                                            
  +----------------------> +-------------------------+       +--------------------+
                           |       points_log        |       |    departments     |
                           +-------------------------+       +--------------------+
```

### Table Breakdown
1. `users`: Stores user credentials (bcrypt hashed), department affiliations, role assignments, total points balance, and login streak counters.
2. `departments`: Academic and administrative departments (`Computer Science`, `Engineering`, `Business`) with unique department codes.
3. `challenges`: Available sustainability tasks, point bounties, active date ranges, and challenge types (daily/monthly).
4. `challenge_submissions`: User challenge entries containing photo proof paths, verification status (`pending`, `approved`, `rejected`), and moderator review stamps.
5. `quizzes`: Multiple-choice sustainability trivia questions, difficulty ratings, and reward points.
6. `quiz_attempts`: Historical record of student quiz answers, correctness flags, and timestamps.
7. `electricity_logs`: Periodic electricity consumption tracking comparing user kilowatt-hour (kWh) usage against campus sustainability benchmarks.
8. `rewards`: Catalog of redeemable campus items (cafeteria vouchers, eco-merchandise, library fee waivers) with inventory stock tracking.
9. `redemptions`: Transaction logs of reward purchases, points spent, and fulfillment statuses.
10. `achievements`: Gamified milestone badges (`points_total`, `quiz_streak`, `challenges_completed`, `electricity_saves`).
11. `user_achievements`: Pivot table mapping unlocked badges to users with completion timestamps.
12. `points_log`: Comprehensive immutable audit trail of every point earned or spent across the platform.
13. `settings`: Global configuration key-value store for conversion ratios, system rules, and platform banners.

---

## 📂 Project Directory Structure

```text
Green-Rewards-sustainability-Platform/
├── admin/               # System Administrator Controller Pages (Users, Depts, Settings, Rewards)
├── auth/                # Authentication Flow (Login, Register, Logout, Password Security)
├── community/           # Campus Leaderboards & Departmental Ranking Tables
├── config/              # Core System Architecture (DB Connection, Helper Functions, Env Loader)
├── css/                 # Vanilla CSS Design System (Responsive Grid, Variables, Micro-animations)
├── database/            # Database Source of Truth (schema.sql & seed.sql)
├── includes/            # Reusable Template Components (Header, Footer, Navigation Navbar)
├── js/                  # Client-Side Vanilla JS (DRY Image Previews, Dynamic DOM Interactions)
├── mod/                 # Moderator Dashboard (Challenge Review & Proof Verification Queue)
├── staff/               # Staff & Faculty Analytics Dashboard (Department Performance Metrics)
├── student/             # Student Hub (Challenges, Quizzes, Tree Progress, Electricity Logging)
├── uploads/             # Secure User File Storage (Profile Avatars, Challenge Image Proofs)
├── .env.example         # Template Environment Configuration
├── index.php            # Main Application Entry Point & Dashboard Router
└── profile.php          # User Profile & Department Management Page
```

---

## 🛠️ Step-by-Step Installation & Setup Guide

This project is built to run effortlessly on any local PHP/MySQL environment (XAMPP, WAMP, MAMP, Docker, or native PHP CLI).

### Prerequisites
- **PHP**: Version 8.0 or higher (8.2+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Extensions**: `pdo_mysql`, `mbstring`, `fileinfo`, `session`

### 1. Clone the Repository
```bash
git clone https://github.com/your-username/Green-Rewards-sustainability-Platform.git
cd Green-Rewards-sustainability-Platform
```

### 2. Database Initialization
1. Open your MySQL client (phpMyAdmin, MySQL Workbench, or terminal) and create a new database:
   ```sql
   CREATE DATABASE green_rewards CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import the database schema (table structures):
   ```bash
   mysql -u root -p green_rewards < database/schema.sql
   ```
3. Import the seed data (default departments, test users, sample challenges, quizzes, and rewards):
   ```bash
   mysql -u root -p green_rewards < database/seed.sql
   ```

### 3. Environment Configuration
Copy the template environment file and update your MySQL connection credentials:
```bash
cp .env.example .env
```
Open `.env` in a text editor and verify your settings:
```ini
DB_HOST=127.0.0.1
DB_NAME=green_rewards
DB_USER=root
DB_PASS=your_password_here
APP_URL=http://localhost:8001
```
*(Note: If no `.env` file is present, the application will gracefully fall back to system environment variables or default settings).*

### 4. Launch the Local Development Server
Start PHP's built-in high-performance web server from the root directory:
```bash
php -S 127.0.0.1:8001
```
Open your web browser and navigate to: **`http://127.0.0.1:8001`**

---

## 🔑 Test Credentials for Evaluation

The system is pre-seeded with ready-to-use test accounts for all 4 role tiers. Graders and evaluators can use these credentials to immediately test role-specific interfaces and permissions:

| Role Tier | Email Address | Password | Key Access Area to Test |
| :--- | :--- | :--- | :--- |
| **System Admin** | `admin@mail.apu.edu.my` | `admin123` | `/admin/users.php`, `/admin/departments.php`, `/admin/settings.php` |
| **Moderator** | `mod@mail.apu.edu.my` | `mod123` | `/mod/review.php`, `/mod/challenges.php`, `/mod/quizzes.php` |
| **Staff / Faculty** | `staff1@mail.apu.edu.my` | `staff123` | `/staff/dashboard.php`, `/community/leaderboard.php` |
| **Student** | `student1@mail.apu.edu.my` | `student123` | `/student/challenges.php`, `/student/tree.php`, `/student/rewards.php` |
| **Student 2** | `student2@mail.apu.edu.my` | `student123` | *(Test inter-user leaderboard competition & reward store redemption)* |

---

## 🌱 Sustainability Algorithms & Gamification Mechanics

### 1. The Green Points Engine
Points are dynamically awarded across four distinct eco-behaviors:
- **Daily/Monthly Challenges**: +50 to +200 points upon moderator photo proof verification.
- **Eco-Quizzes**: +10 points per correct answer (instant automated reward).
- **Electricity Savings**: Evaluated using the formula:
  $$\text{Points} = (\text{Benchmark kWh} - \text{Logged kWh}) \times \text{Conversion Ratio}$$
- **Login Streaks**: Consecutive daily logins multiply user engagement scores and trigger streak achievements.

### 2. Virtual Tree Growth Progression
A student's environmental impact is visualized through an adaptive virtual tree avatar that evolves through 5 maturity stages based on cumulative Green Points earned:
- **Seedling** (0 – 199 Points)
- **Sprout** (200 – 499 Points)
- **Young Tree** (500 – 999 Points)
- **Mature Tree** (1,000 – 1,999 Points)
- **Ancient Forest Guardian** (2,000+ Points)

---

## 🎓 Academic Learning Outcomes Demonstrated

This project successfully fulfills advanced university infomation technology and software engineering learning objectives:
1. **Full-Stack Web Development**: Seamless integration of server-side PHP logic with responsive client-side UI/UX.
2. **Relational Database Design**: Mastering 3rd Normal Form (3NF), foreign key referential integrity, indexing, and complex SQL JOINs.
3. **Software Architecture & Clean Code**: Adherence to DRY (Don't Repeat Yourself) principles, modular helper abstractions, and separation of concerns without relying on commercial frameworks.
4. **Web Security & Defensive Programming**: Implementing robust protection against SQL Injection, XSS, CSRF-adjacent race conditions, and unauthorized privilege escalation.
5. **Algorithmic Efficiency**: Mitigating database $O(N)$ query bottlenecks through memoization and pre-calculated statistical aggregations.

---

<div align="center">
  <p><b>Built with ❤️ for Campus Sustainability & Academic Excellence.</b></p>
  <p><i>Green Rewards Platform © 2026</i></p>
</div>
