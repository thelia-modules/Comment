
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- comment
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `comment`;

CREATE TABLE `comment`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(255),
    `customer_id` INTEGER,
    `ref` VARCHAR(255),
    `ref_id` INTEGER,
    `email` VARCHAR(255),
    `title` VARCHAR(255),
    `content` LONGTEXT,
    `rating` TINYINT,
    `status` TINYINT DEFAULT 0,
    `verified` TINYINT,
    `abuse` INTEGER,
    `locale` VARCHAR(10),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_comment_user_id` (`customer_id`),
    INDEX `idx_comment_ref` (`ref`, `ref_id`),
    INDEX `idx_comment_status` (`status`),
    CONSTRAINT `fk_comment_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
