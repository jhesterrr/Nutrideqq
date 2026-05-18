<?php
// Fix for XAMPP Permission Denied session error
$session_path = __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0777, true);
}
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ANTI-CACHE HEADERS
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriDeq — Nutrition Data, Equity, and Quantification</title>
    <meta name="description"
        content="NutriDeq is a modern platform helping dietitians provide precise, accessible, and personalized nutrition care.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/landing-premium.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/MotionPathPlugin.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/studio-freight/lenis@1.0.29/bundled/lenis.min.js"></script>
    <style>
        .splash-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #FFFFFF; /* Pure white for cleaner clinical start */
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            overflow: hidden;
        }
        .splash-logo-svg {
            width: 70%;
            max-width: 280px;
            height: auto;
            overflow: visible;
        }
    </style>
</head>

<body class="loading">

    <!-- Custom Cursor -->
    <div class="custom-cursor">
        <div class="cursor-dot"></div>
        <div class="cursor-ring"></div>
    </div>

    <!-- Splash Screen -->
    <div id="splash-screen" class="splash-screen">
        <svg viewBox="0 0 240 280" class="splash-logo-svg">
            <!-- The Emerald Facet: A jeweled geometric leaf representing the 'precious' nature of health -->
            
            <!-- Main Top Facet (Core Vitality) -->
            <path class="facet top-facet" 
                d="M120 40 L165 90 L120 135 L75 90 Z" 
                fill="#10b981" opacity="0"/>
            
            <!-- Lower Left Facet (Clinical Foundation) -->
            <path class="facet left-facet" 
                d="M75 90 L120 135 L100 190 L55 135 Z" 
                fill="#059669" opacity="0"/>
            
            <!-- Lower Right Facet (Data Synthesis) -->
            <path class="facet right-facet" 
                d="M165 90 L185 135 L140 190 L120 135 Z" 
                fill="#34d399" opacity="0"/>
            
            <!-- Subtle Reflection (The Luxury Detail) -->
            <path class="facet reflection-facet" 
                d="M120 40 L135 70 L120 85 L105 70 Z" 
                fill="white" opacity="0"/>

            <!-- Typography: Plus Jakarta Sans with Elite Weight Contrast -->
            <g class="brand-name-group" opacity="0">
                <text x="120" y="250" text-anchor="middle" font-family="'Plus Jakarta Sans', sans-serif" font-size="34" letter-spacing="-1">
                    <tspan font-weight="800" fill="#0F172A">NUTRI</tspan><tspan font-weight="200" fill="#64748B">DEQ</tspan>
                </text>
                <text x="120" y="272" text-anchor="middle" font-family="'Plus Jakarta Sans', sans-serif" font-weight="600" font-size="9" letter-spacing="8" fill="#10b981">PRECISION BIOMETRICS</text>
            </g>
        </svg>
    </div>

    <!-- Background Engine (Canvas + CSS Mesh) -->
    <canvas id="bg-canvas"></canvas>
    <div class="neural-overlay"></div>
    <div class="bg-gradient-mesh"></div>
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <!-- Pill Navigation -->
    <nav class="main-nav">
        <div class="logo">
            <svg viewBox="0 0 240 240" class="nav-logo-svg">
                <!-- Compact Emerald Facet -->
                <path d="M120 40 L165 90 L120 135 L75 90 Z" fill="#10b981"/>
                <path d="M75 90 L120 135 L100 190 L55 135 Z" fill="#059669"/>
                <path d="M165 90 L185 135 L140 190 L120 135 Z" fill="#34d399"/>
            </svg>
            <div class="nav-logo-text" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -1px;">
                <span style="font-weight: 800; color: #0F172A;">NUTRI</span><span style="font-weight: 200; color: #64748B;">DEQ</span>
            </div>
        </div>
        <div class="links">
            <a href="#hero">Platform</a>
            <a href="#data">FCT Engine</a>
            <a href="#psychometrics">Psychometrics</a>
            <a href="#features">Architecture</a>
        </div>
        <a href="login-logout/NutriDeqN-Login.php" class="cta-btn">Sign in</a>
    </nav>

    <!-- Smooth Scroll Wrapper -->
    <main id="smooth-wrapper">
        <div id="smooth-content">

            <!-- Hero Section -->
            <section id="hero" class="panel">
                <div class="hero-content">
                    <div class="hero-badge">v2.0 Clinical Registry Live</div>
                    <h1 class="hero-title">Nutrition Data, Equity,<br>and <span>Quantification.</span></h1>
                    <p class="section-subtitle">
                        A clinical-grade platform engineered for registered dietitians. We unify precision biometrics, authoritative FCT datasets, and therapeutic care planning into a single, high-performance workflow.
                    </p>
                    <div class="hero-actions">
                        <a href="#data" class="btn-primary">See Features</a>
                        <a href="login-logout/NutriDeqN-Login.php" class="btn-secondary">Log In</a>
                    </div>
                </div>

                <div class="hero-dashboard-wrapper glass-card">
                    <div class="hero-dashboard">
                        <!-- Sidebar Layer -->
                        <div class="glass-layer mock-sidebar" style="--tz: 20px;">
                            <div class="mock-logo"></div>
                            <div class="mock-nav-item active"></div>
                            <div class="mock-nav-item"></div>
                            <div class="mock-nav-item"></div>
                            <div class="mock-nav-item mt-auto"></div>
                        </div>

                        <!-- Main Content -->
                        <div class="mock-main">
                            <!-- Top Ribbon Layer -->
                            <div class="glass-layer mock-header" style="--tz: 40px;">
                                <div class="mock-title">Patient Profile — Baseline</div>
                                <div class="mock-badge">Active</div>
                            </div>

                            <!-- Stats Grid Layer -->
                            <div class="mock-stats">
                                <div class="glass-layer mock-stat-card" style="--tz: 60px;">
                                    <div class="mock-icon primary"></div>
                                    <div class="mock-stat-val">2,150 kcal</div>
                                    <div class="mock-stat-label">Daily Target</div>
                                </div>
                                <div class="glass-layer mock-stat-card" style="--tz: 60px;">
                                    <div class="mock-icon secondary"></div>
                                    <div class="mock-stat-val" style="width: 50%;">145g</div>
                                    <div class="mock-stat-label" style="width: 70%;">Protein</div>
                                </div>
                                <div class="glass-layer mock-stat-card" style="--tz: 60px;">
                                    <div class="mock-icon accent"></div>
                                    <div class="mock-stat-val" style="width: 40%;">85g</div>
                                    <div class="mock-stat-label" style="width: 60%;">Lipids</div>
                                </div>
                                <div class="glass-layer mock-stat-card" style="--tz: 60px;">
                                    <div class="mock-icon danger"></div>
                                    <div class="mock-stat-val" style="width: 70%;">210g</div>
                                    <div class="mock-stat-label" style="width: 90%;">Carbohydrates</div>
                                </div>
                            </div>

                            <!-- Chart & Table Layer -->
                            <div class="mock-content-row">
                                <div class="glass-layer mock-chart" style="--tz: 50px;">
                                    <div class="mock-chart-title">Macro Distribution</div>
                                    <div class="mock-chart-bars">
                                        <div class="bar" style="height: 40%;"></div>
                                        <div class="bar" style="height: 70%;"></div>
                                        <div class="bar" style="height: 50%;"></div>
                                        <div class="bar" style="height: 90%;"></div>
                                        <div class="bar" style="height: 60%;"></div>
                                    </div>
                                </div>
                                <div class="glass-layer mock-feed" style="--tz: 80px;">
                                    <div class="mock-feed-title">Clinical Log</div>
                                    <div class="mock-feed-item">
                                        <div class="mock-avatar"></div>
                                        <div class="mock-feed-text">
                                            <div class="line1" style="width:90%">Dietary adjustment logged</div>
                                            <div class="line2">Dr. Reyes - 2m ago</div>
                                        </div>
                                    </div>
                                    <div class="mock-feed-item">
                                        <div class="mock-avatar"></div>
                                        <div class="mock-feed-text">
                                            <div class="line1" style="width: 60%;">BMI updated</div>
                                            <div class="line2" style="width: 40%;">System - 1h ago</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- The Core Engine: What is NutriDeq? -->
            <section class="philosophy-section">
                <div class="schematic-grid"></div>
                <div class="container">
                    <div class="section-header center">
                        <h2 class="section-title">The Science Behind the Standard</h2>
                        <p class="section-subtitle">NutriDeq is more than a name; it's a clinical formula designed to bridge the gap between biological science and modern care.</p>
                    </div>

                    <div class="philosophy-deconstruction">
                        <div class="philosophy-side left" id="nutri-side">
                            <div class="p-card schematic-card">
                                <div class="card-scanner"></div>
                                <div class="card-edge-tag">BIO_CORE</div>
                                <span class="p-prefix">[ PART_01 ]</span>
                                <h3>NUTRI</h3>
                                <div class="p-divider"></div>
                                <p><strong>The "Fuel" for the System.</strong> Nutri represents the biological foundation—your body and the food that powers it. We use rigorous science to ensure your nutrition plan is built on absolute clinical truth.</p>
                                <ul class="p-list">
                                    <li><span>•</span> Real-World Food Data</li>
                                    <li><span>•</span> Biological Needs Mapping</li>
                                    <li><span>•</span> Science-First Approach</li>
                                </ul>
                            </div>
                        </div>

                        <div class="philosophy-core">
                            <div class="core-logo">
                                <div class="connector-line left-line"><div class="flow-particle"></div></div>
                                <div class="connector-line right-line"><div class="flow-particle"></div></div>
                                <div class="core-ring"></div>
                                <div class="core-ring"></div>
                                <div class="core-center">
                                    <svg viewBox="0 0 240 240" class="core-logo-svg">
                                        <path d="M120 40 L165 90 L120 135 L75 90 Z" fill="var(--emerald)" />
                                        <path d="M75 90 L120 135 L100 190 L55 135 Z" fill="var(--emerald-deep)" />
                                        <path d="M165 90 L185 135 L140 190 L120 135 Z" fill="#34d399" />
                                        <path d="M120 40 L135 70 L120 85 L105 70 Z" fill="white" opacity="0.3" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="philosophy-side right" id="deq-side">
                            <div class="p-card schematic-card">
                                <div class="card-scanner"></div>
                                <div class="card-edge-tag">LOGIC_CORE</div>
                                <span class="p-prefix">[ PART_02 ]</span>
                                <h3>DEQ</h3>
                                <div class="p-divider"></div>
                                <p><strong>The "Brain" of the System.</strong> DEQ stands for <strong>Data, Equity, and Quantification</strong>—the technology that makes premium healthcare smarter, fairer, and measurable for everyone.</p>
                                <ul class="p-list">
                                    <li><span>•</span> <strong>Data:</strong> Smart tracking technology.</li>
                                    <li><span>•</span> <strong>Equity:</strong> Fair care for every patient.</li>
                                    <li><span>•</span> <strong>Quantification:</strong> Measuring real results.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- DOST-FCT Engine Section -->
            <section id="data" class="panel">
                <div class="section-header">
                    <h2 class="section-title">Powered by Official FCT Data</h2>
                    <p class="section-subtitle">
                        NutriDeq integrates the official Philippine Food Composition Tables (FCT) directly into your workflow. Calculate complex macronutrient profiles, therapeutic exchanges, and micronutrient thresholds with instantaneous, verifiable precision.
                    </p>
                </div>
                <div class="data-container">
                    <div class="fct-reactor">
                        <svg class="reactor-links" viewBox="0 0 400 400">
                            <!-- Connecting lines -->
                            <line class="reactor-line" x1="200" y1="200" x2="120" y2="80" stroke="var(--emerald)" stroke-width="2" stroke-dasharray="4 4" opacity="0.4"/>
                            <line class="reactor-line" x1="200" y1="200" x2="300" y2="100" stroke="var(--emerald)" stroke-width="2" stroke-dasharray="4 4" opacity="0.4"/>
                            <line class="reactor-line" x1="200" y1="200" x2="320" y2="280" stroke="var(--emerald)" stroke-width="2" stroke-dasharray="4 4" opacity="0.4"/>
                            <line class="reactor-line" x1="200" y1="200" x2="100" y2="280" stroke="var(--emerald)" stroke-width="2" stroke-dasharray="4 4" opacity="0.4"/>
                            <line class="reactor-line" x1="200" y1="200" x2="180" y2="340" stroke="var(--emerald)" stroke-width="2" stroke-dasharray="4 4" opacity="0.4"/>
                        </svg>
                        
                        <div class="reactor-core-center">
                            <div class="core-pulse"></div>
                            <div class="core-text">FCT<br><span>CORE</span></div>
                        </div>

                        <!-- Orbiting Nodes -->
                        <div class="reactor-node n1" style="top: 80px; left: 120px;">
                            <div class="node-orb" style="background: #10b981; box-shadow: 0 0 20px #10b981;"></div>
                            <div class="node-label">Protein<br><span>145g</span></div>
                        </div>
                        <div class="reactor-node n2" style="top: 100px; left: 300px;">
                            <div class="node-orb" style="background: #34d399; box-shadow: 0 0 20px #34d399;"></div>
                            <div class="node-label">Carbs<br><span>210g</span></div>
                        </div>
                        <div class="reactor-node n3" style="top: 280px; left: 320px;">
                            <div class="node-orb" style="background: #059669; box-shadow: 0 0 20px #059669;"></div>
                            <div class="node-label">Lipids<br><span>85g</span></div>
                        </div>
                        <div class="reactor-node n4" style="top: 280px; left: 100px;">
                            <div class="node-orb" style="background: #047857; box-shadow: 0 0 20px #047857;"></div>
                            <div class="node-label">Iron<br><span>18mg</span></div>
                        </div>
                        <div class="reactor-node n5" style="top: 340px; left: 180px;">
                            <div class="node-orb" style="background: #6ee7b7; box-shadow: 0 0 20px #6ee7b7;"></div>
                            <div class="node-label">Calcium<br><span>1.2g</span></div>
                        </div>
                    </div>
                    <div class="data-specs">
                        <div class="spec-item glass-layer" style="--tz: 20px;">
                            <h3>Automated Therapeutic Exchanges</h3>
                            <p>Instantly translate macro targets into standardized, culturally appropriate food exchanges for specialized renal, diabetic, or hypertensive diets.</p>
                        </div>
                        <div class="spec-item glass-layer" style="--tz: 30px;">
                            <h3>Micronutrient Telemetry</h3>
                            <p>Track essential trace minerals and vitamins with high-fidelity visualization to prevent clinical deficiencies.</p>
                        </div>
                        <div class="spec-item glass-layer" style="--tz: 40px;">
                            <h3>Clinical Protocol Validation</h3>
                            <p>Receive automated, real-time feedback when prescribed care plans deviate from established therapeutic thresholds.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Psychometrics Section -->
            <section id="psychometrics" class="panel">
                <div class="section-header">
                    <h2 class="section-title">Sentiment-Aware Clinical Intelligence</h2>
                    <p class="section-subtitle">
                        NutriDeq's NLP engine proactively monitors patient psychometrics. By decoding natural language patterns, our platform detects shifts in mood, motivation, and psychological readiness, allowing clinicians to tailor therapeutic adjustments before adherence declines.
                    </p>
                </div>
                
                <div class="psych-visual-container">
                    <!-- 2.5D Mood Matrix Card -->
                    <div class="mood-matrix glass-layer" style="--tz: 30px;">
                        <div class="matrix-grid">
                            <div class="matrix-cell high-engagement">
                                <span class="cell-label">STABILITY</span>
                                <div class="cell-visual pulse"></div>
                            </div>
                            <div class="matrix-cell"></div>
                            <div class="matrix-cell stress-detected">
                                <span class="cell-label">ANXIETY</span>
                                <div class="cell-visual alert"></div>
                            </div>
                            <div class="matrix-cell"></div>
                            <div class="matrix-cell core-nlp">
                                <div class="nlp-brain-icon">
                                    <svg viewBox="0 0 100 100">
                                        <path d="M50 20 C30 20 20 40 20 60 C20 80 40 85 50 85 C60 85 80 80 80 60 C80 40 70 20 50 20 Z" fill="none" stroke="var(--emerald)" stroke-width="2" />
                                        <circle cx="50" cy="50" r="10" fill="var(--emerald)" />
                                        <path d="M50 35 L50 65 M35 50 L65 50" stroke="var(--emerald)" stroke-width="1" />
                                    </svg>
                                </div>
                            </div>
                            <div class="matrix-cell"></div>
                            <div class="matrix-cell motivated">
                                <span class="cell-label">EFFICACY</span>
                                <div class="cell-visual glow"></div>
                            </div>
                            <div class="matrix-cell"></div>
                            <div class="matrix-cell fatigue">
                                <span class="cell-label">STRESS</span>
                                <div class="cell-visual flicker"></div>
                            </div>
                        </div>
                        <div class="matrix-readout">
                            <div class="nlp-telemetry">
                                <span class="telemetry-label">Analysis Confidence</span>
                                <span class="telemetry-value">98.2%</span>
                            </div>
                            <div class="nlp-telemetry">
                                <span class="telemetry-label">Dominant Vector</span>
                                <span class="telemetry-value text-emerald">Positive / High Readiness</span>
                            </div>
                        </div>
                    </div>

                    <!-- Floating Parallax Tokens -->
                    <div class="emotion-token et-1 glass-layer" style="--tz: 80px;">
                        <span class="token-icon">◈</span>
                        <span class="token-text">Sentiment Flux: Detected</span>
                    </div>
                    <div class="emotion-token et-2 glass-layer" style="--tz: 120px;">
                        <span class="token-icon">◎</span>
                        <span class="token-text">Motivation Index: 0.88</span>
                    </div>
                    <div class="emotion-token et-3 glass-layer" style="--tz: 60px;">
                        <span class="token-icon">⚡</span>
                        <span class="token-text">Cognitive Load: Low</span>
                    </div>
                    
                    <!-- Sentiment Spectrum Scroll-Based -->
                    <div class="sentiment-spectrum-wrapper glass-layer" style="--tz: 15px;">
                        <div class="spectrum-line"></div>
                        <div class="spectrum-indicator"></div>
                        <div class="spectrum-labels">
                            <span>Depressive Shift</span>
                            <span>Clinical Neutral</span>
                            <span>Peak Readiness</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Architecture Bento Grid -->
            <section id="features" class="panel">
                <div class="section-header">
                    <h2 class="section-title">Enterprise-Grade Architecture</h2>
                    <p class="section-subtitle">
                        Engineered with modern, high-performance web technologies to ensure uncompromising speed, HIPAA-compliant security, and seamless scalability for clinical teams of any size.
                    </p>
                </div>
                <div class="bento-grid">
                    <!-- 1. Biometrics (Large) -->
                    <div class="bento-item glass-card large">
                        <div class="bento-telemetry corner-tr"><div class="telemetry-dot"></div>BIO_LINK: STABLE</div>
                        <div class="bento-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
                            </svg>
                        </div>
                        <div class="bento-content">
                            <h3>Clinical Anthropometrics</h3>
                            <p>Precision monitoring of patient body composition. Automated BMI calculations, weight trajectory analysis, and growth monitoring optimized for therapeutic care.</p>
                        </div>
                        
                        <div class="premium-health-display">
                            <!-- BMI Focus -->
                            <div class="main-stat-focus">
                                <div class="stat-scan-line"></div>
                                <div class="stat-value-group">
                                    <span class="v-label">CURRENT BMI</span>
                                    <span class="v-main">24.2</span>
                                    <span class="v-unit">HEALTHY RANGE</span>
                                </div>
                            </div>

                            <!-- BMI Status Scale -->
                            <div class="bmi-scale-container">
                                <div class="bmi-labels">
                                    <span>18.5</span>
                                    <span>25.0</span>
                                    <span>30.0</span>
                                </div>
                                <div class="bmi-bar">
                                    <div class="bmi-zone underweight"></div>
                                    <div class="bmi-zone normal"></div>
                                    <div class="bmi-zone overweight"></div>
                                    <div class="bmi-zone obese"></div>
                                    <div class="bmi-marker" style="left: 42%"></div>
                                </div>
                                <div class="bmi-status">STATUS: OPTIMAL</div>
                            </div>

                            <!-- Metrics Row -->
                            <div class="metrics-grid-compact">
                                <div class="compact-stat">
                                    <span class="c-label">WEIGHT</span>
                                    <span class="c-val">68.5 kg</span>
                                    <div class="c-trend up">↑ 0.2</div>
                                </div>
                                <div class="compact-stat">
                                    <span class="c-label">WAIST</span>
                                    <span class="c-val">82 cm</span>
                                    <div class="c-trend stable">~ 0.0</div>
                                </div>
                                <div class="compact-stat">
                                    <span class="c-label">TARGET</span>
                                    <span class="c-val">64.0 kg</span>
                                    <div class="c-trend down">↓ 4.5</div>
                                </div>
                            </div>

                            <!-- Weight Trajectory Chart -->
                            <div class="trajectory-chart">
                                <svg viewBox="0 0 400 100" preserveAspectRatio="none">
                                    <path class="trajectory-path" d="M0,80 L50,75 L100,78 L150,70 L200,65 L250,68 L300,62 L350,58 L400,55" fill="none" stroke="var(--emerald)" stroke-width="3" stroke-linecap="round" />
                                    <circle cx="400" cy="55" r="4" fill="var(--emerald)" />
                                </svg>
                                <span class="chart-label">WEIGHT TRAJECTORY (6MO)</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Security -->
                    <div class="bento-item glass-card">
                        <div class="bento-telemetry corner-tr"><div class="telemetry-dot"></div>SECURE: AES_256</div>
                        <div class="bento-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            </svg>
                        </div>
                        <div class="bento-content">
                            <h3>Enterprise Security</h3>
                            <p>Uncompromising data protection. Patient records are shielded by end-to-end encryption and role-based access controls.</p>
                        </div>
                        <div class="laser-scanner"></div>
                    </div>

                    <!-- 3. AI Insights -->
                    <div class="bento-item glass-card">
                        <div class="bento-telemetry corner-tr"><div class="telemetry-dot"></div>AI: NEURAL_CORE</div>
                        <div class="bento-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
                            </svg>
                        </div>
                        <div class="bento-content">
                            <h3>Nutrient Precision</h3>
                            <p>Automated nutrient mapping using DOST-FCT integration to precisely calculate macro and micronutrient requirements.</p>
                        </div>
                        <div class="neural-web-container" id="neural-visualizer"></div>
                    </div>

                    <!-- 4. Documentation (Tall) -->
                    <div class="bento-item glass-card tall">
                        <div class="bento-telemetry corner-tr"><div class="telemetry-dot"></div>PDF: AUTOMATED</div>
                        <div class="bento-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 22h14a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v4" />
                                <polyline points="14 2 14 8 20 8" />
                            </svg>
                        </div>
                        <div class="bento-content">
                            <h3>Clinical Reports</h3>
                            <p>Generate professional, patient-ready therapeutic reports and dietary care instructions in a single click.</p>
                        </div>
                        <div class="interactive-stack">
                            <div class="mock-doc d3"><span>Nutrition</span></div>
                            <div class="mock-doc d2"><span>Anthropometrics</span></div>
                            <div class="mock-doc d1"><span>Care Plan</span></div>
                        </div>
                    </div>

                    <!-- 5. Hospital Sync -->
                    <div class="bento-item glass-card">
                        <div class="bento-telemetry corner-tr"><div class="telemetry-dot"></div>SYNC: HL7_FHIR</div>
                        <div class="bento-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
                            </svg>
                        </div>
                        <div class="bento-content">
                            <h3>Unified Workflow</h3>
                            <p>Seamless data flow between dietary libraries, patient profiles, and care calculators for a friction-less experience.</p>
                        </div>
                    </div>

                    <!-- 6. Instant Search -->
                    <div class="bento-item glass-card">
                        <div class="bento-telemetry corner-tr"><div class="telemetry-dot"></div>SPEED: &lt;1ms</div>
                        <div class="bento-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                        </div>
                        <div class="bento-content">
                            <h3>Instant FCT Search</h3>
                            <p>Find clinical nutrition data and official food composition entries in the blink of an eye.</p>
                        </div>
                        <div class="data-stream-container">
                            <div class="data-particle dp1"></div>
                            <div class="data-particle dp2"></div>
                            <div class="data-particle dp3"></div>
                        </div>
                    </div>

                    <!-- 7. Custom Workflows (New) -->
                    <div class="bento-item glass-card">
                        <div class="bento-telemetry corner-tr"><div class="telemetry-dot"></div>FLOW: ADAPTIVE</div>
                        <div class="bento-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v20M2 12h20M5 5l14 14M19 5L5 14" />
                            </svg>
                        </div>
                        <div class="bento-content">
                            <h3>Smart Workflows</h3>
                            <p>Customize your clinical path with automated tasks that adapt to your team's specific needs.</p>
                        </div>
                        <div class="workflow-nodes">
                            <div class="wf-line"></div>
                            <div class="wf-node"><svg width="12" height="12" viewBox="0 0 24 24" fill="var(--emerald)"><circle cx="12" cy="12" r="10"/></svg></div>
                            <div class="wf-node"><svg width="12" height="12" viewBox="0 0 24 24" fill="var(--emerald)"><circle cx="12" cy="12" r="10"/></svg></div>
                            <div class="wf-node"><svg width="12" height="12" viewBox="0 0 24 24" fill="var(--emerald)"><circle cx="12" cy="12" r="10"/></svg></div>
                        </div>
                    </div>
                </div>
            </section>

                    <div class="mission-statement">
                        <p>"NutriDeq is not just a tool; it is the <strong>Clinical Standard</strong> for the future of therapeutic care planning."</p>
                    </div>

            <footer>
                <div class="footer-top">
                    <div class="footer-logo">
                        <svg viewBox="0 0 240 240" class="footer-logo-svg">
                            <path d="M120 40 L165 90 L120 135 L75 90 Z" fill="var(--emerald)" />
                            <path d="M75 90 L120 135 L100 190 L55 135 Z" fill="var(--emerald-deep)" />
                            <path d="M165 90 L185 135 L140 190 L120 135 Z" fill="#34d399" />
                            <path d="M120 40 L135 70 L120 85 L105 70 Z" fill="white" opacity="0.3" />
                        </svg>
                        Nutri<span>Deq</span>
                    </div>
                    <div class="footer-links">
                        <a href="fct-library.php">FCT Library</a>
                        <a href="food-exchange.php">Food Exchange Tools</a>
                        <a href="dashboard.php">Clinical Dashboard</a>
                        <a href="#">Support</a>
                    </div>
                </div>
                <div class="footer-bottom">
                    <p>Powered by Official DOST-FNRI Philippine Food Composition Data. Empowering dietitians with precise, accessible care planning.</p>
                    <p>&copy; 2026 NutriDeq Clinical System. All rights reserved.</p>
                </div>
            </footer>

        </div>
    </main>

    <script src="assets/js/landing-animations.js"></script>
    <script src="scripts/interactive-effects.js"></script>
    <script src="scripts/particle-engine.js"></script>
</body>

</html>