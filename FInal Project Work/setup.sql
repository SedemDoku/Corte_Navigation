-- Create the Database
CREATE DATABASE IF NOT EXISTS corte_db;
USE corte_db;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Password reset fields
    reset_token_hash VARCHAR(64) NULL DEFAULT NULL,
    reset_token_expires_at DATETIME NULL DEFAULT NULL,
    
    -- Remember me fields
    remember_token VARCHAR(255) NULL DEFAULT NULL,
    remember_token_expires DATETIME NULL DEFAULT NULL
);

-- 2. Saved Routes Table (For future "Save" feature)
CREATE TABLE IF NOT EXISTS saved_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    route_name VARCHAR(100) NOT NULL,
    start_lat DOUBLE,
    start_lon DOUBLE,
    end_lat DOUBLE,
    end_lon DOUBLE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE                                 
        ON UPDATE CASCADE
);

-- Add indexes for better performance
CREATE INDEX idx_reset_token ON users(reset_token_hash);
CREATE INDEX idx_remember_token ON users(remember_token);
CREATE INDEX idx_user_id ON saved_routes(user_id);