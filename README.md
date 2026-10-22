# Sunday School & Church Management System (API)

Production-grade RESTful API backend for Orthodox Sunday School management, built with Laravel 12, PHP 8.2+, and Laravel Sanctum.

---

## 🏛️ System Architecture & Overview

The system powers student enrollment, multi-department academic tracking, night/day shift scheduling, QR-based live attendance, grading, and a 3-tier student promotion pipeline.

### The 5 Canonical Roles
Access control is enforced strictly across 5 canonical roles:

| Role Name | Identifier (`name`) | Department / Responsibility |
| :--- | :--- | :--- |
| **Super Admin** | `super_admin` | Full system administration, user management, and final promotion approval |
| **Ye Sew Habt Kfl** | `yesew_habt` | Human resources, student demographic management, Level 2 promotion endorsement |
| **Tmhrt Kfl** | `tmhrt_kfl` | Academic courses, teachers, sections, curriculum, Level 1 promotion nomination |
| **Mezmur Kfl** | `mezmur_kfl` | Hymn repertoire, student vocal tracks, hymn assessments & attendance |
| **Mereja Kfl** | `mereja_kfl` | Read-only statistics, dashboards, records oversight, and report generation |

---

## 🚀 Key Modules & Capabilities

1. **Shift-Aware Attendance System**:
   - Supports Day and Night schedules (including overnight sessions that run past midnight).
   - Automatic lateness calculation against shift start times.
   - Shift-matching verification preventing day students from erroneously being marked in night sessions.
   - High-throughput QR scanner endpoints (`/api/attendance/scan-and-mark`).

2. **3-Tier Student Promotion Pipeline**:
   - **Level 1 (Tmhrt Kfl)**: Evaluates academic threshold (≥ 50%) and attendance threshold (≥ 70%), nominates candidates.
   - **Level 2 (Ye Sew Habt Kfl)**: Reviews nominated candidate rosters and endorses for advancement.
   - **Level 3 (Super Admin)**: Executes official promotion, advancing students into their target sections or graduating them.

3. **Mezmur & Hymn Ministry**:
   - Tracks hymns by category, tier, and liturgical season.
   - Mezmur student tracking (`is_mezmur`) and hymn examination records.

4. **Streaming & O(1) Memory Exports**:
   - CSV reports (`/api/reports/export/{type}`) stream directly to the client using Eloquent `cursor()` and include UTF-8 BOM headers for seamless display of Ethiopian/Amharic script in Microsoft Excel.

5. **Token Security & Rate Limiting**:
   - Short-lived Bearer access tokens paired with rotatable refresh tokens (`/api/refresh`).
   - Rate limiting on sensitive endpoints (`/api/login`, `/api/forgot-password`).
   - Realpath containment protecting media downloads against path traversal attacks.

---

## ⚙️ Installation & Setup

### Requirements
- **PHP**: 8.2 or higher (with `pdo`, `mbstring`, `openssl`, `fileinfo`, `sqlite3` or `mysql` extensions)
- **Composer**: 2.5 or higher
- **Database**: SQLite (default) or MySQL 8.0+

### Setup Instructions

1. **Install Dependencies**:
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

2. **Environment Configuration**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure Database & CORS in `.env`**:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.yourchurchdomain.org

   FRONTEND_URL=https://app.yourchurchdomain.org
   ALLOWED_ORIGINS=https://app.yourchurchdomain.org,http://localhost:5173

   DB_CONNECTION=sqlite
   # Or for MySQL:
   # DB_CONNECTION=mysql
   # DB_HOST=127.0.0.1
   # DB_PORT=3306
   # DB_DATABASE=church_mgt
   # DB_USERNAME=church_user
   # DB_PASSWORD=secret_password
   ```

4. **Run Migrations**:
   ```bash
   php artisan migrate --force
   ```

5. **Storage Symlink**:
   ```bash
   php artisan storage:link
   ```

6. **System Initialization**:
   - Navigate to `/api/system/status` or visit the frontend setup wizard at `/setup`.
   - The initial Super Admin account is provisioned once through `/api/system/initialize`.

---

## 🔒 Security Best Practices for Production

- **Credentials & Passwords**: Passwords and security answers are hashed (`bcrypt`) and hidden from serialization (`$hidden = ['password', 'remember_token', 'security_answer']`).
- **File Uploads**: Certificate and photo uploads strictly enforce MIME restrictions (`jpg,jpeg,png,pdf`) and maximum file size limits (5 MB).
- **CORS Protection**: Origin checks are dynamically loaded from `FRONTEND_URL` and `ALLOWED_ORIGINS`.

---

## 📜 License
Private & Proprietary. All rights reserved by the Sunday School Administration.
