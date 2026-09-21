-- 003_nlp_eval_audit.sql
-- NLP evaluation persistence + per-symptom mapping provenance.
--
-- 1. ai_extracted_symptoms gains audit columns recording WHICH mapping table
--    version and class produced each nutrient assignment, so historical
--    extractions remain interpretable when the mapping evolves.
-- 2. nlp_eval_runs stores one row per gold-set harness run (mode, mapping
--    version, prompt hash, per-class metrics) so the ISO 25010 accuracy
--    analysis can query results across runs and mapping versions.

ALTER TABLE ai_extracted_symptoms
    ADD COLUMN mapping_version VARCHAR(20) NULL AFTER mapped_nutrient,
    ADD COLUMN mapping_class_id VARCHAR(40) NULL AFTER mapping_version;

CREATE TABLE IF NOT EXISTS nlp_eval_runs (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_uid            VARCHAR(32)  NOT NULL,
    harness_version    VARCHAR(20)  NOT NULL,
    corpus_version     VARCHAR(20)  NOT NULL,
    mapping_version    VARCHAR(20)  NOT NULL,
    mode               VARCHAR(10)  NOT NULL,             -- 'mock' | 'live'
    model              VARCHAR(60)  NULL,                 -- null in mock mode
    prompt_hash        CHAR(64)     NOT NULL,             -- sha256 of the extraction prompt (probe text)
    total_cases        INT          NOT NULL,
    evaluated          INT          NOT NULL,
    skipped            INT          NOT NULL DEFAULT 0,
    micro_precision    DECIMAL(6,4) NULL,
    micro_recall       DECIMAL(6,4) NULL,
    micro_f1           DECIMAL(6,4) NULL,
    macro_precision    DECIMAL(6,4) NULL,
    macro_recall       DECIMAL(6,4) NULL,
    macro_f1           DECIMAL(6,4) NULL,
    subset_accuracy    DECIMAL(6,4) NULL,
    taglish_exact_rate DECIMAL(6,4) NULL,
    taglish_micro_f1   DECIMAL(6,4) NULL,
    negative_fpr       DECIMAL(6,4) NULL,
    target_f1          DECIMAL(6,4) NOT NULL DEFAULT 0.8000,
    passed             TINYINT(1)   NOT NULL DEFAULT 0,
    per_class_json     MEDIUMTEXT   NULL,                 -- JSON: per-class tp/fp/fn/support/P/R/F1
    report_json        LONGTEXT     NULL,                 -- full machine-readable report
    notes              VARCHAR(255) NULL,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_run_uid (run_uid),
    KEY idx_mapping_version (mapping_version),
    KEY idx_mode_created (mode, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
