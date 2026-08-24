-- Appointment slot upgrade
-- Run once on an existing installation.
-- 30-minute slots are generated from doctor_availability.starttime/endtime.

ALTER TABLE book
  ADD COLUMN appointment_time TIME NULL AFTER DOV,
  ADD COLUMN appointment_id BIGINT UNSIGNED NULL AUTO_INCREMENT UNIQUE FIRST;

ALTER TABLE book
  MODIFY COLUMN appointment_time TIME NOT NULL;

ALTER TABLE book ENGINE=InnoDB;
ALTER TABLE doctor_availability ENGINE=InnoDB;
ALTER TABLE clinic ENGINE=InnoDB;
ALTER TABLE doctor ENGINE=InnoDB;
ALTER TABLE patient ENGINE=InnoDB;

CREATE INDEX idx_book_doctor_date_time ON book (DID, CID, DOV, appointment_time);
CREATE INDEX idx_book_patient_date ON book (Username, DOV);
