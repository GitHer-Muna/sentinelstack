-- Wellspring schema.
-- Run against a fresh SQLite database. Foreign keys are enabled per-connection.

PRAGMA foreign_keys = ON;

-- ============================================================
-- users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    email           TEXT NOT NULL UNIQUE COLLATE NOCASE,
    display_name    TEXT NOT NULL,
    password_hash   TEXT NOT NULL,
    timezone        TEXT NOT NULL DEFAULT 'UTC',
    theme           TEXT NOT NULL DEFAULT 'light' CHECK(theme IN ('light','dark')),
    water_goal      INTEGER NOT NULL DEFAULT 2000,
    water_unit      TEXT NOT NULL DEFAULT 'ml' CHECK(water_unit IN ('ml','oz')),
    created_at      TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    updated_at      TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email COLLATE NOCASE);

-- ============================================================
-- login_attempts  (rate limiting — keyed by email + ip)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    email       TEXT NOT NULL COLLATE NOCASE,
    ip          TEXT NOT NULL,
    succeeded   INTEGER NOT NULL DEFAULT 0,
    attempted_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_email_time ON login_attempts(email, attempted_at);

-- ============================================================
-- register_attempts  (rate limiting — keyed by ip only, since
-- /register is the free oracle for email enumeration otherwise)
-- ============================================================
CREATE TABLE IF NOT EXISTS register_attempts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    ip          TEXT NOT NULL,
    succeeded   INTEGER NOT NULL DEFAULT 0,
    attempted_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE INDEX IF NOT EXISTS idx_register_attempts_ip_time ON register_attempts(ip, attempted_at);

-- ============================================================
-- water_logs
-- stored in the unit the user has set (ml or oz). Goal + unit live on users.
-- ============================================================
CREATE TABLE IF NOT EXISTS water_logs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    amount      INTEGER NOT NULL CHECK(amount > 0),
    unit        TEXT NOT NULL CHECK(unit IN ('ml','oz')),
    logged_at   TEXT NOT NULL,                       -- ISO 8601 UTC
    local_date  TEXT NOT NULL,                       -- YYYY-MM-DD computed at insert from user TZ
    note        TEXT,
    created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_water_logs_user_date ON water_logs(user_id, local_date);
CREATE INDEX IF NOT EXISTS idx_water_logs_user_time ON water_logs(user_id, logged_at);

-- ============================================================
-- todos (intentions & recurring habits)
-- type: 'task' (one-off) or 'habit' (recurring)
-- recurrence_period: 'daily' or 'weekly' (NULL for one-off tasks)
-- ============================================================
CREATE TABLE IF NOT EXISTS todos (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id           INTEGER NOT NULL,
    title             TEXT NOT NULL,
    note              TEXT,
    priority          TEXT NOT NULL DEFAULT 'med' CHECK(priority IN ('low','med','high')),
    type              TEXT NOT NULL DEFAULT 'task' CHECK(type IN ('task','habit')),
    due_date          TEXT,                          -- YYYY-MM-DD, nullable for habits
    recurrence_period TEXT CHECK(recurrence_period IN ('daily','weekly')),
    completed_at      TEXT,                          -- per-occurrence completion timestamp
    completed_log     TEXT,                          -- YYYY-MM-DD, the day this occurrence was checked off
    sort_order        INTEGER NOT NULL DEFAULT 0,
    created_at        TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    updated_at        TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_todos_user_date ON todos(user_id, due_date);
CREATE INDEX IF NOT EXISTS idx_todos_user_type ON todos(user_id, type);

-- ============================================================
-- habit_completions
-- Per-occurrence log of habit check-offs. The `todos` row stores
-- the canonical habit definition (recurrence_period, due_date as
-- next-pending date for weekly habits). Each completion is a row
-- in this table, so we have real history for streak math.
-- ============================================================
CREATE TABLE IF NOT EXISTS habit_completions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    habit_id    INTEGER NOT NULL,
    local_date  TEXT NOT NULL,
    completed_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    UNIQUE(habit_id, local_date),
    FOREIGN KEY(habit_id) REFERENCES todos(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id)  REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_habit_completions_user_date ON habit_completions(user_id, local_date);
CREATE INDEX IF NOT EXISTS idx_habit_completions_habit_date ON habit_completions(habit_id, local_date);

-- ============================================================
-- mindfulness_sessions
-- ============================================================
CREATE TABLE IF NOT EXISTS mindfulness_sessions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    duration_seconds INTEGER NOT NULL CHECK(duration_seconds > 0),
    pattern     TEXT NOT NULL CHECK(pattern IN ('box','4-7-8','equal')),
    completed   INTEGER NOT NULL DEFAULT 1,         -- 0 = cancelled, 1 = completed
    local_date  TEXT NOT NULL,                      -- YYYY-MM-DD computed from user TZ
    started_at  TEXT NOT NULL,
    ended_at    TEXT,
    note        TEXT,
    created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_mindfulness_user_date ON mindfulness_sessions(user_id, local_date);

-- ============================================================
-- mood_entries (one per user per local_date)
-- ============================================================
CREATE TABLE IF NOT EXISTS mood_entries (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    mood        TEXT NOT NULL CHECK(mood IN ('great','good','okay','low','rough')),
    gratitude   TEXT,
    note        TEXT,
    local_date  TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    updated_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    UNIQUE(user_id, local_date),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_mood_entries_user_date ON mood_entries(user_id, local_date);

-- ============================================================
-- movement_logs
-- routines defined in code; this table only tracks completions.
-- ============================================================
CREATE TABLE IF NOT EXISTS movement_logs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    routine_key TEXT NOT NULL,                      -- matches key from routines config
    local_date  TEXT NOT NULL,
    duration_seconds INTEGER,
    note        TEXT,
    created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    UNIQUE(user_id, routine_key, local_date),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_movement_user_date ON movement_logs(user_id, local_date);

-- ============================================================
-- sleep_logs
-- One row per (user_id, local_date). local_date is the wake-up
-- date of the night of sleep. UPSERT keeps today's number editable
-- without losing history.
-- ============================================================
CREATE TABLE IF NOT EXISTS sleep_logs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL,
    duration_minutes INTEGER NOT NULL CHECK(duration_minutes > 0 AND duration_minutes <= 1440),
    local_date      TEXT NOT NULL,
    note            TEXT,
    created_at      TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    updated_at      TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    UNIQUE(user_id, local_date),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sleep_logs_user_date ON sleep_logs(user_id, local_date);

-- ============================================================
-- affirmations
-- Deterministic per day + per user; seeded at install.
-- ============================================================
CREATE TABLE IF NOT EXISTS affirmations (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    body        TEXT NOT NULL UNIQUE,
    created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
