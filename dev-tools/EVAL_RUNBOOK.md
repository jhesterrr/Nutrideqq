# NLP Evaluation Runbook — NutriDeq Symptom→Nutrient Mapping

How to run, reproduce, and cite evaluations of the NLP symptom-extraction pipeline.
Audience: the development team and the thesis evaluation chapter (Objective 4 —
ISO/IEC 25010 accuracy analysis).

---

## 1. Components and their versions

| Artifact | Location | Versioning |
|---|---|---|
| Mapping table (citable data) | `config/symptom_nutrient_mapping.json` | `version` + `released` + `changelog` in the file itself (v1.3.0) |
| Mapper (code) | `includes/SymptomMapper.php` | Reads whatever mapping version is in `config/`; v1.3 matcher = negation-aware + longest-match precedence |
| Text-speak normalizer | `includes/TextNormalizer.php` | Deterministic 3-layer canonicalization (registry → fuzzy skeleton → phonetic); runs BEFORE the prompt and the resolver |
| Gold corpus | `dev-tools/gold_set.php` | `corpus_version` in the file (v4.0.0, 125 cases incl. TXT-SPK) |
| Harness | `dev-tools/test_ai.php` | `HARNESS_VERSION` constant (v2.1.0) |
| Extraction prompt | `NutriDeqAI::buildExtractionPrompt()` in `api/ai_service.php` | Fingerprinted via sha256 (`prompt_hash`) in every report and persisted run |
| Run persistence | `nlp_eval_runs` table | `php migrations/apply.php 003_nlp_eval_audit.sql` |
| NLP scope | `handlers/send_message_ajax.php` | Client messages ONLY — `sender_type` is derived server-side from the session role; dietitian messages never reach the engine |

Rule: **change behavior → bump a version.** New keywords/columns = mapping
patch/minor bump with a changelog line. New/changed gold cases = corpus minor
bump. Harness metric changes = harness minor bump. Never edit keywords in code.

## 2. One-time setup

```bash
# 1. Schema (idempotent)
cd legacy-php && C:\xampp\php\php.exe migrations/apply.php 003_nlp_eval_audit.sql

# 2. Groq key for --live mode: create legacy-php/.env (git-ignored!)
#    GROQ_API_KEY=gsk_...
#    (or export GROQ_API_KEY in the shell). Without it, --live exits 2 with
#    a clear message; mock mode needs no key.
```

Verify the pipeline identity before trusting any report:

```bash
php dev-tools/test_ai.php --show-mapping   # mapping version + citation + keywords
php dev-tools/test_ai.php --show-corpus    # corpus version + all cases + limitations
```

## 3. Running evaluations

```bash
# Deterministic offline run (measures the MAPPING, not the LLM)
php dev-tools/test_ai.php

# With machine-readable report + DB persistence
php dev-tools/test_ai.php --persist --json=dev-tools/reports/nlp_eval_<mappingver>_<mode>.json

# Live LLM run (measures EXTRACTION + MAPPING end-to-end; costs API quota)
php dev-tools/test_ai.php --live --persist --json=dev-tools/reports/nlp_eval_live_run1.json
```

Every run prints: harness/mapping/corpus versions, prompt hash, per-class
precision/recall/F1, micro + macro averages, per-language exact rates,
Taglish micro-F1, negative-case FPR, distractor results (with documented
limitation calls), and PASS/FAIL vs the 80% micro-F1 target.

Reading the numbers honestly:

- **Mock mode** scores the deterministic keyword mapping against pre-annotated
  symptoms. Reproducible bit-for-bit; cite it as *mapping-table accuracy*.
- **Live mode** scores the full pipeline. LLM sampling varies run to run; cite
  it as *pipeline accuracy* with the variance protocol in §4.
- The 3 `limitation` distractor cases ("Hindi ako napagod…", "no headache…",
  "no bloating…") are **expected misses** in mock mode: substring matching
  cannot see negation. They are marked `limitation: negation` in the corpus,
  flagged in the report, and recorded in the persisted run's `notes` column.
  Do not "fix" them by deleting the cases; fix the matcher (v1.3) later.

## 4. Thesis protocol for live evaluation (Objective 4)

1. **Freeze** mapping + corpus versions for the study period; record both
   versions and the prompt hash in the methodology chapter.
