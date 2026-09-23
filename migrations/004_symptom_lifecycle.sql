-- 004_symptom_lifecycle.sql
-- Wellness Insights real-time lifecycle: symptoms extracted from chat can now
-- be RESOLVED when the client says they feel better ("my fatigue is gone").
-- Rows are never deleted — resolution is a status change, preserving the full
-- clinical trail (which message reported it, which message resolved it).

ALTER TABLE ai_extracted_symptoms
  ADD COLUMN status ENUM('active','resolved') NOT NULL DEFAULT 'active',
  ADD COLUMN resolved_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN resolved_message_id INT NULL DEFAULT NULL;

ALTER TABLE ai_extracted_symptoms
  ADD INDEX idx_user_status (user_id, status);
