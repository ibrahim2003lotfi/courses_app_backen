-- Production Database Setup
-- Run as PostgreSQL superuser

-- Create production database and user
CREATE DATABASE mycourses_production;
CREATE USER mycourses_prod WITH ENCRYPTED PASSWORD 'STRONG_PRODUCTION_PASSWORD_2024';

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE mycourses_production TO mycourses_prod;

-- Connect to production database
\c mycourses_production;

-- Grant schema permissions
GRANT ALL ON SCHEMA public TO mycourses_prod;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO mycourses_prod;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO mycourses_prod;

-- Create extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- Create backup user (read-only)
CREATE USER mycourses_backup WITH ENCRYPTED PASSWORD 'BACKUP_USER_PASSWORD_2024';
GRANT CONNECT ON DATABASE mycourses_production TO mycourses_backup;
GRANT USAGE ON SCHEMA public TO mycourses_backup;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO mycourses_backup;

-- Performance optimizations
ALTER DATABASE mycourses_production SET shared_preload_libraries = 'pg_stat_statements';
ALTER DATABASE mycourses_production SET log_statement = 'mod';
ALTER DATABASE mycourses_production SET log_min_duration_statement = 1000;

-- Create indexes for production performance
-- (These will be added during migration, but documenting here)
-- CREATE INDEX CONCURRENTLY idx_courses_search ON courses USING gin(to_tsvector('english', title || ' ' || description));
-- CREATE INDEX CONCURRENTLY idx_users_email_active ON users(email) WHERE deleted_at IS NULL;
-- CREATE INDEX CONCURRENTLY idx_orders_user_status ON orders(user_id, status, created_at);