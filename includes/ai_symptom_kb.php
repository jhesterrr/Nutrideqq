<?php
/**
 * AI Symptom Knowledge Base — Plain-Language Descriptions & Actionables
 *
 * This helper maps symptom keywords (from ai_extracted_symptoms table)
 * to human-friendly educational explanations and practical next-steps.
 *
 * Used by: staff-messages.php (dietitian view) and user-messages.php (client view)
 *
 * NOTE: All content is EDUCATIONAL ONLY — not medical advice.
 *       Always pair with a qualified dietitian consultation.
 */

if (!defined('NUTRIDEQ_KB_LOADED')) {
    define('NUTRIDEQ_KB_LOADED', true);

    function nd_get_symptom_kb() {
        static $kb = null;
        if ($kb !== null) return $kb;

        $kb = [
            // ─── Mood & Energy ───────────────────────────────────────
            'depression' => [
                'icon' => '😔',
                'category' => 'Mood & Brain',
                'short_desc' => 'Persistent low mood or loss of interest linked to nutrient gaps that affect brain chemistry.',
                'full_desc' => 'When your diet lacks key building blocks for neurotransmitters like serotonin (the "feel-good" chemical), you may notice prolonged sadness, irritability, loss of motivation, or difficulty enjoying things you once liked. Low mood is also commonly tied to poor sleep quality, unstable blood sugar, and chronic inflammation — all of which nutrition directly influences.',
                'recommended' => [
                    'Don\'t skip breakfast — try sinangag (garlic fried rice) with egg and tomato to keep blood sugar steady and avoid mood swings.',
                    'Cook fish 2–3 times a week: sardines (canned or fresh), tuyo (dried fish), bangus (milkfish), or galunggong (round scad) — these are rich in omega-3 which is great for the brain and mood.',
                    'Add leafy greens to your meals every day: kangkong (water spinach), talbos ng kamote (sweet potato leaves), pechay (bok choy), or mustasa (mustard greens) for folate and B vitamins that help lift your mood.',
                    'Take a 10–15 minute walk in the morning sun — Vitamin D from sunlight will naturally lighten your mood.',
                ],
                'when_to_ask' => 'Low mood lasting 2+ weeks, or if it affects sleep, appetite, work, or relationships — let your dietitian help you connect it to food patterns.'
            ],
            'anxiety' => [
                'icon' => '😰',
                'category' => 'Mood & Brain',
                'short_desc' => 'Frequent worry or restlessness often worsened by blood-sugar swings and low magnesium/B-vitamins.',
                'full_desc' => 'Racing thoughts, butterflies, tight chest, or trouble winding down can be amplified by what you eat. Skipping meals causes glucose dips that trigger adrenaline (the "stress" hormone). Caffeine, added sugars, and low magnesium can overstimulate the nervous system and make anxiety feel harder to manage.',
                'recommended' => [
                    'Every meal should have rice + a protein dish like fish, chicken, or tokwa (tofu) — avoid quick sugary snacks only (like pandesal with sweet spread) that speed up your heartbeat.',
                    'Limit coffee to just one cup in the morning — replace extra coffee with salabat (ginger tea), banaba tea, or plain water.',
                    'Keep simple calming snacks handy: buto ng kalabasa (squash seeds), peanuts, or warm milk — these contain magnesium that helps calm the nerves.',
                    'Cut back on too much sugar like candy, softdrinks, or donuts — the high-then-crash energy pattern makes anxiety worse.',
                ],
                'when_to_ask' => 'If anxious feelings interfere with work, sleep, or social time — your dietitian can build a "Calm Eating" plan with you.'
            ],
            'fatigue' => [
                'icon' => '😴',
                'category' => 'Energy',
                'short_desc' => 'Ongoing tiredness even after sleep — often a signal of low iron, vitamin B12, or poor oxygen transport.',
                'full_desc' => 'Feeling drained all day, even after a full night\'s sleep, is your body\'s way of saying it\'s missing the fuel or building blocks it needs. Two of the biggest nutrition culprits are low iron (which carries oxygen in your blood) and low B12 (which makes red blood cells and energy). Dehydration, unstable blood sugar, and low calorie intake can also cause persistent fatigue.',
                'recommended' => [
                    'Eat iron-rich foods daily: chicken or pork liver (sisig or sauteed), dilis (anchovies), tuyo (dried fish), mung beans, or tokwa (tofu). Pair them with vitamin C foods like tomato, mango, or calamansi to double iron absorption!',
                    'Drink milk or eat an egg every day for B12 — essential for keeping your body energetic.',
                    'Don\'t forget water — 8 glasses or more a day. Even mild dehydration can make you tire faster.',
                    'A perfect midday snack: saging na saba (cooking banana) + peanuts or keso (cheese) — a fruit-protein combo that delivers sustained energy.',
                ],
                'when_to_ask' => 'Tiredness lasting 2+ weeks or paired with shortness of breath, pale skin, or rapid heartbeat — your dietitian can test for anemia patterns and target iron/B12 intake.'
            ],
            'low energy' => [
                'icon' => '🔋',
                'category' => 'Energy',
                'short_desc' => 'Daily energy dips or difficulty getting through the day — linked to meal timing, hydration, and nutrient gaps.',
                'full_desc' => 'Similar to fatigue but often milder and tied to daily patterns. Common causes: skipping meals, not eating enough total calories, relying too much on quick sugars, not drinking enough water, or low B-vitamin and iron stores that prevent your cells from making energy efficiently.',
                'recommended' => [
                    'Don\'t go longer than 5 hours without eating — have breakfast, lunch, dinner, and 1–2 snacks in between (like kwek-kwek, bananacue, or rice + tuyo).',
                    'At every meal, remember the 3 parts: rice or corn (carbs) + protein dish (fish/chicken/egg/tofu) + vegetable or fruit.',
                    'Drink one large glass of water right after waking up — often, morning tiredness is just dehydration.',
                    'Avoid daily chips, chichirya (snack foods), or overly sweet bread — they taste good briefly but leave you feeling weaker afterward.',
                ],
                'when_to_ask' => 'If you\'re sleeping enough and still dragging by mid-afternoon most days — dietitian can help spot eating patterns draining your energy.'
            ],
            'brain fog' => [
                'icon' => '🌫️',
                'category' => 'Cognition',
                'short_desc' => 'Trouble focusing, remembering, or "thinking clearly" — often tied to hydration, B12, or blood sugar.',
                'full_desc' => 'If you regularly zone out, lose your train of thought, forget words, or struggle to finish tasks, nutrition may be the missing piece. Your brain runs on glucose (steady supply!) and is 73% water. Dehydration of just 1–2% noticeably drops focus and short-term memory. Low B12, omega-3 fats, and zinc are also strongly linked to brain fog.',
                'recommended' => [
                    'First thing: Drink a big glass of water before coffee in the morning. Your mind can\'t work properly if you\'re dehydrated.',
                    'Add healthy fats to your cooking: try fried fish (bangus, tilapia) or dip warm rice in bagoong (fermented fish paste) for good oils. You can also use coconut milk or gata.',
                    'Instead of white bread, try brown rice, corn, or sinangag (garlic fried rice) — these digest slowly so your brain doesn\'t suddenly spike and crash.',
                    'Drink milk or eat an egg at breakfast — Vitamin B12 helps you feel mentally alert all day.',
                ],
                'when_to_ask' => 'If brain fog is getting in the way of work, studying, or daily tasks — your dietitian can build a "Brain Food" meal map with you.'
            ],
            'insomnia' => [
                'icon' => '🌙',
                'category' => 'Sleep',
                'short_desc' => 'Trouble falling or staying asleep — made worse by caffeine timing, sugar at night, and low tryptophan/magnesium.',
                'full_desc' => 'Food and drinks directly affect your sleep hormones (melatonin and serotonin). Caffeine after 2pm, large spicy meals right before bed, alcohol (which may make you drowsy but ruins deep sleep), and sugary late-night snacks can all leave you tossing. Low magnesium or tryptophan (from protein foods) means your body can\'t "switch off" easily.',
                'recommended' => [
                    'No more coffee or strong tea after 2pm — caffeine stays in your body for 6+ hours. This includes caffeinated sodas too.',
                    'For a bedtime snack: banana + a handful of peanuts, warm milk (chocolate milk is fine!), or a small bowl of lugaw (rice porridge). Skip the chips and sweets.',
                    'Avoid cooking super spicy or oily dinners if bedtime is near. Eat dinner early (around 6–7pm).',
                    'Add kangkong (water spinach) or talbos ng kamote (sweet potato leaves) to dinner — these green leaves have magnesium that helps the body and mind relax.',
                ],
                'when_to_ask' => 'If poor sleep happens 3+ nights a week for over a month — your dietitian can create an evening eating routine that supports sleep.'
            ],

            // ─── Pain & Muscles ──────────────────────────────────────
            'muscle cramps' => [
                'icon' => '🦵',
                'category' => 'Muscle & Nerve',
                'short_desc' => 'Sudden tight, painful muscle spasms (usually legs/calves) — linked to low potassium and magnesium.',
                'full_desc' => 'That painful "Charley horse" or random muscle tightness, especially at night or after exercise, is almost always an electrolyte signal. Potassium and magnesium work together to make your muscles contract and relax properly. If levels are low, muscles lock up. Sodium (salt) loss from heavy sweating or dehydration can also trigger cramping.',
                'recommended' => [
                    'Eat bananas, kamoteng kahoy (cassava), or saging na saba daily — these are rich in potassium which relaxes muscles.',
                    'Snacks rich in magnesium: peanuts, squash seeds, or a big bowl of boiled sweet potato leaves (talbos ng kamote) or kangkong.',
                    'Before going to bed, gently stretch your legs and drink one glass of water — this reduces muscle tightening at night.',
                    'If you worked outside and sweated heavily: drink water with a pinch of salt and a banana (better than plain water) to replace lost salt and minerals.',
                ],
                'when_to_ask' => 'Cramps that happen 3+ times per week, or paired with weakness/numbness — your dietitian can help track electrolytes and hydration.'
            ],
            'muscle soreness' => [
                'icon' => '💪',
                'category' => 'Muscle & Nerve',
                'short_desc' => 'Prolonged muscle aching after movement — often needs more protein, magnesium, and anti-inflammatory foods.',
                'full_desc' => 'It\'s normal to feel it after a hard workout, but if muscle ache lingers for 3+ days or happens from light activity, nutrition may be playing a role. Protein rebuilds micro-tears in muscle tissue; if intake is too low, recovery slows. Low vitamin D also causes muscle weakness and aches, while anti-inflammatory foods (omega-3, colorful produce) speed healing.',
                'recommended' => [
                    'Make sure every meal has protein: egg, tuyo (dried fish), sardines, chicken, pork, tokwa (tofu), or gata (coconut milk) — you need this to rebuild stressed muscles.',
                    'After heavy exercise or lifting: eat rice + a protein dish (like fried egg + sinangag) within 1–2 hours for faster recovery.',
                    'Cook sinigang na bangus (tamarind soup with milkfish) or similar fish soup 2 times a week — fish oil reduces swelling and body aches.',
                    'If muscles are sore: try fresh kamias (bilimbi) juice mixed with water or a ripe mango — these sour fruits naturally help reduce inflammation.',
                ],
                'when_to_ask' => 'Soreness that doesn\'t improve with rest, or paired with dark urine — ask your dietitian to review your daily protein and supplement plan.'
            ],
            'joint pain' => [
                'icon' => '🦴',
                'category' => 'Bone & Joint',
                'short_desc' => 'Achy, stiff, or swollen joints — often improved with vitamin D, calcium, and anti-inflammatory foods.',
                'full_desc' => 'Joint discomfort (knees, hips, fingers, back) can come from wear-and-tear, but diet either fuels or fights the inflammation around joints. When you eat lots of pro-inflammatory foods (fried, sugary, processed) plus low in antioxidants and omega-3s, joint stiffness and pain get worse. Low calcium and vitamin D weaken bones underneath too.',
                'recommended' => [
                    'Reduce too much fried food (like daily prito) and switch to nilaga (boiled), sinigang (sour soup), or paksiw (vinegar-stewed) — excess oil worsens joint pain.',
                    'Eat milk, cheese, eggs, or dilis (anchovies) every day for calcium and Vitamin D. 15–20 minutes of morning sun is also free Vitamin D!',
                    'Add green leafy veggies like kangkong, pechay, saluyot, and malunggay (moringa) to your soups — these also have calcium.',
                    'Drink tuba-tuba (cherry) juice if available, or eat fresh pineapple — these are natural anti-inflammatory "medicines" for pain.',
                ],
                'when_to_ask' => 'If joints stay swollen, red, or the pain is getting worse — a dietitian can help build an anti-inflammatory meal plan specific to Filipino dishes.'
            ],
            'headache' => [
                'icon' => '🤕',
                'category' => 'Pain',
                'short_desc' => 'Frequent headaches or migraines often triggered by dehydration, skipped meals, and magnesium deficiency.',
                'full_desc' => 'Most headaches tie back to at least one nutritional trigger. The #1 cause is mild dehydration. Other culprits: skipping meals causes low blood sugar that tightens brain blood vessels. Low magnesium and B2 (riboflavin) are scientifically linked to migraines. Tyramine in aged cheese, processed meats, excessive caffeine withdrawal, and added sulfites (wine, dried fruit) also trigger headaches in sensitive people.',
                'recommended' => [
                    'When you have a headache, IMMEDIATELY drink 2 glasses of water — 3 out of 10 times, the pain goes away in 20–30 minutes because it\'s just dehydration.',
                    'Don\'t skip meals — especially breakfast. Going too long without eating triggers headaches.',
                    'Drink milk or eat peanuts and an egg every morning — the riboflavin (B2) and magnesium in these are proven to reduce migraines.',
                    'Take note of which foods trigger your headaches: for example, strong coffee, aged cheese, or MSG (seasoning on snacks). Then avoid them.',
                ],
                'when_to_ask' => 'More than 2 headaches per week, or migraines that stop your day — a dietitian can help you build a personalized trigger-elimination plan.'
            ],
            'body aches' => [
                'icon' => '🩹',
                'category' => 'Pain',
                'short_desc' => 'Widespread body or bone pain — may be a sign of vitamin D deficiency or low calcium intake.',
                'full_desc' => 'Generalized "all-over" aches, bone tenderness, or muscle weakness that isn\'t tied to a specific injury often connect to vitamin D. Your body needs D to absorb calcium and build strong bones; without it, bones become soft (osteomalacia in adults, rickets in kids) and achy. Low D also worsens general inflammation sensitivity.',
                'recommended' => [
                    'Get 10–20 minutes of sun exposure around 10am (before putting on sunscreen) — this is the cheapest and most effective way to get Vitamin D for your bones.',
                    'Include calcium and D-rich foods: milk (powdered or fresh), eggs, dilis (anchovies), sardines with bones, and gata (coconut milk).',
                    'Cook bulalo (bone marrow soup) or sinabawang buto-buto (bone broth) occasionally — the soup from bones also helps bone health.',
                    'If you always stay indoors or live in a dark home: you may need additional Vitamin D3 — ask your dietitian for the right amount.',
                ],
                'when_to_ask' => 'Aches that last for weeks, or paired with fatigue and hair loss — your dietitian can recommend blood testing for D levels.'
            ],

            // ─── Digestion ───────────────────────────────────────────
            'bloating' => [
                'icon' => '🎈',
                'category' => 'Digestion',
                'short_desc' => 'Abdominal fullness, tightness, or gas — often tied to eating speed, fiber changes, or food sensitivities.',
                'full_desc' => 'That "swollen belly" feeling after meals happens for many reasons: eating too fast and swallowing air, sudden jumps in fiber, not drinking enough water with fiber, mild intolerances (lactose, fructose, certain FODMAPs), gut bacteria imbalance, or too much sodium holding water.',
                'recommended' => [
                    'Take your time eating — 20 minutes or more per meal. Don\'t rush. Try not to laugh or talk loudly with your mouth full, because you swallow air that causes gas (utot).',
                    'When adding vegetables like mung beans (monggo) or sitaw (long beans), don\'t add too much at once — increase gradually over 2 weeks and drink plenty of water to soften the fiber in your gut.',
                    'Eat one glass of plain yogurt or Yakult every day — the "good bacteria" help balance your digestive system.',
                    'Drink salabat (ginger tea) or peppermint leaf tea after meals — both help release trapped gas in the stomach.',
                ],
                'when_to_ask' => 'Daily bloating with pain, bloody stools, or unintended weight change — dietitian can run a low-FODMAP elimination to identify triggers.'
            ],
            'constipation' => [
                'icon' => '🚽',
                'category' => 'Digestion',
                'short_desc' => 'Infrequent or hard stools — usually fixed with more fiber, water, and movement.',
                'full_desc' => 'Going less than 3 times per week, or passing hard/lumpy stools, is your gut asking for 3 things: fiber to bulk the stool, water to soften it, and movement to push it through. Common mistakes: not enough fruits/veggies/grains, relying on processed foods, ignoring the "urge to go," dehydration, and low magnesium (which naturally draws water into the colon).',
                'recommended' => [
                    'Eat more fruits and vegetables: mango, saging na saba (cooking banana), pineapple, tomatoes, okra, sitaw (long beans), and kangkong. One large mango in the morning is often enough to get things moving.',
                    'Drink warm water with calamansi right after waking up — often, this triggers a bowel movement within 30 minutes.',
                    'Try lugaw (rice porridge) with lots of vegetables or champorado (chocolate rice porridge) with black rice — soft foods that don\'t get stuck in the intestines.',
                    'After every meal, take a 10–15 minute walk — body movement also gets your gut moving!',
                ],
                'when_to_ask' => 'If it lasts over 3 weeks despite lifestyle fixes, or is paired with pain/vomiting — dietitian can build a step-by-step fiber + hydration plan.'
            ],
            'nausea' => [
                'icon' => '🤢',
                'category' => 'Digestion',
                'short_desc' => 'Uneasy stomach or urge to vomit — can be soothed by ginger, small meals, and hydration strategies.',
                'full_desc' => 'That "sick to your stomach" feeling has many triggers, but diet and meal timing matter a lot. It often gets worse on an empty stomach (bile buildup), after large greasy meals, or when dehydrated. Blood sugar dips, food sensitivities, and low B6 (especially in pregnancy) also cause it. Even strong food smells can trigger waves of nausea in sensitive people.',
                'recommended' => [
                    'Don\'t let your stomach stay empty for too long — eat LITTLE but OFTEN (for example: 6 small meals a day). Don\'t force big meals all at once.',
                    'Chew on raw ginger or candied ginger, or drink hot salabat (ginger tea) — this definitely reduces the queasy feeling. Many studies have proven this.',
                    'Drink water with a little salt and sugar (like oral rehydration salt) in small sips — don\'t drink straight from the glass because it can make you dizzier.',
                    'Avoid greasy foods like crispy pata or fried pork when you feel nauseous — they just make it worse. Try sabaw (soup), lugaw (porridge), or bananas instead.',
                ],
                'when_to_ask' => 'If nausea lasts more than 3 days, or you can\'t keep any food/water down — talk to your dietitian immediately for safe hydration and meal ideas.'
            ],

            // ─── Skin & Hair ─────────────────────────────────────────
            'hair loss' => [
                'icon' => '💇',
                'category' => 'Skin & Hair',
                'short_desc' => 'More-than-usual shedding or thinning — linked to low iron, biotin, zinc, and protein intake.',
                'full_desc' => 'Losing 50–100 hairs a day is normal. If you notice clumps, a widening part, or thinner ponytail, look at nutrition first. Hair is made of protein (keratin), so low protein intake halts hair growth. Low iron (ferritin) is the #1 nutritional cause of telogen effluvium (temporary heavy shedding). Zinc, biotin, vitamin D, and essential fatty acids also support follicle health.',
                'recommended' => [
                    'Make sure every meal has protein: one whole egg, one can of sardines, three pieces of tuyo (dried fish), one chicken thigh, or one glass of milk. Hair is made of protein — if you don\'t eat enough, hair growth stops.',
                    'Include chicken liver, dilis (anchovies), or red meat once a week for the iron hair needs. Pair with tomato so your body absorbs it faster.',
                    'Eat peanuts, squash seeds, or eggs every day for zinc and biotin — these are the "vitamins for skin and hair".',
                    'Don\'t forget green leafy veggies: kangkong, talbos ng kamote (sweet potato leaves), and malunggay (moringa). Their folate helps quickly produce new hair cells.',
                ],
                'when_to_ask' => 'If heavy shedding lasts 2+ months or the scalp is visible — your dietitian can test for iron/zinc and design a hair-recovery eating plan.'
            ],
            'acne' => [
                'icon' => '🧴',
                'category' => 'Skin & Hair',
                'short_desc' => 'Breakouts often worsened by high-sugar diets, dairy sensitivities, and low antioxidant intake.',
                'full_desc' => 'Hormones are a big acne driver, but nutrition can dial inflammation and oil production up or down. High-glycemic foods (white bread, pastries, sugary drinks) spike insulin, which ramps up oil-making hormones. Dairy (especially skim milk and whey protein) can trigger breakouts in 10–30% of people. Low zinc, vitamin A, and omega-3s leave skin less able to fight bacteria and inflammation.',
                'recommended' => [
                    'Try going 2 weeks without softdrinks, candy, and ice cream — many people see their face clear up just by cutting back on sugar.',
                    'Eat lots of colorful fruits and vegetables: mango, squash, tomatoes, malunggay (moringa), and papaya. Their vitamin A naturally smooths skin.',
                    'If you take whey protein (milk powder), stop for 3 weeks — for some people, that\'s what triggers acne. Switch to gata (coconut milk) instead.',
                    'Snack on peanuts, squash seeds, or dilis (anchovies) — their zinc kills bacteria on skin and heals acne wounds.',
                ],
                'when_to_ask' => 'Cystic or persistent acne — a dietitian can help you do a well-designed food-elimination + reintroduction test.'
            ],
            'dry skin' => [
                'icon' => '🏜️',
                'category' => 'Skin & Hair',
                'short_desc' => 'Tight, flaky, or itchy skin — improved with hydration, omega-3 fats, and vitamin A/E.',
                'full_desc' => 'Moisturizer helps the outside, but dry skin starts from inside. Essential fatty acids (omega-3 and omega-6) build your skin\'s natural oily barrier that locks moisture in; if you\'re low, water escapes and skin gets flaky. Dehydration, low vitamins A, C, E, and not eating enough total dietary fat all contribute. Even the heating/cooling season can magnify these effects.',
                'recommended' => [
                    'Drink plenty of water — minimum 8 glasses. Dry skin starts from a dry body inside.',
                    'Cook using gata (coconut milk), coconut oil, or a little fish oil — these fats act as "body lotion from the inside" that keeps skin from getting rough.',
                    'Eat bananas, mangoes, and carrots (or squash) every day — their vitamins A and C improve skin cells.',
                    'A handful of peanuts or one glass of milk before bed — their vitamin E and fats soften skin overnight.',
                ],
                'when_to_ask' => 'If dry skin cracks, bleeds, or is itchy for weeks — dietitian can design a "skin-beauty menu" of local Filipino dishes.'
            ],

            // ─── Weight & Appetite ───────────────────────────────────
            'weight loss' => [
                'icon' => '⚖️',
                'category' => 'Weight',
                'short_desc' => 'Unintentional weight loss — needs immediate review of protein/calorie intake and gut health.',
                'full_desc' => 'Losing weight without trying is never a "good" thing. It usually means you\'re not eating enough calories or protein (busy life, depression, appetite loss), or your body isn\'t absorbing nutrients properly (gut issues). Muscle breaks down first — this weakens immunity, energy, and recovery. Your dietitian can identify the gap and build a safe, nutrient-dense eating plan to return to a healthy weight.',
                'recommended' => [
                    'Make every meal "nutrient-dense": for example, have lugaw (rice porridge) with egg + milk for breakfast (more than just plain rice). Add a little oil or gata (coconut milk) to dishes for extra calories.',
                    'Have 2–3 snacks between main meals: tuyo (dried fish) + rice, bananacue, taho (soybean pudding), or banana + peanuts — eat often and enough.',
                    'Drink milk (powdered or fresh) 2 times a day — it\'s easy to drink and packed with protein and calories.',
                    'Choose the foods you really like — you\'ll eat more when the food tastes good. Add a little sugar or soy sauce to taste.',
                ],
                'when_to_ask' => 'If you lost 2+ kg in a month without trying, or you can\'t finish meals — your dietitian can help you gain weight safely and gently.'
            ],
            'weight gain' => [
                'icon' => '⚖️',
                'category' => 'Weight',
                'short_desc' => 'Unintended or unwanted weight gain — often tied to portion sizes, sugar, liquid calories, and timing.',
                'full_desc' => 'Weight changes can happen for many reasons, but common nutritional causes include: mindless "grazing" at home, liquid calories you don\'t notice (soda, fancy coffee drinks, alcohol), oversized restaurant portions, snacking during screen time, or eating ultra-processed foods that are engineered to be overeaten. Sleep loss and high stress (cortisol hormone) also push hunger hormones higher.',
                'recommended' => [
                    'Use a smaller plate (only 9 inches) — you\'ll notice you feel full earlier with a small plate compared to a giant one.',
                    'Stop sugary drinks for now: 1 glass of softdrink = 10 teaspoons of sugar! Drink water, calamansi juice (with little sugar), or iced tea instead.',
                    'Make sure every meal has a protein dish (fish, egg, chicken, tokwa/tofu) — you won\'t get hungry quickly, so you don\'t end up eating twice in one sitting.',
                    'Eat while sitting at the dining table, with no TV or cellphone. When you focus on the show, you don\'t realize you\'ve already eaten too much!',
                ],
                'when_to_ask' => 'Steady gain of 1–2+ lbs per month without trying — dietitian can help build a realistic, sustainable plan (no crash diets!).'
            ],
            'appetite loss' => [
                'icon' => '🍽️',
                'category' => 'Weight',
                'short_desc' => 'Lack of hunger or desire to eat — can cause nutrient gaps and weight loss if not addressed.',
                'full_desc' => 'When you consistently "forget to eat" or nothing sounds good, it leads to steady nutrient loss over time. Stress, medications, depression, aging, and zinc deficiency can all shut down the hunger signal. Low protein intake and skipping meals create a feedback loop: eat less → lose hunger → eat even less. Small, frequent, nutrient-dense meals usually work better than forcing big plates.',
                'recommended' => [
                    'Don\'t force one big meal — eat 5–6 SMALL times a day. For example: lugaw for breakfast, taho for snack, small sinigang for lunch, banana + peanuts for afternoon, a little soup for evening.',
                    'Cook your favorite food! When the food tastes good, you\'re more likely to have an appetite. For example, kare-kare, adobo, or sinigang na baboy are all very appetizing.',
                    'Add a little vinegar, soy sauce, patis (fish sauce), or calamansi — sour and salty flavors wake up the taste of food.',
                    'One glass of milk + an egg before bed is an easy way to get protein even if you had no appetite all day.',
                ],
                'when_to_ask' => 'If you skip 2+ meals a day for over a week, or you\'re losing weight — dietitian can build a "small & often" meal plan that\'s easy to follow.'
            ],

            // ─── Heart & Blood Pressure ──────────────────────────────
            'high blood pressure' => [
                'icon' => '❤️',
                'category' => 'Cardiovascular',
                'short_desc' => 'Elevated BP — very responsive to DASH-style eating: less sodium, more potassium and magnesium.',
                'full_desc' => 'Blood pressure goes up when your blood vessels hold on to extra fluid (from too much sodium) or lack flexibility (from low potassium/magnesium, low fiber). The DASH (Dietary Approaches to Stop Hypertension) eating plan is proven to lower BP by 5–8 mmHg (similar to some medications) in 2 weeks by prioritizing produce, whole grains, lean protein, and limiting salt and added sugars.',
                'recommended' => [
                    'Reduce toyo (soy sauce), patis (fish sauce), bagoong (fermented paste), table salt, and canned goods — all of these have salt that raises blood pressure. Try garlic, onions, and calamansi to flavor food without salt.',
                    'Eat plenty of fruits and vegetables: bananas, mangoes, tomatoes, kangkong (water spinach), sitaw (long beans), and eggplant. The potassium in these is your body\'s "partner" to flush out excess salt.',
                    'Instead of daily fried pork, try fried fish (bangus, bisugo) or boiled chicken. Fish oil helps lower blood pressure.',
                    'When eating rice, try mixing in some corn or brown rice occasionally. The fiber in whole grains helps keep blood vessels soft and flexible.',
                ],
                'when_to_ask' => 'If BP stays above 140/90 for multiple readings — work with your dietitian to customize a low-sodium Filipino meal plan that still tastes good.'
            ],

            // ─── Immune ──────────────────────────────────────────────
            'frequent illness' => [
                'icon' => '🤒',
                'category' => 'Immune',
                'short_desc' => 'Catching every cold or slow to heal — a sign to boost vitamin C, D, zinc, and protein.',
                'full_desc' => 'If you get sick more often than the people around you, or a cold that should last 3 days stretches into 2 weeks, nutrition is a fast lever to pull. Your immune cells are built from protein and need vitamins C, D, A, zinc, and selenium to function. Diets high in processed sugar and low in produce literally slow immune cell activity for hours.',
                'recommended' => [
                    'Slice up 1 large dalandan (orange) or calamansi in the morning and afternoon — their Vitamin C is your first defense against coughs and colds. Guava works too!',
                    'Make sure you have protein every day (egg, fish, chicken, tokwa/tofu) — the body\'s "soldier" cells that fight sickness are made from protein.',
                    'Eat peanuts, dilis (anchovies), or eggs for zinc — this mineral speeds up healing of wounds and colds.',
                    'Don\'t forget 15 minutes of morning sun for Vitamin D, and eat green leafy vegetables for Vitamin A — two vitamins that strengthen your whole body\'s defenses.',
                ],
                'when_to_ask' => 'If you catch more than 5 colds per year, or infections take longer than 10 days to heal — dietitian can strengthen your immune system with simple local foods.'
            ],

            // ─── Canonical wellness classes (v1.5.0) ─────────────────
            'dizziness' => [
                'icon' => '🌀',
                'category' => 'Circulation',
                'short_desc' => 'Lightheadedness, spinning, or feeling faint — often tied to hydration, skipped meals, and low sodium.',
                'full_desc' => 'Feeling dizzy or like the room is spinning usually means your brain briefly got less blood flow. Common nutrition-related causes: dehydration, a sudden drop in blood sugar from skipped meals, standing up too fast (orthostatic), and low sodium or iron status. Food timing and fluids fix most everyday cases.',
                'recommended' => [
                    'Drink one glass of water right after waking and sip throughout the day — dehydration is the #1 everyday cause of dizziness.',
                    'Never skip breakfast; go no longer than 5 hours without eating so blood sugar stays steady (try rice + egg + tomato).',
                    'Stand up slowly: sit at the edge of the bed for a few seconds before standing.',
                    'If you sweat a lot or work in the heat, replace salt with sabaw (soup) or a pinch of salt in water plus a banana.',
                ],
                'when_to_ask' => 'Dizziness with fainting, chest pain, or one-sided weakness is urgent — seek care now. Persistent dizziness should be reviewed by your dietitian and doctor.'
            ],
            'fever' => [
                'icon' => '🌡️',
                'category' => 'Immune',
                'short_desc' => 'Raised body temperature — focus on fluids and easy-to-digest nourishment while you recover.',
                'full_desc' => 'Fever is your body fighting an infection. The goal is not to "starve a fever" — it is to keep fluids and energy coming in so your immune system has fuel. Dehydration and low protein intake slow recovery. This insight is educational; fever can signal infection that needs medical care.',
                'recommended' => [
                    'Sip water, sabaw (soup), or oral rehydration fluid often — small sips every few minutes if solid food is hard.',
                    'Eat soft, easy foods: lugaw (rice porridge), arroz caldo with ginger, or bread with egg when you feel ready.',
                    'Skip heavy fried and very oily meals until the fever breaks.',
                    'Rest and keep the room cool; log how long the fever lasts.',
                ],
                'when_to_ask' => 'Fever above 38.5°C lasting more than 2–3 days, or with stiff neck, rash, or confusion — see a doctor right away.'
            ],
            'cough' => [
                'icon' => '🫁',
                'category' => 'Respiratory',
                'short_desc' => 'Ongoing cough — hydration, warm fluids, and vitamin C support comfort and recovery.',
                'full_desc' => 'A cough is your airway clearing irritants or infection. Keeping fluids up thins mucus; warm drinks and vitamin C foods soothe the throat, while smoke and very cold drinks make it worse. Persistent cough needs medical review to rule out infection or asthma.',
                'recommended' => [
                    'Drink warm water or salabat (ginger tea) with calamansi and honey through the day to soothe the throat.',
                    'Eat vitamin C–rich foods: dalandan, guava, calamansi, or mango.',
                    'Avoid smoke, very cold drinks, and iced desserts while coughing.',
                    'Rest your voice and sleep propped up slightly if night cough is worse.',
                ],
                'when_to_ask' => 'Cough lasting more than 2–3 weeks, coughing blood, or with breathing difficulty — see a doctor; your dietitian can then support recovery nutrition.'
            ],
            'sore throat' => [
                'icon' => '🗣️',
                'category' => 'Respiratory',
                'short_desc' => 'Painful or scratchy throat — warm soothing fluids and soft foods help until it heals.',
                'full_desc' => 'A sore throat is often viral and settles in a few days. Warm fluids and soft, non-acidic foods keep you hydrated and comfortable. Very spicy, sour, or crunchy foods can scrape and irritate it further.',
                'recommended' => [
                    'Drink warm salabat (ginger tea) or warm water with honey and calamansi in small sips.',
                    'Choose soft foods: lugaw, arroz caldo, mashed saging na saba, or soft eggs.',
                    'Avoid very spicy, very sour (plain calamansi shots), and crunchy fried food for now.',
                    'Gargle warm salt water to ease pain.',
                ],
                'when_to_ask' => 'Sore throat with high fever, drooling, or trouble swallowing/breathing — seek care promptly.'
            ],
            'chest pain' => [
                'icon' => '🫀',
                'category' => 'Cardiovascular',
                'short_desc' => 'Pain or pressure in the chest — this needs prompt medical review, not diet changes alone.',
                'full_desc' => 'Chest pain can come from muscle strain, acid reflux, or the heart. Because heart-related causes are dangerous, this insight always recommends prompt medical evaluation. Nutrition matters afterward for heart-healthy eating, but it is not a first-line fix for acute chest pain.',
                'recommended' => [
                    'Do not wait it out — have this checked by a doctor as soon as possible.',
                    'While waiting, avoid heavy meals and lying flat right after eating if it feels like reflux.',
                    'Note what triggers it (exertion, meals, position) to help your clinician.',
                    'After medical clearance, a heart-healthy diet (less salt/fried food, more fish and vegetables) can help.',
                ],
                'when_to_ask' => 'Chest pain that is crushing, radiates to the arm/jaw, or comes with sweating or shortness of breath is an emergency — call for help immediately.'
            ],
            'shortness of breath' => [
                'icon' => '🫁',
                'category' => 'Respiratory',
                'short_desc' => 'Difficulty breathing — a red-flag symptom that needs prompt medical attention.',
                'full_desc' => 'Breathlessness can come from infection, asthma, anemia, or heart issues. It should be medically evaluated. Nutrition support (iron status, overall fueling) is relevant for chronic patterns but does not replace urgent care.',
                'recommended' => [
                    'Sit upright and stay calm; use any prescribed inhaler as directed.',
                    'Have it evaluated promptly — do not rely on diet changes to resolve it.',
                    'If it is linked to anemia, iron-rich foods (liver, dilis, monggo) with vitamin C help once medically cleared.',
                    'Avoid smoke and strong fumes.',
                ],
                'when_to_ask' => 'Severe breathlessness, chest pain, or bluish lips is an emergency — seek help immediately.'
            ],
            'palpitations' => [
                'icon' => '💓',
                'category' => 'Cardiovascular',
                'short_desc' => 'A racing, pounding, or fluttering heartbeat — reduce stimulants and check with a clinician.',
                'full_desc' => 'Palpitations can be triggered by too much caffeine, skipped meals (low blood sugar), dehydration, stress, or thyroid/electrolyte issues. Cutting stimulants and stabilizing meals often helps, but new or persistent palpitations need medical review.',
                'recommended' => [
                    'Limit coffee, energy drinks, and strong tea — cap at one morning cup or switch to salabat.',
                    'Eat regular meals so blood sugar does not dip (which triggers adrenaline and a racing heart).',
                    'Drink enough water, and include potassium foods (banana, kamote) if cleared by your clinician.',
                    'Log episodes: timing, caffeine, stress, and activity.',
                ],
                'when_to_ask' => 'Palpitations with fainting, chest pain, or breathlessness — seek care promptly; discuss persistent episodes with your clinician.'
            ],
            'diarrhea' => [
                'icon' => '🚰',
                'category' => 'Digestion',
                'short_desc' => 'Frequent loose stools — priority #1 is replacing fluids and electrolytes.',
                'full_desc' => 'Diarrhea drains water and salts fast, so dehydration is the main risk. Bland, low-fiber foods rest the gut while oral rehydration replaces lost fluids and electrolytes. Very fatty, spicy, or dairy-heavy foods can prolong it.',
                'recommended' => [
                    'Drink oral rehydration solution (or water with a little salt and sugar) in small frequent sips.',
                    'Eat bland soft foods: lugaw, plain rice, boiled saging na saba, or sabaw ng manok without oil.',
                    'Avoid very fatty, spicy, and high-fiber foods, and go easy on milk until it settles.',
                    'Wash hands and food well to avoid spreading it.',
                ],
                'when_to_ask' => 'Diarrhea lasting more than 2 days, with blood, high fever, or signs of dehydration (dizzy, very little urine) — seek care promptly.'
            ],
            'back pain' => [
                'icon' => '🩻',
                'category' => 'Musculoskeletal',
                'short_desc' => 'Lower or upper back ache — posture, movement, and anti-inflammatory eating help.',
                'full_desc' => 'Most everyday back pain is muscular or postural. Weight, inactivity, and inflammation play a role; nutrition supports recovery through protein, vitamin D, and anti-inflammatory foods, alongside movement and good posture.',
                'recommended' => [
                    'Add protein at every meal (egg, fish, chicken, tokwa) to support muscle repair.',
                    'Include anti-inflammatory foods: sardines/bangus, ginger (salabat), and colorful vegetables.',
                    'Take short walking or stretching breaks if you sit long hours.',
                    'Watch heavy lifting; bend the knees, not the back.',
                ],
                'when_to_ask' => 'Back pain with numbness, weakness in the legs, or after an injury — see a doctor.'
            ],
            'neck pain' => [
                'icon' => '🦴',
                'category' => 'Musculoskeletal',
                'short_desc' => 'Neck stiffness or ache — often posture and screen-related; hydration and movement help.',
                'full_desc' => 'Neck pain commonly comes from sustained poor posture (phone/desk), muscle tension, or strain. Movement, posture fixes, hydration, and adequate protein/magnesium support recovery.',
                'recommended' => [
                    'Set the screen at eye level and take a stretch break every 30 minutes.',
                    'Drink enough water — dehydration tightens muscles.',
                    'Include magnesium foods: peanuts, squash seeds, and leafy greens (kangkong, malunggay).',
                    'Sleep with a supportive pillow height.',
                ],
                'when_to_ask' => 'Neck pain with arm numbness/weakness, fever, or after trauma — see a doctor.'
            ],
            'leg pain' => [
                'icon' => '🦵',
                'category' => 'Musculoskeletal',
                'short_desc' => 'Leg ache, heaviness, or cramping — review hydration, electrolytes, and circulation.',
                'full_desc' => 'Leg discomfort ranges from muscle cramps and post-exercise soreness to circulation issues. Hydration, potassium/magnesium, and gentle movement help muscular causes, but swelling, redness, or one-sided pain can signal a clot and need urgent review.',
                'recommended' => [
                    'Hydrate well and include potassium foods: banana, kamote, saba.',
                    'Stretch calves gently morning and night for cramp-prone legs.',
                    'Elevate and move your legs regularly if you stand or sit for long periods.',
                    'Eat protein at meals to support muscle recovery.',
                ],
                'when_to_ask' => 'One-sided swelling, warmth, or redness in the leg, or pain after long travel — seek care promptly to rule out a clot.'
            ],
            'shoulder pain' => [
                'icon' => '💪',
                'category' => 'Musculoskeletal',
                'short_desc' => 'Shoulder ache or stiffness — often strain or posture; protein and anti-inflammatory foods help.',
                'full_desc' => 'Shoulder pain is frequently muscular or from repetitive use and posture. Recovery nutrition (protein, omega-3s, vitamin D) supports tissue repair, while rest and targeted movement reduce strain.',
                'recommended' => [
                    'Rest the shoulder from the aggravating movement and use gentle range-of-motion stretches.',
                    'Eat protein each meal and oily fish 2–3× a week (sardines, bangus) for omega-3s.',
                    'Adjust your chair/desk so the shoulder is not shrugged or rounded.',
                    'Use warm compress for stiffness, cold for a fresh strain.',
                ],
                'when_to_ask' => 'Shoulder pain after a fall, with deformity, or with arm weakness — see a doctor.'
            ],
            'tooth pain' => [
                'icon' => '🦷',
                'category' => 'Oral',
                'short_desc' => 'Toothache or gum pain — needs a dentist; diet tips only reduce irritation meanwhile.',
                'full_desc' => 'Tooth pain almost always needs dental care (cavity, abscess, or gum disease). Nutrition supports oral health long-term (calcium, vitamin D, less sugar) but cannot fix an active tooth problem. Avoid very hot, cold, or sweet foods that trigger pain.',
                'recommended' => [
                    'See a dentist promptly — painkillers and diet do not resolve the cause.',
                    'Avoid very sweet, very hot, or very cold foods that trigger the pain.',
                    'Rinse with warm salt water and keep the area clean.',
                    'For prevention: cut sugary drinks/snacks and include calcium (milk, dilis) and vitamin D.',
                ],
                'when_to_ask' => 'Tooth pain with facial swelling, fever, or trouble swallowing — seek dental/medical care urgently.'
            ],
            'muscle pain' => [
                'icon' => '💪',
                'category' => 'Musculoskeletal',
                'short_desc' => 'Aching muscles or body pain — protein, magnesium, and anti-inflammatory foods aid recovery.',
                'full_desc' => 'Generalized muscle aches can follow exertion or reflect vitamin D, magnesium, or protein shortfalls, and sometimes infection. Protein rebuilds muscle, magnesium eases tension, and omega-3s calm inflammation.',
                'recommended' => [
                    'Protein at every meal to rebuild muscle (egg, fish, chicken, tokwa).',
                    'Magnesium foods: peanuts, squash seeds, kangkong, malunggay.',
                    'Oily fish (sardines, bangus) 2–3× a week and colorful produce for anti-inflammatory support.',
                    'Hydrate and rest; gentle movement beats total inactivity.',
                ],
                'when_to_ask' => 'Severe muscle pain with dark urine after exertion, or aches with fever lasting days — seek care.'
            ],
            'pain' => [
                'icon' => '⚠️',
                'category' => 'General',
                'short_desc' => 'Reported pain in a specific area — note the location and pattern for your dietitian.',
                'full_desc' => 'When pain is mentioned without a clear location, it is worth logging where and when it happens. Nutrition can influence inflammatory and muscular pain, but persistent or severe pain needs medical review.',
                'recommended' => [
                    'Note the location, timing, and what makes it better or worse.',
                    'Stay hydrated and keep regular balanced meals.',
                    'Include anti-inflammatory foods (fish, ginger, colorful vegetables).',
                    'Share the pattern with your dietitian at your next session.',
                ],
                'when_to_ask' => 'Severe, worsening, or unexplained pain — speak with your clinician promptly.'
            ],
        ];

        // Fill in any placeholders with reasonable defaults on first runtime
        $defaults = [
            'icon' => '💡',
            'category' => 'General Wellness',
            'short_desc' => 'A wellness pattern flagged by our AI nutrition insights.',
            'full_desc' => 'Our AI wellness system noticed this pattern appearing in your diary or chat messages. While not a diagnosis, it often connects to diet and lifestyle patterns your dietitian can help you explore. Nutritional science shows that consistent, small dietary changes can often improve how you feel within 2–4 weeks.',
            'recommended' => [
                'Start by reviewing your recent meals with your dietitian — patterns are easier to spot with a trained eye.',
                'Track symptoms and meals together for 5–7 days (even a simple notes app works).',
                'Prioritize 3 balanced meals daily with protein, colorful produce, and whole-food carbs.',
                'Hydrate consistently throughout the day — 8–10 glasses of water minimum.',
            ],
            'when_to_ask' => 'If this symptom has lasted more than 2 weeks, or is getting worse — share it with your dietitian at your next session. Together you can rule out underlying nutrient gaps or food triggers.'
        ];

        foreach ($kb as $key => &$row) {
            foreach ($defaults as $dfield => $dval) {
                if (!isset($row[$dfield]) || (is_string($row[$dfield]) && $row[$dfield] === 'placeholder')) {
                    $row[$dfield] = $dval;
                }
            }
        }
        unset($row); // cleanup ref

        return $kb;
    }

    /**
     * Look up a symptom keyword from the KB. Falls back to a generic entry.
     * Always returns an array with all 6 fields populated.
     */
    function nd_lookup_symptom($symptom_keyword) {
        $kb = nd_get_symptom_kb();
        $kw = strtolower(trim($symptom_keyword));

        if (isset($kb[$kw])) return $kb[$kw];

        // Fuzzy match: contains
        foreach ($kb as $k => $row) {
            if (strpos($kw, $k) !== false || strpos($k, $kw) !== false) {
                return $row;
            }
        }

        // Plural/singular (simple English rules)
        if (str_ends_with($kw, 's')) {
            $singular = substr($kw, 0, -1);
            if (isset($kb[$singular])) return $kb[$singular];
        } else {
            if (isset($kb[$kw . 's'])) return $kb[$kw . 's'];
        }

        // Generic fallback
        return [
            'icon' => '✨',
            'category' => 'Wellness Insight',
            'short_desc' => ucfirst($symptom_keyword) . ' — a pattern flagged for nutritional review.',
            'full_desc' => 'Our AI wellness system noticed mention of "' . htmlspecialchars(ucfirst($symptom_keyword)) . '" in recent records. This is an educational insight — many symptoms respond well to dietary adjustments. Your dietitian can help you dig deeper and connect it to food, sleep, hydration, and lifestyle patterns.',
            'recommended' => [
                'Note when this symptom occurs (time of day, before/after meals, with certain foods).',
                'Share a 5-day food + symptom log with your dietitian.',
                'Keep water intake high and meals regular while you investigate.',
                'Avoid large meals, added sugars, or caffeine if they seem to make it worse.',
            ],
            'when_to_ask' => 'If it persists for more than 2 weeks, or is paired with fever, pain, or sudden changes — bring it to your dietitian\'s attention at the next session so you can review together and rule out any need for further testing.'
        ];
    }
}
