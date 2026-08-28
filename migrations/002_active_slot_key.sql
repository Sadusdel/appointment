-- Active appointment slot locking
-- Run after 001_appointment_slots.sql.
-- active_slot_key is a NORMAL nullable column, not a generated column.
-- NULL means the appointment no longer occupies the slot (cancelled/completed).

ALTER TABLE book
  ADD COLUMN active_slot_key VARCHAR(100)
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci
    NULL;

CREATE UNIQUE INDEX unique_active_slot ON book (active_slot_key);
