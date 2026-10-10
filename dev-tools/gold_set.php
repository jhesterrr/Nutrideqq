<?php
// dev-tools/gold_set.php
//
// NutriDeq NLP gold corpus — returns the curated evaluation dataset.
//
// Case fields:
//   message       raw client message as typed (mixed language is intentional)
//   lang          EN | TL | BIS for positives; -neg suffix for neutral
//                 negatives; -distr for distractors (plausible wording that
//                 must NOT trigger a mapping)
//   expected      class_ids from config/symptom_nutrient_mapping.json
//   mock_symptoms symptom labels a compliant LLM would extract; used by mock
//                 mode. For the three `limitation` cases below the mock
//                 deliberately simulates a mention-extracting model with no
//                 negation handling, so the harness exercises — and reports —
//                 the mapper's known substring-matching limitation.
//
// Design rules:
//  * Expected values are class_ids (stable) — never raw nutrient strings.
//  * Positive messages are checked against the FULL keyword table so no case
//    accidentally matches a second class via substring collision.
//  * Regional terms: Bisaya (Cebuano) cases use only verified terms. Ilocano
//    cases are pending linguistic verification and are deliberately absent
//    (see dev-tools/EVAL_RUNBOOK.md, "Corpus governance").

return [
    'corpus_version' => '5.0.0',
    'updated' => '2026-10-10',
    'languages' => ['EN', 'TL', 'BIS', 'TXT-SPK'],
    'notes' => 'v5 adds canonical-dedup and punctuation/human-phrasing cases (mapping v1.5.0: synonym collapse to one label per class). v4 added Filipino text-speak/shortcut cases (TXT-SPK). All run through TextNormalizer first, exactly like production.',
    'known_limitations' => [
        'v1.3.0 matcher resolves the former negation limitation: message-level negation cues ("no headache", "hindi na ako napagod", "wala nang lagnat") now suppress keyword matches, both in the deterministic matcher and as a production guard on LLM extraction output. No active limitations as of corpus v4.0.0 / mapping v1.3.0.',
    ],

    'cases' => [
        // ── SC-FATIGUE → Iron (18) ──────────────────────────────────────────
        ['message' => 'I constantly feel exhausted and weak', 'lang' => 'EN', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['fatigue', 'weakness']],
        ['message' => 'I get tired easily after minimal exertion', 'lang' => 'EN', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['tiredness']],
        ['message' => 'I feel fatigued all the time, even after rest', 'lang' => 'EN', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['fatigue']],
        ['message' => 'My energy levels are very low lately', 'lang' => 'EN', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['fatigue']],
        ['message' => 'By mid-afternoon I am completely drained and exhausted', 'lang' => 'EN', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['exhaustion']],
        ['message' => 'Climbing the stairs leaves me weak and out of breath', 'lang' => 'EN', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['weakness']],
        ['message' => 'Simple tasks wipe me out, the tiredness never stops', 'lang' => 'EN', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['tiredness']],
        ['message' => 'I have low energy before lunch every day', 'lang' => 'EN', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['low energy']],
        ['message' => 'Ang pagod ko na talaga these past few days', 'lang' => 'TL', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['pagod']],
        ['message' => 'Walang lakas ang katawan ko kahit kakakain ko lang', 'lang' => 'TL', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['walang lakas']],
        ['message' => 'Napakapagod ako after ng shift ko', 'lang' => 'TL', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['pagod']],
        ['message' => 'Kapaguran levels through the roof this week', 'lang' => 'TL', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['kapaguran']],
        ['message' => 'Parang hina ng katawan ko na talaga these days', 'lang' => 'TL', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['hina ng katawan']],
        ['message' => 'Grabe ang low energy ko simula Monday', 'lang' => 'TL', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['low energy']],
        ['message' => 'Kapoy kaayo ko ron, gusto ko na matulog', 'lang' => 'BIS', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['kapoy']],
        ['message' => 'Walay lain akong gibati gawas kapoy', 'lang' => 'BIS', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['kapoy']],
        ['message' => 'Pagkapoy nako oi, sige rako tulog pero dili gihapon maayo', 'lang' => 'BIS', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['kapoy']],
        ['message' => 'Kapoy kaayo akong lawas inig buntag', 'lang' => 'BIS', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['kapoy']],

        // ── SC-CRAMP → Potassium, K (14) ────────────────────────────────────
        ['message' => 'I experience muscle cramps in my legs at night', 'lang' => 'EN', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['muscle cramps']],
        ['message' => 'My calves keep cramping, especially after exercise', 'lang' => 'EN', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['calf cramps']],
        ['message' => 'I have muscle spasms that come and go', 'lang' => 'EN', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['muscle spasms']],
        ['message' => 'My fingers spasm when I write for too long', 'lang' => 'EN', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['hand spasms']],
        ['message' => 'Charley horses wake me up at night with painful cramps', 'lang' => 'EN', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['leg cramps']],
        ['message' => 'After long runs my feet cramp badly', 'lang' => 'EN', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['foot cramps']],
        ['message' => 'Sobrang pulikat ng binti ko tuwing gabi', 'lang' => 'TL', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['pulikat']],
        ['message' => 'May kalampos ako sa hita minsan', 'lang' => 'TL', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['kalampos']],
        ['message' => 'Kabag sa kalamnan ang naramdaman ko after mag-gym', 'lang' => 'TL', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['kabag sa kalamnan']],
        ['message' => 'Pulikat na naman sa binti ko, grabe', 'lang' => 'TL', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['pulikat']],
        ['message' => 'Muscle spasms ako sa likod pagkatapos magbuhat ng mabigat', 'lang' => 'TL', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['muscle spasms']],
        ['message' => 'Grabe ka pulikat sa akong tiil kagabi', 'lang' => 'BIS', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['pulikat']],
        ['message' => 'May kalampos sa akong paa kada buntag', 'lang' => 'BIS', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['kalampos']],
        ['message' => 'Pulikat-pulikat na lang akong legs sa gabii', 'lang' => 'BIS', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['pulikat']],

        // ── SC-DIZZY → Sodium (13) ──────────────────────────────────────────
        ['message' => 'I feel dizzy when I stand up quickly', 'lang' => 'EN', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['dizziness']],
        ['message' => 'I get lightheaded spells throughout the day', 'lang' => 'EN', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['lightheadedness']],
        ['message' => 'The room spins when I stand, and I get dizzy spells', 'lang' => 'EN', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['dizziness']],
        ['message' => 'Nearly fainted at the mall from feeling lightheaded', 'lang' => 'EN', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['lightheadedness']],
        ['message' => 'Everything goes blurry and dizzy when I skip meals', 'lang' => 'EN', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['dizziness']],
        ['message' => 'Dizziness hits me every afternoon', 'lang' => 'EN', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['dizziness']],
        ['message' => 'Nahihilo ako kapag tumatayo ako agad', 'lang' => 'TL', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['nahihilo']],
        ['message' => 'Bigla akong nahihilo ngayon lang', 'lang' => 'TL', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['hilo']],
        ['message' => 'Hilo na naman, parang iikot ang paligid', 'lang' => 'TL', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['hilo']],
        ['message' => 'Ang lula lula ko kanina sa jeep', 'lang' => 'TL', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['lula']],
        ['message' => 'Lightheaded ako buong maghapon, kulang tulog siguro', 'lang' => 'TL', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['lightheadedness']],
        ['message' => 'Nahilo ko gabii, grabe kaayo', 'lang' => 'BIS', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['nahilo']],
        ['message' => 'Dili ko ganahan magtindog dayon kay mahilo ko', 'lang' => 'BIS', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['hilo']],

        // ── SC-HEADACHE → Calcium (13) ──────────────────────────────────────
        ['message' => "I have frequent headaches that won't go away", 'lang' => 'EN', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['headache']],
        ['message' => 'I get throbbing headaches every morning', 'lang' => 'EN', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['headache']],
        ['message' => 'Migraines knock me out twice a week', 'lang' => 'EN', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['migraine']],
        ['message' => 'A dull headache sits behind my eyes all day', 'lang' => 'EN', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['headache']],
        ['message' => 'Pressure-like headache after long screen time', 'lang' => 'EN', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['headache']],
        ['message' => 'My daily headaches pound behind my forehead', 'lang' => 'EN', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['headache']],
        ['message' => 'Ang sakit ng ulo ko simula pa kahapon', 'lang' => 'TL', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['sakit ng ulo']],
        ['message' => 'Sakit ng ulo nanaman, pangatlong araw na', 'lang' => 'TL', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['sakit ng ulo']],
        ['message' => 'Barado ang ulo ko sa kakaisip sa thesis', 'lang' => 'TL', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['barado']],
        ['message' => 'Migraine attack nanaman, hindi ako makatayo', 'lang' => 'TL', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['migraine']],
        ['message' => 'Ang sakit ng ulo ko kapag umaga, laging ganyan', 'lang' => 'TL', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['sakit ng ulo']],
        ['message' => 'Sakit ug ulo kaayo nako ron', 'lang' => 'BIS', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['sakit ug ulo']],
        ['message' => 'Sakit ug ulo gikan pa sa umaga', 'lang' => 'BIS', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['sakit ug ulo']],

        // ── SC-NAUSEA → Vitamin C (12) ──────────────────────────────────────
        ['message' => 'I feel nauseous after meals', 'lang' => 'EN', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['nausea']],
        ['message' => 'I have been feeling queasy and nauseous', 'lang' => 'EN', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['nausea']],
        ['message' => 'The smell of fried food makes me nauseous', 'lang' => 'EN', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['nausea']],
        ['message' => 'Morning queasy spells last until noon', 'lang' => 'EN', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['queasy']],
        ['message' => 'I keep retching and feel nauseous after taking supplements', 'lang' => 'EN', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['nausea']],
        ['message' => 'Nausea waves hit me during car rides', 'lang' => 'EN', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['nausea']],
        ['message' => 'Sukang-suka ako after kumain, parang maiinit ang sikmura', 'lang' => 'TL', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['susuka', 'naiinit']],
        ['message' => 'Naiinit ako, parang masusuka na', 'lang' => 'TL', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['naiinit']],
        ['message' => 'Nauseous ako sa amoy ng fish sauce', 'lang' => 'TL', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['nauseous']],
        ['message' => 'Sa byahe kanina, naiinit at nauseous ako the whole ride', 'lang' => 'TL', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['naiinit', 'nauseous']],
        ['message' => 'Queasy ako tuwing umaga after ng pills ko', 'lang' => 'TL', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['queasy']],
        ['message' => 'Nauseous kaayo akong gibati human mokaon ug tambok', 'lang' => 'BIS', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['nauseous']],

        // ── SC-BLOAT → Fiber, total dietary (13) ────────────────────────────
        ['message' => 'I feel bloated and gassy after eating', 'lang' => 'EN', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['bloating']],
        ['message' => 'My stomach feels swollen and bloated', 'lang' => 'EN', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['bloating']],
        ['message' => 'Constant gas and a distended belly after meals', 'lang' => 'EN', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['gas', 'bloating']],
        ['message' => 'My jeans feel tight from bloating every dinner', 'lang' => 'EN', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['bloating']],
        ['message' => 'I look six months pregnant from the gas buildup', 'lang' => 'EN', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['gas', 'bloating']],
        ['message' => 'Belching and gas pains all afternoon', 'lang' => 'EN', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['gas']],
        ['message' => 'Ang kabag ko, sikip na sikip ang tiyan ko', 'lang' => 'TL', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['kabag', 'sikip ng tiyan']],
        ['message' => 'Namamaga ang tiyan ko tuwing gabi', 'lang' => 'TL', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['namamaga ang tiyan']],
        ['message' => 'Grabe ang gas ko after ng beans', 'lang' => 'TL', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['gas']],
        ['message' => 'Kabag nanaman, hindi ako makatino', 'lang' => 'TL', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['kabag']],
        ['message' => 'Sikip ng tiyan ko buong araw, ang uncomfortable', 'lang' => 'TL', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['sikip ng tiyan']],
        ['message' => 'Naay hangin sa tiyan nga ako gibati karon', 'lang' => 'BIS', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['hangin sa tiyan']],
        ['message' => 'Kabag kaayo ako human mokaon ug kamote', 'lang' => 'BIS', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['kabag']],

        // ── Combined symptom cases (9) ──────────────────────────────────────
        ['message' => 'I feel exhausted AND get muscle cramps at night', 'lang' => 'EN', 'expected' => ['SC-FATIGUE', 'SC-CRAMP'], 'mock_symptoms' => ['fatigue', 'muscle cramps']],
        ['message' => 'I am dizzy and fatigued', 'lang' => 'EN', 'expected' => ['SC-DIZZY', 'SC-FATIGUE'], 'mock_symptoms' => ['dizziness', 'fatigue']],
        ['message' => 'Headache plus bloated stomach after lunch', 'lang' => 'EN', 'expected' => ['SC-HEADACHE', 'SC-BLOAT'], 'mock_symptoms' => ['headache', 'bloating']],
        ['message' => 'Pagod na pagod ako tas pulikat pa ang binti', 'lang' => 'TL', 'expected' => ['SC-FATIGUE', 'SC-CRAMP'], 'mock_symptoms' => ['pagod', 'pulikat']],
        ['message' => 'Sakit ng ulo ko tapos kabag pa', 'lang' => 'TL', 'expected' => ['SC-HEADACHE', 'SC-BLOAT'], 'mock_symptoms' => ['sakit ng ulo', 'kabag']],
        ['message' => 'Sukang-suka ako tapos hilo pa sa byahe', 'lang' => 'TL', 'expected' => ['SC-NAUSEA', 'SC-DIZZY'], 'mock_symptoms' => ['susuka', 'hilo']],
        ['message' => 'Kapoy kaayo ko ug naa pay pulikat sa akong legs', 'lang' => 'BIS', 'expected' => ['SC-FATIGUE', 'SC-CRAMP'], 'mock_symptoms' => ['kapoy', 'pulikat']],
        ['message' => 'Nahilo ko ganina ug kapoy pa jud', 'lang' => 'BIS', 'expected' => ['SC-DIZZY', 'SC-FATIGUE'], 'mock_symptoms' => ['nahilo', 'kapoy']],
        ['message' => 'Sakit ug ulo ug naay hangin sa tiyan', 'lang' => 'BIS', 'expected' => ['SC-HEADACHE', 'SC-BLOAT'], 'mock_symptoms' => ['sakit ug ulo', 'hangin sa tiyan']],

        // ── Text-speak / shortcut cases (TXT-SPK) ──────────────────────────
        // These go through TextNormalizer::normalize() first (production path).
        // mock_symptoms list what a compliant model would extract AFTER
        // normalization. Evaluated live, the Groq prompt receives the same
        // normalized text the production pipeline sends.
        ['message' => 'mskit chan q grabe', 'lang' => 'TXT-SPK', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['masakit tiyan ko'], 'note' => 'canonicalizes to "masakit tiyan ko" — stomach-area pain → closest class is bloating (no dedicated generic stomach class in v1.2.0)'],
        ['message' => 'pgd n q tlga', 'lang' => 'TXT-SPK', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['pagod na ko talaga']],
        ['message' => 'wl lkas aq simula pa khpon', 'lang' => 'TXT-SPK', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['walang lakas ako']],
        ['message' => 'hlo q kpg tmtayo agad', 'lang' => 'TXT-SPK', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['hilo']],
        ['message' => 'plkt n nman bnti q', 'lang' => 'TXT-SPK', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['pulikat']],
        ['message' => 'sska q grbe amoy ng ulam', 'lang' => 'TXT-SPK', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['susuka']],
        ['message' => 'kbg n nman tyan q', 'lang' => 'TXT-SPK', 'expected' => ['SC-BLOAT'], 'mock_symptoms' => ['kabag']],
        ['message' => 'ang skt ng ulo q simula khpon', 'lang' => 'TXT-SPK', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['sakit ng ulo']],
        ['message' => 'pgd n q taz plkt p ang bnti q', 'lang' => 'TXT-SPK', 'expected' => ['SC-FATIGUE', 'SC-CRAMP'], 'mock_symptoms' => ['pagod', 'pulikat']],
        ['message' => 'gising buong gbi, sobrng pgd n q', 'lang' => 'TXT-SPK', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['pagod']],
        ['message' => 'okay n q, wla n skt chan q', 'lang' => 'TXT-SPK-neg', 'expected' => [], 'mock_symptoms' => [], 'note' => 'resolution phrasing — must NOT create a symptom'],
        ['message' => 'salamat doc, feeling bt n q', 'lang' => 'TXT-SPK-neg', 'expected' => [], 'mock_symptoms' => []],

        // ── Negative cases — neutral, must map to nothing (12) ─────────────
        ['message' => 'I had a great workout today', 'lang' => 'EN-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'I enjoyed my lunch and feel satisfied', 'lang' => 'EN-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'The weather is nice today', 'lang' => 'EN-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Finally finished my module, feeling productive', 'lang' => 'EN-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Booked our tickets for the beach trip', 'lang' => 'EN-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Team lunch later to celebrate the win', 'lang' => 'EN-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Ang ganda ng sunset kagabi grabe', 'lang' => 'TL-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Tapos ko na ang laundry, tagumpay', 'lang' => 'TL-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Nanood kami ng sine, ang ganda ng plot', 'lang' => 'TL-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Lami kaayo ang luto ni mama kagabi', 'lang' => 'BIS-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Kaligo nako unya, init kaayo ang panahon', 'lang' => 'BIS-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Nagdula mi basketball, lipay kaayo', 'lang' => 'BIS-neg', 'expected' => [], 'mock_symptoms' => []],

        // ── Distractors — plausible wording that must NOT map (9) ──────────
        // The three `limitation` cases intentionally exercise negation: a
        // mention-extracting model hands the mapper "napagod"/"headache"/
        // "bloating" even though the client DENIED the symptom. Substring
        // matching will fire → these document the known limitation honestly.
        ['message' => 'Nag dumblrak ako, ang sarap ng hangin sa bundok', 'lang' => 'TL-distr', 'expected' => [], 'mock_symptoms' => ['hangin sa bundok']],
        ['message' => 'Hindi ako napagod sa lakad ngayon', 'lang' => 'TL-distr', 'expected' => [], 'mock_symptoms' => ['napagod'], 'limitation' => 'negation'],
        ['message' => 'I hit my head on the cabinet but no headache', 'lang' => 'EN-distr', 'expected' => [], 'mock_symptoms' => ['headache'], 'limitation' => 'negation'],
        ['message' => 'My stomach is fine now, no bloating at all', 'lang' => 'EN-distr', 'expected' => [], 'mock_symptoms' => ['bloating'], 'limitation' => 'negation'],
        ['message' => 'Ang bagal ng wifi kanina, nakakainis', 'lang' => 'TL-distr', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Nag-schedule ako ng checkup next week', 'lang' => 'TL-distr', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'I feel strong after a full night of rest', 'lang' => 'EN-distr', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Wala moy hilanat? Bugnaw ra kaayo ang panahon diri', 'lang' => 'BIS-distr', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'Dako na kaayo ang progress sa akong diet this month', 'lang' => 'BIS-distr', 'expected' => [], 'mock_symptoms' => []],

        // ── Corpus v5.0.0 — canonical dedup, punctuation, human phrasing ────
        // Deterministic (offline) expectations computed from TextNormalizer +
        // SymptomMapper::matchText; live expectations from the canonicalizing
        // prompt. Exercises: repeated-letter collapse, punctuation-glued
        // shortcuts, and richer English human phrasing.
        ['message' => 'sobranggg pgd n q', 'lang' => 'TXT-SPK', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['fatigue'], 'note' => 'repeated-letter collapse: sobranggg -> sobrang'],
        ['message' => 'plkt plkt bnti q kpg gbi', 'lang' => 'TXT-SPK', 'expected' => ['SC-CRAMP'], 'mock_symptoms' => ['muscle cramps'], 'note' => 'expanded function words kpg/gbi/bnti'],
        ['message' => 'hlo n nman aq kpg tmtayo', 'lang' => 'TXT-SPK', 'expected' => ['SC-DIZZY'], 'mock_symptoms' => ['dizziness']],
        ['message' => 'ang skt ng ulo q, 3 araw na', 'lang' => 'TXT-SPK', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['headache'], 'note' => 'punctuation no longer blocks shortcut expansion'],
        ['message' => 'sska n q sa amoy ng pagkain', 'lang' => 'TXT-SPK', 'expected' => ['SC-NAUSEA'], 'mock_symptoms' => ['nausea']],
        ['message' => 'my head is pounding, migraine again', 'lang' => 'EN', 'expected' => ['SC-HEADACHE'], 'mock_symptoms' => ['headache']],
        ['message' => 'i feel drained and weak all day', 'lang' => 'EN', 'expected' => ['SC-FATIGUE'], 'mock_symptoms' => ['fatigue']],
        // Resolution phrasing must NOT create a symptom (and must close one).
        ['message' => 'wla n skt chan q, ok n q', 'lang' => 'TXT-SPK-neg', 'expected' => [], 'mock_symptoms' => [], 'note' => 'negation scope after normalization'],
        ['message' => 'hnd na aq pgd, slmt doc', 'lang' => 'TXT-SPK-neg', 'expected' => [], 'mock_symptoms' => []],
        ['message' => 'wala nang lagnat, ok na ako', 'lang' => 'TXT-SPK-neg', 'expected' => [], 'mock_symptoms' => []],
    ],
];
