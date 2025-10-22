-- Create staging database and user
CREATE DATABASE mycourses_staging;
CREATE USER mycourses_staging WITH ENCRYPTED PASSWORD 'staging_secure_password_2024';
GRANT ALL PRIVILEGES ON DATABASE mycourses_staging TO mycourses_staging;

-- Connect to staging database
\c mycourses_staging;

-- Grant schema permissions
GRANT ALL ON SCHEMA public TO mycourses_staging;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO mycourses_staging;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO mycourses_staging;

-- Create extensions if needed
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";