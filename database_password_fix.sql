-- Password hash columns must be long enough for PASSWORD_DEFAULT (bcrypt is 60 chars; future algorithms may be longer).
-- Run once in phpMyAdmin against wt_database.

ALTER TABLE patient MODIFY Password VARCHAR(255) NOT NULL;
ALTER TABLE doctor MODIFY Password VARCHAR(255) NOT NULL;
ALTER TABLE manager MODIFY Password VARCHAR(255) NOT NULL;

-- IMPORTANT:
-- Existing patient/doctor/manager passwords that were previously truncated cannot be recovered.
-- After running the ALTER statements, create/reset those accounts so a complete password hash is stored.
