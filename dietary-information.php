<?php
require_once __DIR__ . '/session_bootstrap.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

// Get user data from session
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['user_role'];

// Generate initials for avatar
function getInitials($name)
{
    $names = explode(' ', $name);
    $initials = '';
    foreach ($names as $n) {
        if (!empty($n)) {
            $initials .= strtoupper($n[0]);
        }
    }
    return substr($initials, 0, 2);
}

$user_initials = getInitials($user_name);

// Role-based navigation links
require_once 'navigation.php';
$nav_links = getNavigationLinks($user_role, 'dietary-information.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>NutriDeq - Nutrient Calculator</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&family=Playfair+Display:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/dashboard-premium.css">
    <!-- Platform Specific Styles -->
    <link rel="stylesheet" href="css/desktop-style.css" media="all and (min-width: 1025px)">
    <link rel="stylesheet" href="css/mobile-style.css" media="all and (max-width: 1024px)">
    <style>
        /* ═══════════ CSS VARIABLES FOR CONSISTENCY ═══════════ */
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: rgba(5, 150, 105, 0.08);
            --secondary: #3b82f6;
            --gradient: linear-gradient(135deg, #059669 0%, #3b82f6 100%);
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.2);
            --glass-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --bg-surface: #ffffff;
            --bg-main: #f8fafc;
        }

        .dash-premium {
            background: transparent !important;
            min-height: var(--app-height-dvh);
            position: relative;
        }

        .nutrient-terminal {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            margin-top: 20px;
            position: relative;
            z-index: 10;
            height: calc(var(--app-height-dvh) - 180px);
        }

        .bento-nutrient-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 16px;
            max-height: calc(100vh - 480px);
            overflow-y: auto;
            padding-right: 8px;
        }

        .nutrient-bento-card {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 16px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .nutrient-bento-card:hover {
            transform: translateY(-4px);
            background: rgba(255, 255, 255, 0.9);
            border-color: var(--primary);
        }

        .nutrient-bento-card.active {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .nutrient-icon {
            font-size: 1.4rem;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .nutrient-name {
            font-weight: 800;
            color: var(--text-primary);
            font-size: 0.95rem;
            margin-bottom: 4px;
            font-family: 'Outfit', sans-serif;
        }

        .nutrient-benefit {
            font-size: 0.72rem;
            color: #64748b;
            line-height: 1.35;
            margin: 4px 0 12px;
            display: block;
            font-weight: 500;
            min-height: 38px;
        }

        .val-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 10px 14px;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            color: #1e293b;
            font-size: 1.1rem;
            outline: none;
            transition: all 0.3s;
        }

        .val-input:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
        }

        .terminal-panel {
            background: var(--glass-bg) !important;
            backdrop-filter: blur(25px);
            border: 1px solid var(--glass-border) !important;
            border-radius: 28px;
            padding: 24px;
            box-shadow: var(--glass-shadow);
            display: flex;
            flex-direction: column;
        }

        .floating-pill-nav {
            background: rgba(0, 0, 0, 0.04);
            padding: 4px;
            border-radius: 50px;
            display: inline-flex;
            gap: 6px;
            margin-bottom: 20px;
        }

        .pill-item {
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 0.75rem;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s;
            color: #64748b;
        }

        .pill-item.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 8px 16px rgba(5, 150, 105, 0.2);
            transform: scale(1.05);
        }

        .diagnostic-progress {
            height: 10px;
            background: rgba(0, 0, 0, 0.04);
            border-radius: 20px;
            overflow: hidden;
            margin-top: 12px;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #34d399, var(--primary));
            background-size: 200% 100%;
            animation: gradient-move 3s infinite linear;
            border-radius: 20px;
            transition: width 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes gradient-move {
            0% {
                background-position: 100% 0%;
            }

            100% {
                background-position: -100% 0%;
            }
        }

        /* Custom Clinical Scrollbar */
        .bento-nutrient-grid::-webkit-scrollbar {
            width: 4px;
        }

        .bento-nutrient-grid::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
        }

        .bento-nutrient-grid::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.2);
            border-radius: 10px;
        }

        .bento-nutrient-grid::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.4);
        }

        .result-group {
            animation: slideIn 0.5s cubic-bezier(0.23, 1, 0.32, 1) forwards;
            opacity: 0;
            transform: translateX(-20px);
        }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @media (max-width: 1200px) {
            .nutrient-terminal {
                grid-template-columns: 1fr;
            }
        }
    </style>
    </style>
    <link rel="stylesheet" href="css/premium-revamp.css">
