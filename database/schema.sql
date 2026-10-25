
CREATE DATABASE IF NOT EXISTS bist_college
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bist_college;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM(
        'super_admin',
        'administrator',
        'editor',
        'publisher'
    ) NOT NULL DEFAULT 'editor',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE programmes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    description TEXT,
    duration VARCHAR(80),
    eligibility TEXT,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE notices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    category VARCHAR(80) NOT NULL DEFAULT 'General',
    summary TEXT,
    document_path VARCHAR(500),
    published_at DATETIME,
    status ENUM(
        'draft',
        'pending',
        'published',
        'archived'
    ) NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_notice_status_date (status, published_at)
);

CREATE TABLE subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    programme_id BIGINT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    subject_code VARCHAR(50),
    subject_name VARCHAR(180) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (programme_id) REFERENCES programmes(id)
        ON DELETE CASCADE,
    INDEX idx_subject_course_semester
        (programme_id, semester)
);

CREATE TABLE academic_resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id BIGINT UNSIGNED,
    title VARCHAR(255) NOT NULL,
    resource_type ENUM(
        'syllabus',
        'pyq',
        'book_reference',
        'notes',
        'practical',
        'assignment',
        'other'
    ) NOT NULL DEFAULT 'other',
    academic_year VARCHAR(20),
    description TEXT,
    file_path VARCHAR(500),
    external_url VARCHAR(1000),
    publication_date DATE,
    status ENUM(
        'draft',
        'pending',
        'published',
        'archived'
    ) NOT NULL DEFAULT 'draft',
    uploaded_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
        ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_resource_type_status (resource_type, status)
);

CREATE TABLE exam_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    programme_id BIGINT UNSIGNED,
    subject_id BIGINT UNSIGNED,
    title VARCHAR(255) NOT NULL,
    exam_type VARCHAR(80) NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    venue VARCHAR(255),
    official_source VARCHAR(1000),
    routine_file_path VARCHAR(500),
    status ENUM(
        'scheduled',
        'postponed',
        'cancelled',
        'completed'
    ) NOT NULL DEFAULT 'scheduled',
    published_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (programme_id) REFERENCES programmes(id)
        ON DELETE SET NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
        ON DELETE SET NULL,
    INDEX idx_exam_date_status (exam_date, status)
);
