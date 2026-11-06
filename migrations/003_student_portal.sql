-- Adds student accounts without linking or publishing any existing academic record.
ALTER TABLE users
    MODIFY role ENUM('Administrator','Faculty','Student') NOT NULL DEFAULT 'Faculty',
    ADD student_id BIGINT UNSIGNED NULL,
    ADD UNIQUE KEY uq_users_student (student_id),
    ADD CONSTRAINT fk_users_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL;

ALTER TABLE notifications
    ADD audience ENUM('staff','student') NOT NULL DEFAULT 'staff',
    ADD student_id BIGINT UNSIGNED NULL,
    ADD grade_id BIGINT UNSIGNED NULL,
    ADD KEY idx_notifications_student (user_id,audience,student_id,grade_id);

INSERT INTO schema_migrations(version) VALUES ('003_student_portal');