</head>

<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content dash-premium" style="padding: 20px 32px;">
            <!-- Modern Mesh Background Elements -->
            <div class="mesh-gradient-container dashboard-mesh">
                <div class="mesh-blob blob-1"></div>
                <div class="mesh-blob blob-2"></div>
                <div class="mesh-blob blob-3"></div>
            </div>

            <!-- Nutri-Glass Noise Texture -->
            <div class="glass-noise"></div>

            <!-- Spotlight & Custom Cursor -->
            <div class="spotlight" id="spotlight"></div>
            <div id="organicCursor"></div>
            <div class="glow-aura" id="cursorAura"></div>

            <div class="page-container" style="position: relative; z-index: 10;">
                <div class="header" style="background: transparent; backdrop-filter: none; box-shadow: none; border: none; padding: 0; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start;">
                    <div class="page-title">
                        <h1 style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2.1rem; letter-spacing: -0.02em; color: #1e293b; margin: 0;">Nutrient Calculator</h1>
                        <p style="font-weight: 600; color: #64748b; font-size: 0.95rem; margin-top: 4px;">Convert nutrient weights into daily percentages</p>
                    </div>
                </div>

                <!-- Nutrient Terminal Hub -->
                <div class="nutrient-terminal">
                    <!-- Left: Input Engine -->
                    <div class="terminal-panel stagger d-1">
                        <div
                            style="border:none; padding:0; margin-bottom:20px; display: flex; align-items: center; justify-content: space-between;">
                            <h2
                                style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.25rem; margin: 0; color: #1e293b;">
                                <i class="fas fa-keyboard" style="color:#10b981; margin-right:12px;"></i> Enter Nutrient
                                Values
                            </h2>
                        </div>

                        <!-- Premium Category Navigation -->
                        <div class="floating-pill-nav">
                            <div class="pill-item active" onclick="switchNutrientTab('group-essentials', this)">
                                <i class="fas fa-atom"></i> Minerals
                            </div>
                            <div class="pill-item" onclick="switchNutrientTab('group-vitamins', this)">
                                <i class="fas fa-dna"></i> Vitamins
                            </div>
                        </div>

                        <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 24px; font-weight: 500;">Select a
                            category and enter the weight of each nutrient found on your food label.</p>

                        <!-- Minerals Tab -->
                        <div id="group-essentials" class="nutrient-tab-content">
                            <div class="bento-nutrient-grid">
                                <?php
                                $minerals = [
                                    ['id' => 'sodium', 'label' => 'Sodium', 'unit' => 'mg', 'icon' => 'fa-vial-circle-check', 'benefit' => 'Regulates fluid balance and nerve function.'],
                                    ['id' => 'potassium', 'label' => 'Potassium', 'unit' => 'mg', 'icon' => 'fa-vial', 'benefit' => 'Supports muscle contractions and heart rhythm.'],
                                    ['id' => 'dietary-fiber', 'label' => 'Fiber', 'unit' => 'g', 'icon' => 'fa-leaf', 'benefit' => 'Improves digestion and slows sugar absorption.'],
                                    ['id' => 'protein', 'label' => 'Protein', 'unit' => 'g', 'icon' => 'fa-dumbbell', 'benefit' => 'Essential for muscle repair and immune defense.'],
                                    ['id' => 'iodine', 'label' => 'Iodine', 'unit' => 'mg', 'icon' => 'fa-atom', 'benefit' => 'Crucial for thyroid health and metabolic rate.'],
                                    ['id' => 'magnesium', 'label' => 'Magnesium', 'unit' => 'mg', 'icon' => 'fa-bolt-lightning', 'benefit' => 'Energy production and muscle relaxation.'],
                                    ['id' => 'zinc', 'label' => 'Zinc', 'unit' => 'mg', 'icon' => 'fa-shield-halved', 'benefit' => 'Boosts immune system and helps wound healing.'],
                                    ['id' => 'selenium', 'label' => 'Selenium', 'unit' => 'μg', 'icon' => 'fa-flask', 'benefit' => 'Potent antioxidant for cell protection.'],
                                    ['id' => 'chloride', 'label' => 'Chloride', 'unit' => 'mg', 'icon' => 'fa-droplet', 'benefit' => 'Maintains proper blood volume and pH.'],
                                    ['id' => 'flouride', 'label' => 'Fluoride', 'unit' => 'mg', 'icon' => 'fa-tooth', 'benefit' => 'Strengthens tooth enamel and bone density.'],
                                    ['id' => 'phosphorus', 'label' => 'Phosphorus', 'unit' => 'mg', 'icon' => 'fa-lightbulb', 'benefit' => 'Vital for bone health and ATP energy storage.'],
                                    ['id' => 'calcium', 'label' => 'Calcium', 'unit' => 'mg', 'icon' => 'fa-bone', 'benefit' => 'Builds strong bones and supports blood clotting.'],
                                    ['id' => 'iron', 'label' => 'Iron', 'unit' => 'mg', 'icon' => 'fa-hammer', 'benefit' => 'Carries oxygen through the blood to cells.']
                                ];
                                foreach ($minerals as $m): ?>
                                    <div class="nutrient-bento-card nutrient-item" data-nutrient="<?= $m['id'] ?>">
                                        <div class="nutrient-icon"><i class="fas <?= $m['icon'] ?>"></i></div>
                                        <div class="nutrient-name"><?= $m['label'] ?> (<?= $m['unit'] ?>)</div>
                                        <span class="nutrient-benefit"><?= $m['benefit'] ?></span>
                                        <input type="number" class="val-input nutrient-input" placeholder="0"
                                            data-nutrient="<?= $m['id'] ?>" style="margin-top: 12px;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Vitamins Tab -->
                        <div id="group-vitamins" class="nutrient-tab-content" style="display:none;">
                            <div class="bento-nutrient-grid">
                                <?php
                                $vitamins = [
                                    ['id' => 'vitamin-a', 'label' => 'Vitamin A', 'unit' => 'μg', 'icon' => 'fa-eye', 'benefit' => 'Essential for vision, skin health, and immunity.'],
                                    ['id' => 'vitamin-c', 'label' => 'Vitamin C', 'unit' => 'mg', 'icon' => 'fa-lemon', 'benefit' => 'Supports collagen and protects cells from damage.'],
                                    ['id' => 'vitamin-d', 'label' => 'Vitamin D', 'unit' => 'μg', 'icon' => 'fa-sun', 'benefit' => 'Crucial for calcium absorption and bone health.'],
                                    ['id' => 'vitamin-e', 'label' => 'Vitamin E', 'unit' => 'mg', 'icon' => 'fa-shield', 'benefit' => 'A powerful antioxidant for cell membrane protection.'],
                                    ['id' => 'vitamin-k', 'label' => 'Vitamin K', 'unit' => 'μg', 'icon' => 'fa-bandage', 'benefit' => 'Key factor in blood clotting and bone metabolism.'],
                                    ['id' => 'thiamin', 'label' => 'Thiamin', 'unit' => 'mg', 'icon' => 'fa-bolt', 'benefit' => 'Helps convert food into energy for nerves.'],
                                    ['id' => 'riboflavin', 'label' => 'Riboflavin', 'unit' => 'mg', 'icon' => 'fa-dna', 'benefit' => 'Promotes healthy skin and eye function.'],
                                    ['id' => 'niacin', 'label' => 'Niacin', 'unit' => 'mg', 'icon' => 'fa-flask-vial', 'benefit' => 'Improves cholesterol and supports DNA repair.'],
                                    ['id' => 'vitamin-b6', 'label' => 'Vitamin B6', 'unit' => 'mg', 'icon' => 'fa-pills', 'benefit' => 'Important for brain development and mood.'],
                                    ['id' => 'folate', 'label' => 'Folate', 'unit' => 'μg', 'icon' => 'fa-seedling', 'benefit' => 'Essential for DNA synthesis and cell division.'],
                                    ['id' => 'vitamin-b12', 'label' => 'Vitamin B12', 'unit' => 'μg', 'icon' => 'fa-capsules', 'benefit' => 'Necessary for nerve tissue health and red blood cells.']
                                ];
                                foreach ($vitamins as $v): ?>
                                    <div class="nutrient-bento-card nutrient-item" data-nutrient="<?= $v['id'] ?>">
                                        <div class="nutrient-icon"><i class="fas <?= $v['icon'] ?>"></i></div>
                                        <div class="nutrient-name"><?= $v['label'] ?> (<?= $v['unit'] ?>)</div>
                                        <span class="nutrient-benefit"><?= $v['benefit'] ?></span>
                                        <input type="number" class="val-input nutrient-input" placeholder="0"
                                            data-nutrient="<?= $v['id'] ?>" style="margin-top: 12px;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div style="display: flex; gap: 12px; margin-top: 24px;">
                            <button class="btn-dash-action" id="calculateBtn"
                                style="background: #10b981; color: white; border: none; flex: 1; padding: 14px; border-radius: 16px; font-weight: 800; font-size: 1rem; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.2);">
                                <i class="fas fa-play" style="margin-right: 8px;"></i> Calculate Now
                            </button>
                            <button class="btn-dash-action" id="resetBtn"
                                style="padding: 14px; border-radius: 16px; font-weight: 800; border: 1.5px solid #e2e8f0; font-size: 1rem;">
                                <i class="fas fa-redo" style="margin-right: 8px;"></i> Reset
                            </button>
                        </div>
                    </div>

                    <!-- Right: Diagnostic Results -->
                    <div class="terminal-panel stagger d-2" style="height: fit-content;">
                        <div style="border:none; padding:0; margin-bottom:20px;">
                            <h2
                                style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.25rem; margin: 0; color: #1e293b;">
                                <i class="fas fa-chart-pie" style="color:#10b981; margin-right:12px;"></i> Your Results
                            </h2>
                        </div>

                        <div id="emptyState" style="text-align: center; padding: 70px 20px; color: #64748b;">
                            <i class="fas fa-satellite-dish fa-3x"
                                style="opacity: 0.15; margin-bottom: 20px; animation: pulse-glass 2s infinite;"></i>
                            <p style="font-weight: 600; font-size: 1rem;">Waiting for your entry...</p>
                        </div>

                        <div id="calculatedResults" style="display: none;">
                            <div id="resultsList"
                                style="display: flex; flex-direction: column; gap: 12px; max-height: calc(100vh - 400px); overflow-y: auto; padding-right: 8px;">
                                <!-- Populated via JS -->
                            </div>

                            <div id="clinicalInsights"
                                style="margin-top: 24px; padding: 20px; background: rgba(255,255,255,0.6); border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.1); display: none;">
                                <h4
                                    style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1rem; color: var(--primary); margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-microscope"></i> Clinical Insights
                                </h4>
                                <div id="insightContent" style="display: flex; flex-direction: column; gap: 10px;">
                                </div>
                            </div>

                            <div class="disclaimer-box"
                                style="margin-top: 48px; background: rgba(239, 68, 68, 0.06); border: 1.5px solid rgba(239, 68, 68, 0.15); padding: 24px; border-radius: 24px;">
                                <h4
                                    style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 0.95rem; color: #ef4444; margin: 0 0 12px 0;">
                                    <i class="fas fa-circle-info"></i> MEDICAL REMINDER
                                </h4>
                                <p
                                    style="font-size: 0.88rem; color: #4b5563; line-height: 1.6; margin: 0; font-weight: 500;">
                                    Results are estimates based on standard daily values. For professional dietary
                                    advice, please consult your Nutrideq dietician or a doctor.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const nutrientItems = document.querySelectorAll('.nutrient-item');
                const calculateBtn = document.getElementById('calculateBtn');
                const resetBtn = document.getElementById('resetBtn');
                const resultsList = document.getElementById('resultsList');
                const emptyState = document.getElementById('emptyState');
                const calculatedResults = document.getElementById('calculatedResults');

                const referenceValues = {
                    'sodium': 500, 'potassium': 2000, 'dietary-fiber': 25, 'protein': 50,
                    'vitamin-a': 800, 'vitamin-c': 60, 'calcium': 750, 'iron': 14,
                    'vitamin-d': 15, 'vitamin-e': 15, 'vitamin-k': 65, 'thiamin': 1.2,
                    'riboflavin': 1.3, 'niacin': 16, 'vitamin-b6': 1.3, 'folate': 400,
                    'vitamin-b12': 2.4, 'iodine': 150, 'magnesium': 350, 'zinc': 11,
                    'selenium': 55, 'chloride': 2300, 'flouride': 3, 'phosphorus': 700
                };

                const displayNames = {
                    'sodium': 'Sodium', 'potassium': 'Potassium', 'dietary-fiber': 'Fiber', 'protein': 'Protein',
                    'vitamin-a': 'Vitamin A', 'vitamin-c': 'Vitamin C', 'calcium': 'Calcium', 'iron': 'Iron',
                    'vitamin-d': 'Vitamin D', 'vitamin-e': 'Vitamin E', 'vitamin-k': 'Vitamin K', 'thiamin': 'Thiamin',
                    'riboflavin': 'Riboflavin', 'niacin': 'Niacin', 'vitamin-b6': 'Vitamin B6', 'folate': 'Folate',
                    'vitamin-b12': 'Vitamin B12', 'iodine': 'Iodine', 'magnesium': 'Magnesium', 'zinc': 'Zinc',
                    'selenium': 'Selenium', 'chloride': 'Chloride', 'flouride': 'Fluoride', 'phosphorus': 'Phosphorus'
                };

                // Toggle Selection logic
                nutrientItems.forEach(item => {
                    item.addEventListener('click', function (e) {
                        if (e.target.tagName !== 'INPUT') {
                            this.classList.toggle('active');
                            const input = this.querySelector('.nutrient-input');
                            if (this.classList.contains('active')) input.focus();
                        }
                    });
                });

                calculateBtn.addEventListener('click', function () {
                    const selected = [];
                    nutrientItems.forEach(item => {
                        const input = item.querySelector('.nutrient-input');
                        const val = parseFloat(input.value) || 0;
                        if (val > 0) {
                            selected.push({ id: item.dataset.nutrient, val: val });
                        }
                    });

                    if (selected.length === 0) {
                        alert('Calculation stopped: Please enter a weight higher than 0.');
                        return;
                    }

                    resultsList.innerHTML = '';
                    selected.forEach(s => {
                        const ref = referenceValues[s.id];
                        const pct = ((s.val / ref) * 100).toFixed(2);
                        const progress = Math.min(pct, 100);

                        const res = document.createElement('div');
                        res.className = 'result-group';
                        res.style.padding = '14px 20px';
                        res.style.background = 'rgba(255,255,255,0.6)';
                        res.style.borderRadius = '16px';
                        res.style.border = '1px solid rgba(255,255,255,0.9)';
                        res.style.boxShadow = '0 8px 20px -10px rgba(0,0,0,0.04)';

                        res.innerHTML = `
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-weight:800; color:#1e293b; font-family:'Outfit',sans-serif; font-size:0.9rem;">
                                        ${displayNames[s.id]}
                                    </span>
                                    <span style="font-size: 0.65rem; color: #64748b; font-weight: 600;">Ref: ${ref} units</span>
                                </div>
                                <span style="font-weight:800; color:#10b981; font-family:'Outfit',sans-serif; font-size:1.1rem;">
                                    ${pct}%
                                </span>
                            </div>
                            <div class="diagnostic-progress">
                                <div class="progress-bar" style="width: 0%; background: ${pct > 100 ? '#ef4444' : (pct > 75 ? '#10b981' : '#f59e0b')}"></div>
                            </div>
                        `;
                        resultsList.appendChild(res);
                        setTimeout(() => { res.querySelector('.progress-bar').style.width = progress + '%'; }, 100);
                    });

                    // Generate Insights
                    const clinicalInsights = document.getElementById('clinicalInsights');
                    const insightContent = document.getElementById('insightContent');
                    clinicalInsights.style.display = 'block';
                    insightContent.innerHTML = '';

                    const highNutrients = selected.filter(s => (s.val / referenceValues[s.id]) > 0.5);
                    const lowNutrients = selected.filter(s => (s.val / referenceValues[s.id]) <= 0.5);

                    if (highNutrients.length > 0) {
                        const div = document.createElement('div');
                        div.style.cssText = 'font-size: 0.78rem; color: #1e293b; line-height: 1.4;';
                        div.innerHTML = `<b>🔥 High Performance:</b> This serving provides a significant amount of <b>${highNutrients.map(n => displayNames[n.id]).join(', ')}</b>. This is excellent for metabolic stability.`;
                        insightContent.appendChild(div);
                    }

                    if (selected.some(n => n.id === 'sodium' && (n.val / referenceValues['sodium']) > 0.4)) {
                        const div = document.createElement('div');
                        div.style.cssText = 'background: rgba(239, 68, 68, 0.05); padding: 10px; border-radius: 12px; font-size: 0.75rem; border: 1px solid rgba(239, 68, 68, 0.1);';
                        div.innerHTML = `<span style="color: #ef4444; font-weight: 800;">⚠ High Sodium Alert:</span> This food contains over 40% of your sodium target. Ensure you drink extra water to maintain osmotic balance.`;
                        insightContent.appendChild(div);
                    }

                    if (selected.some(n => n.id === 'protein' && (n.val / referenceValues['protein']) > 0.3)) {
                        const div = document.createElement('div');
                        div.style.cssText = 'background: rgba(16, 185, 129, 0.05); padding: 10px; border-radius: 12px; font-size: 0.75rem; border: 1px solid rgba(16, 185, 129, 0.1);';
                        div.innerHTML = `<span style="color: #10b981; font-weight: 800;">💪 Muscle Fuel:</span> Great protein density! This is optimal for muscle recovery and metabolic thermogenesis.`;
                        insightContent.appendChild(div);
                    }

                    emptyState.style.display = 'none';
                    calculatedResults.style.display = 'block';

                    this.innerHTML = '<i class="fas fa-check"></i> Calculation Ready';
                    setTimeout(() => { this.innerHTML = '<i class="fas fa-play"></i> Calculate Now'; }, 2000);
                });

                resetBtn.addEventListener('click', function () {
                    nutrientItems.forEach(item => {
                        item.classList.remove('active');
                        item.querySelector('.nutrient-input').value = '';
                    });
                    emptyState.style.display = 'block';
                    calculatedResults.style.display = 'none';
                });

                window.switchNutrientTab = function (tabId, btn) {
                    btn.parentElement.querySelectorAll('.pill-item').forEach(t => t.classList.remove('active'));
                    btn.classList.add('active');
                    document.querySelectorAll('.nutrient-tab-content').forEach(c => c.style.display = 'none');
                    document.getElementById(tabId).style.display = 'block';
                };
            });
        </script>
</body>

</html>
