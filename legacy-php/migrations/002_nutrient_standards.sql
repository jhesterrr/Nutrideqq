-- 002_nutrient_standards.sql
-- Nutrient standards system: per-demographic (age bracket x sex) daily reference
-- values + tolerable upper limits for the 6 tracked nutrients.
--
-- Source: FNRI-DOST "Philippine Dietary Reference Intakes 2015: Summary Tables"
-- (Revised September 2018). https://fnri.dost.gov.ph (PDRI tool page).
-- Value types:
--   RNI = Recommended Nutrient Intake (iron, calcium, vitamin C)
--   AI  = Adequate Intake (potassium, sodium, dietary fiber)
--   UL  = Tolerable Upper Intake Level (from the PDRI UL table; WHO/FAO + IOM-FNB)
-- Notes carried from the source tables:
--   * Iron: FNRI states female 10+ RNI cannot be met by usual diet alone
--     (iron-rich/fortified foods or supplements recommended) -> source_note.
--   * Potassium/Sodium: PDRI AI is 2000/500 mg for age >= 10 years. WHO additional
--     recommendations (WHO 2012 guidelines, referenced by the PDRI "Additional
--     Recommendations" table): potassium 3510 mg/day, sodium < 2000 mg/day for
--     adults (children scaled by energy). Stored as WHO_* targets.
--   * Fiber: PDRI gives an AI range (e.g. 20-25 g for adults); ai_value stores the
--     lower bound, ai_value_upper the upper bound; the working target used for
--     percent-of-daily is the upper bound.

CREATE TABLE IF NOT EXISTS nutrient_standards (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    age_min        INT UNSIGNED NOT NULL,           -- inclusive lower age (years)
    age_max        INT UNSIGNED NOT NULL,           -- inclusive upper age (years; 200 = open-ended >= 70)
    sex            ENUM('male','female') NOT NULL,  -- PDRI values are sex-specific
    nutrient_name  VARCHAR(100) NOT NULL,           -- matches fct_nutrients.nutrient_name
    unit           VARCHAR(20)  NOT NULL,
    rni_value      DECIMAL(10,2) NULL,              -- Recommended Nutrient Intake (mg or g)
    ai_value       DECIMAL(10,2) NULL,              -- Adequate Intake (mg or g)
    ai_value_upper DECIMAL(10,2) NULL,              -- upper bound when PDRI gives an AI range (fiber)
    ul_value       DECIMAL(10,2) NULL,              -- Tolerable Upper Intake Level (NULL = none established)
    source_type    ENUM('RNI','AI') NOT NULL DEFAULT 'RNI',
    source_note    VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bracket (age_min, age_max, sex, nutrient_name),
    KEY idx_lookup (nutrient_name, sex, age_min, age_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- PDRI 2015 values. Life stages (years): 1-2, 3-5, 6-9, 10-12, 13-15, 16-18,
-- 19-29, 30-49, 50-59, 60-69, >=70. Infants (0-11 mo) omitted: the app's
-- youngest recorded client is 5, and the FCT/AI model targets ages 1+.
-- Units: mg for minerals/vitamins, g for fiber (per PDRI table units).
-- ─────────────────────────────────────────────────────────────────────────────

-- ═══ IRON (RNI, mg) — F 10+ cannot be met by usual diet alone (FNRI note) ═══
INSERT IGNORE INTO nutrient_standards
  (age_min, age_max, sex, nutrient_name, unit, rni_value, source_type, source_note) VALUES
  (1,  2,  'male',  'Iron', 'mg',  8.0,  'RNI', NULL),
  (1,  2,  'female','Iron', 'mg',  8.0,  'RNI', NULL),
  (3,  5,  'male',  'Iron', 'mg',  9.0,  'RNI', NULL),
  (3,  5,  'female','Iron', 'mg',  9.0,  'RNI', NULL),
  (6,  9,  'male',  'Iron', 'mg', 10.0,  'RNI', NULL),
  (6,  9,  'female','Iron', 'mg',  9.0,  'RNI', NULL),
  (10, 12, 'male',  'Iron', 'mg', 12.0,  'RNI', NULL),
  (10, 12, 'female','Iron', 'mg', 20.0,  'RNI', 'FNRI: cannot be met by usual diet alone'),
  (13, 15, 'male',  'Iron', 'mg', 19.0,  'RNI', NULL),
  (13, 15, 'female','Iron', 'mg', 28.0,  'RNI', 'FNRI: cannot be met by usual diet alone'),
  (16, 18, 'male',  'Iron', 'mg', 14.0,  'RNI', NULL),
  (16, 18, 'female','Iron', 'mg', 28.0,  'RNI', 'FNRI: cannot be met by usual diet alone'),
  (19, 29, 'male',  'Iron', 'mg', 12.0,  'RNI', NULL),
  (19, 29, 'female','Iron', 'mg', 28.0,  'RNI', 'FNRI: cannot be met by usual diet alone'),
  (30, 49, 'male',  'Iron', 'mg', 12.0,  'RNI', NULL),
  (30, 49, 'female','Iron', 'mg', 28.0,  'RNI', 'FNRI: cannot be met by usual diet alone'),
  (50, 59, 'male',  'Iron', 'mg', 12.0,  'RNI', NULL),
  (50, 59, 'female','Iron', 'mg', 10.0,  'RNI', NULL),
  (60, 69, 'male',  'Iron', 'mg', 12.0,  'RNI', NULL),
  (60, 69, 'female','Iron', 'mg', 10.0,  'RNI', NULL),
  (70, 200,'male',  'Iron', 'mg', 12.0,  'RNI', NULL),
  (70, 200,'female','Iron', 'mg', 10.0,  'RNI', NULL);

-- ═══ CALCIUM (RNI, mg) ═══
INSERT IGNORE INTO nutrient_standards
  (age_min, age_max, sex, nutrient_name, unit, rni_value, source_type, source_note) VALUES
  (1,  2,  'male',  'Calcium', 'mg', 500.0, 'RNI', NULL),
  (1,  2,  'female','Calcium', 'mg', 500.0, 'RNI', NULL),
  (3,  5,  'male',  'Calcium', 'mg', 550.0, 'RNI', NULL),
  (3,  5,  'female','Calcium', 'mg', 550.0, 'RNI', NULL),
  (6,  9,  'male',  'Calcium', 'mg', 700.0, 'RNI', NULL),
  (6,  9,  'female','Calcium', 'mg', 700.0, 'RNI', NULL),
  (10, 12, 'male',  'Calcium', 'mg', 1000.0,'RNI', NULL),
  (10, 12, 'female','Calcium', 'mg', 1000.0,'RNI', NULL),
  (13, 15, 'male',  'Calcium', 'mg', 1000.0,'RNI', NULL),
  (13, 15, 'female','Calcium', 'mg', 1000.0,'RNI', NULL),
  (16, 18, 'male',  'Calcium', 'mg', 1000.0,'RNI', NULL),
  (16, 18, 'female','Calcium', 'mg', 1000.0,'RNI', NULL),
  (19, 29, 'male',  'Calcium', 'mg', 750.0, 'RNI', NULL),
  (19, 29, 'female','Calcium', 'mg', 750.0, 'RNI', NULL),
  (30, 49, 'male',  'Calcium', 'mg', 750.0, 'RNI', NULL),
  (30, 49, 'female','Calcium', 'mg', 750.0, 'RNI', NULL),
  (50, 59, 'male',  'Calcium', 'mg', 750.0, 'RNI', NULL),
  (50, 59, 'female','Calcium', 'mg', 800.0, 'RNI', NULL),
  (60, 69, 'male',  'Calcium', 'mg', 800.0, 'RNI', NULL),
  (60, 69, 'female','Calcium', 'mg', 800.0, 'RNI', NULL),
  (70, 200,'male',  'Calcium', 'mg', 800.0, 'RNI', NULL),
  (70, 200,'female','Calcium', 'mg', 800.0, 'RNI', NULL);

-- ═══ POTASSIUM (AI, mg) — PDRI AI 2000 for age >= 10; WHO target 3510 ═══
INSERT IGNORE INTO nutrient_standards
  (age_min, age_max, sex, nutrient_name, unit, ai_value, source_type, source_note) VALUES
  (1,  2,  'male',  'Potassium, K', 'mg', 1000.0, 'AI', NULL),
  (1,  2,  'female','Potassium, K', 'mg', 1000.0, 'AI', NULL),
  (3,  5,  'male',  'Potassium, K', 'mg', 1400.0, 'AI', NULL),
  (3,  5,  'female','Potassium, K', 'mg', 1400.0, 'AI', NULL),
  (6,  9,  'male',  'Potassium, K', 'mg', 1600.0, 'AI', NULL),
  (6,  9,  'female','Potassium, K', 'mg', 1600.0, 'AI', NULL),
  (10, 12, 'male',  'Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg (scaled by energy for children)'),
  (10, 12, 'female','Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg (scaled by energy for children)'),
  (13, 15, 'male',  'Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg (scaled by energy for children)'),
  (13, 15, 'female','Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg (scaled by energy for children)'),
  (16, 18, 'male',  'Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg (scaled by energy for children)'),
  (16, 18, 'female','Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg (scaled by energy for children)'),
  (19, 29, 'male',  'Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day'),
  (19, 29, 'female','Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day'),
  (30, 49, 'male',  'Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day'),
  (30, 49, 'female','Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day'),
  (50, 59, 'male',  'Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day'),
  (50, 59, 'female','Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day'),
  (60, 69, 'male',  'Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day'),
  (60, 69, 'female','Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day'),
  (70, 200,'male',  'Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day'),
  (70, 200,'female','Potassium, K', 'mg', 2000.0, 'AI', 'WHO additional target 3510 mg/day');

-- ═══ SODIUM (AI, mg) — PDRI AI 500 for age >= 10; WHO limit < 2000 ═══
INSERT IGNORE INTO nutrient_standards
  (age_min, age_max, sex, nutrient_name, unit, ai_value, source_type, source_note) VALUES
  (1,  2,  'male',  'Sodium', 'mg', 225.0, 'AI', 'WHO limit scaled by energy for children'),
  (1,  2,  'female','Sodium', 'mg', 225.0, 'AI', 'WHO limit scaled by energy for children'),
  (3,  5,  'male',  'Sodium', 'mg', 300.0, 'AI', 'WHO limit scaled by energy for children'),
  (3,  5,  'female','Sodium', 'mg', 300.0, 'AI', 'WHO limit scaled by energy for children'),
  (6,  9,  'male',  'Sodium', 'mg', 400.0, 'AI', 'WHO limit scaled by energy for children'),
  (6,  9,  'female','Sodium', 'mg', 400.0, 'AI', 'WHO limit scaled by energy for children'),
  (10, 12, 'male',  'Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (10, 12, 'female','Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (13, 15, 'male',  'Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (13, 15, 'female','Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (16, 18, 'male',  'Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (16, 18, 'female','Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (19, 29, 'male',  'Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (19, 29, 'female','Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (30, 49, 'male',  'Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (30, 49, 'female','Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (50, 59, 'male',  'Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (50, 59, 'female','Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (60, 69, 'male',  'Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (60, 69, 'female','Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (70, 200,'male',  'Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day'),
  (70, 200,'female','Sodium', 'mg', 500.0, 'AI', 'WHO limit < 2000 mg/day');

-- ═══ VITAMIN C (RNI, mg) — M 70 / F 60 from age 13 ═══
INSERT IGNORE INTO nutrient_standards
  (age_min, age_max, sex, nutrient_name, unit, rni_value, source_type, source_note) VALUES
  (1,  2,  'male',  'Vitamin C', 'mg', 45.0, 'RNI', NULL),
  (1,  2,  'female','Vitamin C', 'mg', 45.0, 'RNI', NULL),
  (3,  5,  'male',  'Vitamin C', 'mg', 45.0, 'RNI', NULL),
  (3,  5,  'female','Vitamin C', 'mg', 45.0, 'RNI', NULL),
  (6,  9,  'male',  'Vitamin C', 'mg', 45.0, 'RNI', NULL),
  (6,  9,  'female','Vitamin C', 'mg', 45.0, 'RNI', NULL),
  (10, 12, 'male',  'Vitamin C', 'mg', 45.0, 'RNI', NULL),
  (10, 12, 'female','Vitamin C', 'mg', 45.0, 'RNI', NULL),
  (13, 15, 'male',  'Vitamin C', 'mg', 60.0, 'RNI', NULL),
  (13, 15, 'female','Vitamin C', 'mg', 55.0, 'RNI', NULL),
  (16, 18, 'male',  'Vitamin C', 'mg', 70.0, 'RNI', NULL),
  (16, 18, 'female','Vitamin C', 'mg', 60.0, 'RNI', NULL),
  (19, 29, 'male',  'Vitamin C', 'mg', 70.0, 'RNI', NULL),
  (19, 29, 'female','Vitamin C', 'mg', 60.0, 'RNI', NULL),
  (30, 49, 'male',  'Vitamin C', 'mg', 70.0, 'RNI', NULL),
  (30, 49, 'female','Vitamin C', 'mg', 60.0, 'RNI', NULL),
  (50, 59, 'male',  'Vitamin C', 'mg', 70.0, 'RNI', NULL),
  (50, 59, 'female','Vitamin C', 'mg', 60.0, 'RNI', NULL),
  (60, 69, 'male',  'Vitamin C', 'mg', 70.0, 'RNI', NULL),
  (60, 69, 'female','Vitamin C', 'mg', 60.0, 'RNI', NULL),
  (70, 200,'male',  'Vitamin C', 'mg', 70.0, 'RNI', NULL),
  (70, 200,'female','Vitamin C', 'mg', 60.0, 'RNI', NULL);

-- ═══ FIBER (AI range, g) — PDRI macronutrient table ═══
INSERT IGNORE INTO nutrient_standards
  (age_min, age_max, sex, nutrient_name, unit, ai_value, ai_value_upper, source_type, source_note) VALUES
  (1,  2,  'male',  'Fiber, total dietary', 'g', 6.0,  7.0,  'AI', NULL),
  (1,  2,  'female','Fiber, total dietary', 'g', 6.0,  7.0,  'AI', NULL),
  (3,  5,  'male',  'Fiber, total dietary', 'g', 8.0,  10.0, 'AI', NULL),
  (3,  5,  'female','Fiber, total dietary', 'g', 8.0,  10.0, 'AI', NULL),
  (6,  9,  'male',  'Fiber, total dietary', 'g', 11.0, 14.0, 'AI', NULL),
  (6,  9,  'female','Fiber, total dietary', 'g', 11.0, 14.0, 'AI', NULL),
  (10, 12, 'male',  'Fiber, total dietary', 'g', 15.0, 17.0, 'AI', NULL),
  (10, 12, 'female','Fiber, total dietary', 'g', 15.0, 17.0, 'AI', NULL),
  (13, 15, 'male',  'Fiber, total dietary', 'g', 18.0, 20.0, 'AI', NULL),
  (13, 15, 'female','Fiber, total dietary', 'g', 18.0, 20.0, 'AI', NULL),
  (16, 18, 'male',  'Fiber, total dietary', 'g', 21.0, 23.0, 'AI', NULL),
  (16, 18, 'female','Fiber, total dietary', 'g', 21.0, 23.0, 'AI', NULL),
  (19, 29, 'male',  'Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL),
  (19, 29, 'female','Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL),
  (30, 49, 'male',  'Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL),
  (30, 49, 'female','Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL),
  (50, 59, 'male',  'Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL),
  (50, 59, 'female','Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL),
  (60, 69, 'male',  'Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL),
  (60, 69, 'female','Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL),
  (70, 200,'male',  'Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL),
  (70, 200,'female','Fiber, total dietary', 'g', 20.0, 25.0, 'AI', NULL);

-- ═══ TOLERABLE UPPER INTAKE LEVELS (mg) — from the PDRI UL table ═══
-- Iron UL 45 (age 1+); Calcium UL 3000 (2000 for >= 70). The PDRI UL table uses
-- IOM age rows for Vitamin C (1-2:400, 3-8:650, 9-13:1200, 14-18:1800, 19+:1000);
-- where an FNRI life-stage bracket straddles an IOM row (6-9, 13-15) the lower,
-- more protective UL is applied. Potassium/Sodium/Fiber: no UL established from
-- food sources -> left NULL so no false "upper limit" warnings fire.
UPDATE nutrient_standards SET ul_value = 45.0   WHERE nutrient_name = 'Iron';
UPDATE nutrient_standards SET ul_value = 3000.0 WHERE nutrient_name = 'Calcium' AND age_max < 70;
UPDATE nutrient_standards SET ul_value = 2000.0 WHERE nutrient_name = 'Calcium' AND age_max >= 70;
UPDATE nutrient_standards SET ul_value = 400.0  WHERE nutrient_name = 'Vitamin C' AND age_max <= 2;
UPDATE nutrient_standards SET ul_value = 650.0  WHERE nutrient_name = 'Vitamin C' AND age_min >= 3  AND age_max <= 9;
UPDATE nutrient_standards SET ul_value = 1200.0 WHERE nutrient_name = 'Vitamin C' AND age_min >= 10 AND age_max <= 15;
UPDATE nutrient_standards SET ul_value = 1800.0 WHERE nutrient_name = 'Vitamin C' AND age_min >= 16 AND age_max <= 18;
UPDATE nutrient_standards SET ul_value = 1000.0 WHERE nutrient_name = 'Vitamin C' AND age_min >= 19;
