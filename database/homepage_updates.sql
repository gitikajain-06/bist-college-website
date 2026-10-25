
USE bist_college;

-- Add urgent-notice controls to the existing notices table.
ALTER TABLE notices
    ADD COLUMN is_urgent TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN expires_at DATETIME NULL,
    ADD COLUMN display_order INT NOT NULL DEFAULT 0;

-- President's and Principal's approved messages.
CREATE TABLE site_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('president', 'principal') NOT NULL,
    name VARCHAR(150) NOT NULL,
    designation VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    photo_path VARCHAR(500) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_leadership_role (role)
);

-- Homepage events.
CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT,
    event_date DATETIME NOT NULL,
    location VARCHAR(200),
    image_path VARCHAR(500),
    status ENUM('draft', 'published', 'archived')
        NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_events_date_status (event_date, status)
);

-- Approved gallery photographs.
CREATE TABLE gallery_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    alt_text VARCHAR(255) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gallery_published (is_published, display_order)
);
