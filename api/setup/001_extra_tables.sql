-- Campus Coin API additions
-- Run this ONCE after importing the main campus_coin_db.sql file.

USE campus_coin_db;

ALTER TABLE transactions
    ADD COLUMN recurring_parent_id INT UNSIGNED DEFAULT NULL AFTER is_recurring,
    ADD COLUMN next_recurring_date DATE DEFAULT NULL AFTER recurring_frequency,
    ADD INDEX idx_recurring_due (user_id, is_recurring, next_recurring_date),
    ADD INDEX idx_recurring_parent (recurring_parent_id),
    ADD CONSTRAINT fk_transactions_recurring_parent
        FOREIGN KEY (recurring_parent_id) REFERENCES transactions(id) ON DELETE SET NULL;

CREATE TABLE content_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('tip_template') NOT NULL DEFAULT 'tip_template',
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_content_items_admin
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_content_type_active (content_type, is_active)
) ENGINE=InnoDB;