2. **Run 5 independent live passes** on different days/times (API load varies):
   `--live --persist --json=...runN.json` for N=1..5.
3. **Report mean ± spread** of micro-F1, macro-F1, per-class F1, Taglish
   micro-F1, and negative FPR across the 5 runs — not a single best run.
4. **Pull the numbers from the database**, not from chat transcripts:

   ```sql
   SELECT mapping_version, mode, COUNT(*) runs,
          AVG(micro_f1) mean_f1, MIN(micro_f1) min_f1, MAX(micro_f1) max_f1,
          AVG(taglish_micro_f1) mean_tl_f1, AVG(negative_fpr) mean_fpr
   FROM nlp_eval_runs
   WHERE mode = 'live'
   GROUP BY mapping_version, mode;
   ```

5. Compare v1.1.0 → v1.2.0 (and any future mapping) by keeping historical
   runs: the `mapping_version` column is the grouping key for your
   improvement-claim tables.

## 5. Corpus governance (how regional terms earn their place)

The corpus claims EN/TL/BIS coverage. To stay defensible:

1. **Verification before inclusion.** A regional keyword enters the mapping
   table only with a checkable source (dictionary entry, published usage, or
   two native-speaker attestations recorded in the changelog line). The
   Ilocano set is absent in v1.2.0 precisely because no candidate passed yet.
2. **No invented cases.** Every corpus case must be realistic client prose.
   If a Cebuano term can't be verified, write the case as code-switched
   Taglish/Bisaya-English using already-verified keywords (see the BIS
   nausea case) — never mint a dictionary entry to fit a test.
3. **Collision sweep is mandatory.** After any keyword or corpus change, run
   the sweep: every positive case's expected classes must equal exactly what
   the mapper produces for its mock symptoms (limitation cases exempt):

   ```bash
   php -r "$c=require('dev-tools/gold_set.php');require('includes/SymptomMapper.php');
   foreach($c['cases'] as $k=>$cs){$got=[];foreach($cs['mock_symptoms'] as $ms){
   [,$cid]=SymptomMapper::mapWithClass($ms);if($cid&&!in_array($cid,$got))$got[]=$cid;}
   sort($got);$e=$cs['expected'];sort($e);
   if($got!==$e&&empty($cs['limitation']))echo \"#$k \",$cs['lang'],': ',implode('+',$got),' vs ',implode('+',$e),PHP_EOL;}"
   ```

4. **Precedence is data, not luck.** Longest-context keywords (e.g.
   "kabag sa kalamnan" before generic "kabag") live in earlier classes;
   document the intent in the class `notes` field.

## 6. Citation templates

**Mapping table:**
> NutriDeq Symptom-to-Nutrient Mapping Table, version 1.2.0 (2026-09-22).
> Symptom classes per the NutriDeq clinical knowledge base; nutrient keys per
> the DOST-FNRI Food Composition Table; reference values per the Philippine
> Dietary Reference Intakes 2015 (FNRI-DOST, Rev. Sept 2018).
> `legacy-php/config/symptom_nutrient_mapping.json`.

**Evaluation run (example):**
> Gold-set evaluation, harness v2.1.0, corpus v3.0.0 (113 cases), mapping
> v1.2.0, prompt sha256 `22f81eb3…`, mock mode: micro-F1 0.985 (macro 0.986),
> subset accuracy 97.3%, Taglish micro-F1 1.000, negative FPR 0.000; 3
> documented negation-limitation misses. Stored in `nlp_eval_runs`
> (run_uid `b2d61140…`).

**Live pipeline run:** same shape, `mode='live'`, plus run count and
mean ± spread per §4.

## 7. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `FATAL: mapping table unavailable` | `config/symptom_nutrient_mapping.json` missing or invalid JSON — validate with `php -r "json_decode(file_get_contents('config/symptom_nutrient_mapping.json'),true);" ` |
| `FATAL: corpus case #N expects unknown class` | Corpus references a class_id removed/renamed in the mapping — reconcile both files, bump versions |
| `ERROR: GROQ_API_KEY not set` (exit 2) | Expected without a key; add `legacy-php/.env` or export it |
| `WARNING: DB persistence failed` (exit 3) | Migration 003 not applied, or DB down — run the migration command from §2 |
| Per-class metrics shift after a corpus edit | Expected: support counts changed. Re-run the §5 collision sweep, then re-baseline mock + live runs |
