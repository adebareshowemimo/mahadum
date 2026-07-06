-- Run once as MySQL root: mysql -u root -p < deploy/mysql-setup.sql
-- Change the password before running.
CREATE DATABASE IF NOT EXISTS mahadum_staging
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'mahadum_staging'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON mahadum_staging.* TO 'mahadum_staging'@'localhost';
FLUSH PRIVILEGES;
