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
function getInitials($name) {
    $names = explode(' ', $name);
    $initials = '';
    foreach ($names as $n) { if ($n) $initials .= strtoupper($n[0]); }
    return substr($initials, 0, 2);
}

$user_initials = getInitials($user_name);
require_once 'navigation.php';
$nav_links_array = getNavigationLinks($user_role, 'Nutrition-Calculator.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>Nutrition Calculator | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

        .dash-premium { background: transparent !important; min-height: var(--app-height-dvh); position: relative; overflow-x: hidden; }
        
        .diagnostic-engine {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 28px;
            margin-top: 24px;
            position: relative;
            z-index: 10;
        }

        .terminal-panel {
            background: var(--glass-bg) !important;
            backdrop-filter: blur(25px);
            border: 1px solid var(--glass-border) !important;
            border-radius: 32px;
            padding: 32px;
            box-shadow: var(--glass-shadow);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        /* Tactical Bento Inputs */
        .bento-input-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 16px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }
        .metric-card:focus-within { border-color: var(--primary); background: #ffffff; transform: translateY(-4px); box-shadow: 0 12px 30px -10px rgba(5, 150, 105, 0.15); }

        .metric-label { font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .metric-label i { color: var(--primary); font-size: 0.9rem; }

        .tac-input-group { display: flex; align-items: center; gap: 8px; }
        .tac-input { 
            width: 100%; border: none; background: transparent; 
            font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.25rem; 
            color: var(--text-primary); outline: none; 
        }
        .tac-select { 
            background: rgba(0,0,0,0.05); border: none; border-radius: 8px; 
            padding: 4px 8px; font-weight: 700; font-size: 0.75rem; color: var(--text-secondary); 
            cursor: pointer; outline: none;
        }

        /* Floating Pill Nav */
        .pill-nav {
            background: rgba(0,0,0,0.04); padding: 4px; border-radius: 50px;
            display: inline-flex; gap: 6px; margin-bottom: 20px;
        }
        .pill-item {
            padding: 10px 24px; border-radius: 50px; font-weight: 800; font-size: 0.8rem;
            text-transform: uppercase; cursor: pointer; transition: all 0.3s; color: var(--text-secondary);
        }
        .pill-item.active { background: var(--primary); color: white; box-shadow: 0 8px 20px rgba(5, 150, 105, 0.25); transform: scale(1.05); }

        /* Diagnostic HUD */
        .diagnostic-hud { display: flex; flex-direction: column; gap: 20px; }
        .hud-card {
            background: rgba(255, 255, 255, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .hud-val-main { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2.2rem; color: var(--text-primary); margin-bottom: 4px; }
        .hud-lbl { font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.02em; }

        .progress-hud { height: 10px; background: rgba(0,0,0,0.05); border-radius: 20px; overflow: hidden; margin-top: 16px; border: 1px solid rgba(255,255,255,0.2); }
        .progress-hud-bar { 
            height: 100%; width: 0; background: var(--gradient); 
            border-radius: 20px; transition: width 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }
        .progress-hud-bar::after {
            content: ''; position: absolute; top: 0; right: 0; width: 4px; height: 100%;
            background: #fff; box-shadow: 0 0 10px #fff;
        }

        /* SVG Diagnostic Rings */
        .ring-container { position: relative; width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; margin: 0 auto; }
        .ring-svg { transform: rotate(-90deg); }
        .ring-bg { fill: none; stroke: rgba(0,0,0,0.05); stroke-width: 8; }
        .ring-fill { 
            fill: none; stroke: var(--primary); stroke-width: 8; stroke-linecap: round; 
            stroke-dasharray: 283; stroke-dashoffset: 283; transition: stroke-dashoffset 1.5s ease; 
        }
        .ring-val { position: absolute; font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.5rem; color: var(--text-primary); }

        /* Bio-Scan Animation */
        .scan-line {
            position: absolute; top: -10px; left: 0; width: 100%; height: 2px;
            background: rgba(5, 150, 105, 0.4); box-shadow: 0 0 15px var(--primary);
            z-index: 100; pointer-events: none; opacity: 0;
        }
        @keyframes scanEffect {
            0% { top: 0; opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }
        .scan-active .scan-line { animation: scanEffect 1.5s ease-in-out forwards; }

        .btn-bio-scan {
            background: var(--primary); color: white; border: none; border-radius: 20px;
            padding: 18px; font-weight: 800; font-size: 1rem; cursor: pointer;
            width: 100%; margin-top: 24px; transition: all 0.3s;
            box-shadow: 0 10px 25px rgba(5, 150, 105, 0.2);
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .btn-bio-scan:hover { transform: translateY(-4px); box-shadow: 0 15px 35px rgba(5, 150, 105, 0.4); filter: brightness(1.1); }

        @keyframes pulse-emerald { 0% { transform: scale(1); opacity: 0.8; } 50% { transform: scale(1.05); opacity: 1; } 100% { transform: scale(1); opacity: 0.8; } }
        .clinical-status { padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; float: right; margin-top: 6px; }
        .status-healthy { background: rgba(5, 150, 105, 0.1); color: #059669; }
        .status-warning { background: rgba(217, 119, 6, 0.1); color: #d97706; }
        .status-danger { background: rgba(225, 29, 72, 0.1); color: #e11d48; }

        @media (max-width: 1100px) { .diagnostic-engine { grid-template-columns: 1fr; } }
        
        /* Mobile Precision Polish */
        @media screen and (max-width: 768px) {
            .page-container { padding: 15px 15px 90px 15px !important; }
            .terminal-panel { padding: 20px !important; border-radius: 20px !important; }
            .bento-input-grid { grid-template-columns: 1fr !important; gap: 12px !important; }
            .pill-nav { flex-wrap: wrap; justify-content: center; width: 100%; border-radius: 12px; }
            .pill-item { flex: 1 1 auto; text-align: center; padding: 8px 12px !important; }
            .page-title h1 { font-size: 1.6rem !important; }
            #inputTerminal h2 { font-size: 1.1rem !important; }
            .diagnostic-hud .terminal-panel > div { gap: 12px !important; }
            .hud-val-main { font-size: 1.8rem !important; }
        }
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
                        <h1 style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2.1rem; letter-spacing: -0.02em; color: #1e293b; margin: 0;">Nutrition Calculator</h1>
                        <p style="font-weight: 600; color: #64748b; font-size: 0.95rem; margin-top: 4px;">Calculate your daily calories and ideal weight</p>
                    </div>
                </div>

                <div class="diagnostic-engine">
                    <!-- Left: Input Peripheral -->
                    <div class="terminal-panel stagger d-1" id="inputTerminal">
                        <div class="scan-line"></div>
                        <div style="border:none; padding:0; margin-bottom:20px; display: flex; align-items: center; justify-content: space-between;">
                            <h2 style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.25rem; margin: 0; color: #1e293b;">
                                <i class="fas fa-fingerprint" style="color:var(--primary); margin-right:12px;"></i> Your Details
                            </h2>
                        </div>

                        <!-- Sex Segmented Control -->
                        <div class="metric-label"><i class="fas fa-venus-mars"></i> Sex</div>
                        <div class="pill-nav" id="sexToggle">
                            <div class="pill-item active" data-val="male">Male</div>
                            <div class="pill-item" data-val="female">Female</div>
                        </div>

                        <div class="bento-input-grid">
                            <!-- Date Chip -->
                            <div class="metric-card">
                                <div class="metric-label"><i class="fas fa-calendar-alt"></i> Birth Date</div>
                                <input type="date" id="dob" class="tac-input" style="font-size: 1rem;">
                            </div>

                            <!-- Weight Chip -->
                            <div class="metric-card">
                                <div class="metric-label"><i class="fas fa-weight"></i> Weight</div>
                                <div class="tac-input-group">
                                    <input type="number" id="weight" class="tac-input" placeholder="0">
                                    <select id="weightUnit" class="tac-select"><option>kg</option><option>lbs</option></select>
                                </div>
                            </div>

                            <!-- Height Chip -->
                            <div class="metric-card">
                                <div class="metric-label"><i class="fas fa-ruler-vertical"></i> Height</div>
                                <div class="tac-input-group" id="heightInputGroup">
                                    <input type="number" id="height" class="tac-input" placeholder="0">
                                    <select id="heightUnit" class="tac-select"><option value="cm">cm</option><option value="ft">ft</option></select>
                                </div>
                                <!-- Feet/Inches Container (Hidden by default) -->
                                <div id="ftInContainer" style="display: none; align-items: center; gap: 8px;">
                                    <div style="flex: 1; display: flex; align-items: baseline; gap: 4px;">
                                        <input type="number" id="heightFt" class="tac-input" placeholder="0" style="text-align: right; width: 40px;">
                                        <span style="font-weight: 800; color: var(--text-secondary);">'</span>
                                    </div>
                                    <div style="flex: 1; display: flex; align-items: baseline; gap: 4px;">
                                        <input type="number" id="heightIn" class="tac-input" placeholder="0" style="text-align: right; width: 40px;">
                                        <span style="font-weight: 800; color: var(--text-secondary);">"</span>
                                    </div>
                                    <select id="heightUnitFt" class="tac-select"><option value="cm">cm</option><option value="ft" selected>ft</option></select>
                                </div>
                            </div>

                            <!-- Waist Chip -->
                            <div class="metric-card">
                                <div class="metric-label"><i class="fas fa-arrows-alt-h"></i> Waist Size</div>
                                <div class="tac-input-group">
                                    <input type="number" id="waist" class="tac-input" placeholder="0">
                                    <select id="waistUnit" class="tac-select"><option>cm</option><option>in</option></select>
                                </div>
                            </div>

                            <!-- Hip Chip -->
                            <div class="metric-card">
                                <div class="metric-label"><i class="fas fa-vector-square"></i> Hip Size</div>
                                <div class="tac-input-group">
                                    <input type="number" id="hip" class="tac-input" placeholder="0">
                                    <select id="hipUnit" class="tac-select"><option>cm</option><option>in</option></select>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Segmented Control -->
                        <div class="metric-label" style="margin-top: 24px;"><i class="fas fa-walking"></i> Daily Activity Level</div>
                        <div class="pill-nav" id="activityToggle" style="margin-bottom: 12px;">
                            <div class="pill-item active" data-val="sedentary">Sedentary</div>
                            <div class="pill-item" data-val="light">Light</div>
                            <div class="pill-item" data-val="moderate">Moderate</div>
                            <div class="pill-item" data-val="very">Very Active</div>
                        </div>
                        <div id="activityDesc" style="background: rgba(0,0,0,0.03); border-radius: 12px; padding: 12px 16px; font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4; margin-bottom: 24px;">
                            Mostly resting with little or no activity.
                        </div>

                        <button class="btn-bio-scan" id="calculateBtn">
                            <i class="fas fa-microscope" style="margin-right: 12px;"></i> Calculate My Results
                        </button>
                    </div>

                    <!-- Right: Diagnostic HUD -->
                    <div class="diagnostic-hud stagger d-2">
                        <!-- Primary Rings -->
                        <div class="terminal-panel" style="padding: 24px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="ring-diagnostic">
                                    <div class="ring-container">
                                        <svg class="ring-svg" width="100" height="100">
                                            <circle class="ring-bg" cx="50" cy="50" r="45"></circle>
                                            <circle class="ring-fill" id="bmiRing" cx="50" cy="50" r="45"></circle>
                                        </svg>
                                        <div class="ring-val" id="bmiResult">--</div>
                                    </div>
                                    <div class="hud-lbl" style="text-align: center; margin-top: 12px;">Body Mass Index (BMI)</div>
                                    <div id="bmiStatus" class="clinical-status" style="float:none; text-align:center; display:block;">Awaiting Scan</div>
                                </div>
                                <div class="ring-diagnostic">
                                    <div class="ring-container">
                                        <svg class="ring-svg" width="100" height="100">
                                            <circle class="ring-bg" cx="50" cy="50" r="45"></circle>
                                            <circle class="ring-fill" id="tdeeRing" cx="50" cy="50" r="45" style="stroke: #3b82f6;"></circle>
                                        </svg>
                                        <div class="ring-val" id="tdeeResult" style="font-size: 1.2rem;">--</div>
                                    </div>
                                    <div class="hud-lbl" style="text-align: center; margin-top: 12px;">Maintain Weight Calories</div>
                                    <div id="tdeeUnit" class="clinical-status status-healthy" style="float:none; text-align:center; display:block;">kcal/day</div>
                                </div>
                            </div>
                        </div>

                        <!-- Ratio HUDs -->
                        <div class="terminal-panel" style="gap: 16px; padding: 24px;">
                            <div class="hud-row">
                                <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                    <div class="hud-lbl">Waist-to-Hip Ratio</div>
                                    <div class="hud-val-main" style="font-size: 1.4rem;" id="whrResult">--</div>
                                </div>
                                <div class="progress-hud"><div class="progress-hud-bar" id="whrBar"></div></div>
                                <div id="whrStatus" class="clinical-status">N/A</div>
                            </div>

                            <div class="hud-row">
                                <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                    <div class="hud-lbl">Waist-to-Height Ratio</div>
                                    <div class="hud-val-main" style="font-size: 1.4rem;" id="whtrResult">--</div>
                                </div>
                                <div class="progress-hud"><div class="progress-hud-bar" id="whtrBar" style="background: linear-gradient(90deg, #3b82f6, #60a5fa);"></div></div>
                                <div id="whtrStatus" class="clinical-status">N/A</div>
                            </div>
                        </div>

                        <!-- DBW Terminal -->
                        <div class="terminal-panel" style="padding: 24px; border: 1px solid var(--primary-light) !important;">
                             <div class="hud-lbl" style="margin-bottom: 12px; color: var(--primary);">Desirable Weight Formula</div>
                             <div class="pill-nav" id="methodToggle">
                                 <div class="pill-item active" data-val="tannhauser">Tan</div>
                                 <div class="pill-item" data-val="hamwi">Hamwi</div>
                             </div>
                             <div id="methodDesc" style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 8px; line-height: 1.3; font-style: italic;">
                                 <b>Tannhauser:</b> A standard clinical formula calculating weight based on height (cm) minus 100, with a 10% adjustment.
                             </div>
                             <div style="display: flex; align-items: baseline; gap: 12px; margin-top: 12px;">
                                <div class="hud-val-main" id="dbwResult" style="color: var(--primary); font-size: 3rem;">--</div>
                                <div class="hud-lbl" style="font-size: 1.2rem;">kg</div>
                             </div>
                             <p style="font-size: 0.75rem; color: var(--text-secondary); margin: 8px 0 0; font-weight: 600;">*Estimated healthy target weight.</p>
                        </div>

                        <!-- NEW: AI Clinical Guidance -->
                        <div class="terminal-panel" id="guidanceTerminal" style="padding: 24px; background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.2)); border: 1px solid rgba(129,140,248,0.2) !important; display: none;">
                             <div class="hud-lbl" style="margin-bottom: 15px; color: #6366f1; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-brain"></i> Clinical Guidance
                             </div>
                             <div id="guidanceContent" style="display: flex; flex-direction: column; gap: 12px;">
                                <!-- Populated by JS -->
                             </div>
                        </div>
                    </div>
                </div>

                <!-- Clinical Notice Overlay -->
                <div class="terminal-panel stagger d-3" style="margin-top: 24px; background: rgba(239, 68, 68, 0.05) !important; border: 1.2px solid rgba(239, 68, 68, 0.15) !important; padding: 20px;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <i class="fas fa-biohazard fa-2x" style="color: #ef4444; opacity: 0.8;"></i>
                        <div>
                            <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-weight: 800; color: #ef4444; font-size: 1rem;">IMPORTANT MEDICAL NOTE</h4>
                            <p style="margin: 4px 0 0; font-size: 0.85rem; color: var(--text-secondary); font-weight: 600;">
                                These results are estimates. For professional medical advice, consult a Registered Nutritionist-Dietitian.
                            </p>
                        </div>
                        <?php if ($user_role === 'regular'): ?>
                        <button class="btn-dash-action" style="margin-left: auto; background: #ef4444; color: white; padding: 10px 24px; font-size: 0.8rem; border-radius: 50px;">
                            CONSULT SPECIALIST
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Tactical Toggle Logic
            function initToggle(containerId, callback) {
                const container = document.getElementById(containerId);
                const items = container.querySelectorAll('.pill-item');
                items.forEach(item => {
                    item.addEventListener('click', () => {
                        items.forEach(i => i.classList.remove('active'));
                        item.classList.add('active');
                        if(callback) callback(item.dataset.val);
                    });
                });
            }

            // State variables initialized from active UI
            let currentSex = document.querySelector('#sexToggle .active')?.dataset.val || 'male';
            let currentActivity = document.querySelector('#activityToggle .active')?.dataset.val || 'sedentary';
            let currentMethod = document.querySelector('#methodToggle .active')?.dataset.val || 'tannhauser';

            const methodDescriptions = {
                tannhauser: "<b>Tannhauser:</b> A standard clinical formula calculating weight based on height (cm) minus 100, with a 10% adjustment.",
                hamwi: "<b>Hamwi:</b> A more specific formula that uses 5 feet (152.4 cm) as a base and adds weight for every additional inch."
            };

            const activityDescriptions = {
                sedentary: "Mostly resting with little or no activity.",
                light: "Mostly sitting/desk work (clerical, professional) or light housework (dishwashing, preparing food) with occasional walking.",
                moderate: "Extended walking, pushing, pulling, or lifting/carrying heavy objects (cleaning, waiting tables, farming).",
                very: "Extensive running, rapid movement, or tasks requiring strenuous effort and heavy body movement (firefighting, construction, masonry)."
            };

            initToggle('sexToggle', (val) => currentSex = val);
            initToggle('activityToggle', (val) => {
                currentActivity = val;
                document.getElementById('activityDesc').textContent = activityDescriptions[val];
            });
            initToggle('methodToggle', (val) => {
                currentMethod = val;
                document.getElementById('methodDesc').innerHTML = methodDescriptions[val];
                if(document.getElementById('bmiResult').textContent !== '--') calculateDiagnostics();
            });

            // Height Unit Management
            const heightUnit = document.getElementById('heightUnit');
            const heightUnitFt = document.getElementById('heightUnitFt');
            const heightInputGroup = document.getElementById('heightInputGroup');
            const ftInContainer = document.getElementById('ftInContainer');

            function toggleHeightUnit(unit) {
                if (unit === 'ft') {
                    heightInputGroup.style.display = 'none';
                    ftInContainer.style.display = 'flex';
                } else {
                    heightInputGroup.style.display = 'flex';
                    ftInContainer.style.display = 'none';
                }
            }

            heightUnit.addEventListener('change', (e) => toggleHeightUnit(e.target.value));
            heightUnitFt.addEventListener('change', (e) => {
                heightUnit.value = e.target.value;
                toggleHeightUnit(e.target.value);
            });

            // SVG Ring Controller
            function updateRing(id, percentage, max = 100) {
                const ring = document.getElementById(id);
                const radius = 45;
                const circumference = 2 * Math.PI * radius;
                const offset = circumference - (Math.min(percentage, max) / max) * circumference;
                ring.style.strokeDashoffset = offset;
            }

            // Diagnostic Logic
            const calculateBtn = document.getElementById('calculateBtn');
            calculateBtn.addEventListener('click', () => {
                const terminal = document.getElementById('inputTerminal');
                terminal.classList.remove('scan-active');
                void terminal.offsetWidth; // Trigger reflow
                terminal.classList.add('scan-active');
                
                setTimeout(calculateDiagnostics, 600);
            });

            function calculateDiagnostics() {
                const weightVal = parseFloat(document.getElementById('weight').value) || 0;
                const weightUnit = document.getElementById('weightUnit').value;
                const dob = document.getElementById('dob').value;
                const currentHeightUnit = document.getElementById('heightUnit').value;
                
                let heightCm = 0;
                if (currentHeightUnit === 'ft') {
                    const feet = parseFloat(document.getElementById('heightFt').value) || 0;
                    const inches = parseFloat(document.getElementById('heightIn').value) || 0;
                    if (feet > 0 || inches > 0) {
                        heightCm = (feet * 30.48) + (inches * 2.54);
                    }
                } else {
                    heightCm = parseFloat(document.getElementById('height').value) || 0;
                }

                if (!weightVal || !heightCm) {
                    alert("Please provide both Weight and Height to calculate your results.");
                    return;
                }

                const waistVal = parseFloat(document.getElementById('waist').value) || 0;
                const waistUnit = document.getElementById('waistUnit').value;
                const hipVal = parseFloat(document.getElementById('hip').value) || 0;
                const hipUnit = document.getElementById('hipUnit').value;

                // Standardize Units
                let weightKg = weightUnit === 'lbs' ? weightVal * 0.453592 : weightVal;
                let waistCm = waistUnit === 'in' ? waistVal * 2.54 : waistVal;
                let hipCm = hipUnit === 'in' ? hipVal * 2.54 : hipVal;

                // Age Diagnostic (Safety Fallback)
                let age = 25;
                if (dob) {
                    const birthDate = new Date(dob);
                    if (!isNaN(birthDate.getTime())) {
                        const today = new Date();
                        age = today.getFullYear() - birthDate.getFullYear();
                        const m = today.getMonth() - birthDate.getMonth();
                        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
                    }
                }
                if (isNaN(age) || age < 1) age = 25;

                // BMI Diagnostic
                const heightM = heightCm / 100;
                const bmi = (weightKg / (heightM * heightM)).toFixed(1);
                document.getElementById('bmiResult').textContent = bmi;
                
                const bmiStatus = document.getElementById('bmiStatus');
                bmiStatus.className = 'clinical-status';
                if (bmi < 18.5) { bmiStatus.textContent = 'Underweight'; bmiStatus.classList.add('status-warning'); }
                else if (bmi < 25) { bmiStatus.textContent = 'Healthy Range'; bmiStatus.classList.add('status-healthy'); }
                else if (bmi < 30) { bmiStatus.textContent = 'High Threshold'; bmiStatus.classList.add('status-warning'); }
                else { bmiStatus.textContent = 'Clinical Obese'; bmiStatus.classList.add('status-danger'); }
                
                updateRing('bmiRing', bmi, 40);

                // TDEE using DBW Method
                let dbw = 0;
                const heightIn = heightCm / 2.54;
                const inchesOver5ft = Math.max(0, heightIn - 60);

                if (currentMethod === 'tannhauser') {
                    dbw = (heightCm - 100) * 0.9;
                } else if (currentMethod === 'hamwi') {
                    dbw = currentSex === 'male' ? 48 + (2.7 * inchesOver5ft) : 45 + (2.2 * inchesOver5ft);
                }

                // Step 2: Apply kcal/kg of DBW based on activity level
                // (Standard Philippine clinical nutrition values)
                const kcalPerKgMap = { sedentary: 30, light: 35, moderate: 40, very: 45 };
                const kcalPerKg = kcalPerKgMap[currentActivity] || 30;

                const tdee = Math.round(dbw * kcalPerKg);
                const tdeeEl = document.getElementById('tdeeResult');

                if (!isNaN(tdee) && tdee > 0) {
                    tdeeEl.textContent = tdee;
                    updateRing('tdeeRing', tdee, 4000);
                } else {
                    tdeeEl.textContent = 'Err';
                }
                
                // Add reactive persist sync for dashboard
                const macroGoals = {
                    calories: tdee,
                    protein: Math.round((tdee * 0.30) / 4),
                    carbs: Math.round((tdee * 0.40) / 4),
                    fats: Math.round((tdee * 0.30) / 9)
                };
                fetch('api/save_macro_goals.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(macroGoals)
                }).catch(e => console.warn('Sync failed:', e));

                // Ratio Synthesis
                if (waistCm && hipCm) {
                    const whr = (waistCm / hipCm).toFixed(2);
                    document.getElementById('whrResult').textContent = whr;
                    const whrStatus = document.getElementById('whrStatus');
                    const isHigh = currentSex === 'male' ? whr > 0.9 : whr > 0.85;
                    whrStatus.textContent = isHigh ? 'High Risk' : 'Optimal';
                    whrStatus.className = 'clinical-status ' + (isHigh ? 'status-danger' : 'status-healthy');
                    document.getElementById('whrBar').style.width = (whr * 100) + '%';
                }

                if (waistCm && heightCm) {
                    const whtr = (waistCm / heightCm).toFixed(2);
                    document.getElementById('whtrResult').textContent = whtr;
                    const whtrStatus = document.getElementById('whtrStatus');
                    const isHigh = whtr > 0.5;
                    whtrStatus.textContent = isHigh ? 'Risk Detected' : 'Metabolic Optimal';
                    whtrStatus.className = 'clinical-status ' + (isHigh ? 'status-danger' : 'status-healthy');
                    document.getElementById('whtrBar').style.width = (whtr * 100) + '%';
                }

                // Desirable Body Weight Method (Display)
                document.getElementById('dbwResult').textContent = Math.round(dbw);

                // --- Generate Guidance Tips ---
                const guidanceTerminal = document.getElementById('guidanceTerminal');
                const guidanceContent = document.getElementById('guidanceContent');
                guidanceTerminal.style.display = 'flex';
                guidanceContent.innerHTML = '';

                const tips = [];
                
                // BMI Tip
                if (bmi < 18.5) tips.push({ icon: 'fa-apple-whole', title: 'Nutrient Density', text: 'Focus on calorie-dense whole foods like nuts, avocados, and oils to reach a healthy weight safely.' });
                else if (bmi >= 25) tips.push({ icon: 'fa-person-walking', title: 'Caloric Deficit', text: 'To lose 0.5kg per week, aim for about ' + (tdee - 500) + ' kcal/day while maintaining protein intake.' });
                else tips.push({ icon: 'fa-shield-heart', title: 'Maintenance Mode', text: 'You are in the optimal range. Focus on diverse micronutrients to maintain this metabolic stability.' });

                // Ratio Tip
                if ((waistCm && hipCm && (currentSex === 'male' ? (waistCm/hipCm) > 0.9 : (waistCm/hipCm) > 0.85)) || (waistCm && heightCm && (waistCm/heightCm) > 0.5)) {
                    tips.push({ icon: 'fa-fire', title: 'Metabolic Focus', text: 'Elevated ratios indicate visceral fat. Prioritize high-intensity activity and fiber-rich foods to improve metabolic markers.' });
                }

                // General Recommendation
                tips.push({ icon: 'fa-glass-water', title: 'Hydration Target', text: 'Based on your weight, your baseline hydration target is approximately ' + (weightKg * 35).toFixed(0) + 'ml of water daily.' });

                tips.forEach(tip => {
                    const div = document.createElement('div');
                    div.style.cssText = 'background: rgba(255,255,255,0.5); padding: 12px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.8); display: flex; gap: 12px; align-items: start;';
                    div.innerHTML = `
                        <div style="width: 32px; height: 32px; background: #e0e7ff; color: #4f46e5; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas ${tip.icon}"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 800; color: #1e293b;">${tip.title}</div>
                            <div style="font-size: 0.72rem; color: #64748b; line-height: 1.3; margin-top: 2px;">${tip.text}</div>
                        </div>
                    `;
                    guidanceContent.appendChild(div);
                });
            }

            // Spotlight Tracking
            const spotlight = document.getElementById('spotlight');
            document.addEventListener('mousemove', (e) => {
                spotlight.style.left = e.clientX + 'px';
                spotlight.style.top = e.clientY + 'px';
            });
        });
    </script>
</body>
</html>
