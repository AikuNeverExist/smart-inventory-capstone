USE smart_inventory_db;

-- 1) Allow all four application roles.
ALTER TABLE users
MODIFY COLUMN role ENUM('admin','staff','user','supplier') NOT NULL DEFAULT 'user';

-- 2) Add a link from supplier profile to a real user account if it does not exist yet.
SET @has_user_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND COLUMN_NAME = 'user_id'
);

SET @sql := IF(
    @has_user_id = 0,
    'ALTER TABLE suppliers ADD COLUMN user_id INT NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Keep one supplier profile per linked account.
SET @has_unique := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND INDEX_NAME = 'uq_suppliers_user_id'
);

SET @sql := IF(
    @has_unique = 0,
    'ALTER TABLE suppliers ADD UNIQUE KEY uq_suppliers_user_id (user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) Optional foreign key. Add it only when it is not already present.
SET @has_fk := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND CONSTRAINT_NAME = 'fk_suppliers_user'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql := IF(
    @has_fk = 0,
    'ALTER TABLE suppliers ADD CONSTRAINT fk_suppliers_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT id, name, username, role, created_at
FROM users
ORDER BY id;
