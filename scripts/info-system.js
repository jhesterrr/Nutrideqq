// scripts/info-system.js
// NutriDeq Global Feature Knowledge System
(function () {
    'use strict';

    const FEATURE_KNOWLEDGE = {
        // ADMIN FEATURES
        'system_efficiency': {
            title: 'System Efficiency',
            purpose: 'Monitor Platform Health',
            description: 'This real-time gauge tracks the overall activity of the platform. It calculates the ratio of active logs versus total users. A high score means your community is actively tracking and engaged.'
        },
        'staff_performance': {
            title: 'Staff Influence',
            purpose: 'Assess Dietitian Engagement',
            description: 'This metric measures how well your staff is interacting with their assigned clients. It analyzes message response times and diary feedback frequency to give a performance score.'
        },
        'recent_activity': {
            title: 'Live Activity Feed',
            purpose: 'Real-time System Audit',
            description: 'A dedicated stream of every action happening on NutriDeq—from new client signups to clinical food logs. This ensures you always have a pulse on the system.'
        },

        // USER FEATURES
        'nutritional_snap': {
            title: 'Nutritional Daily Snap',
            purpose: 'Macro-Target Achievement',
            description: 'Your daily health goals represented as progress rings. Complete the rings by logging your food. Once all rings are closed, you have hit your nutritional targets for the day!'
        },
        'hydration_flow': {
            title: 'Daily Hydration Flow',
            purpose: 'Hydration Tracking',
            description: 'Hydration is critical for energy and metabolism. Tap the + button every time you drink a glass of water. Aim to fill the blue wave (8 glasses) for optimal health.'
        },
        'weekly_report': {
            title: 'Clinical PDF Report',
            purpose: 'Professional Documentation',
            description: 'Generate a professional-grade clinical report of your week. This PDF summarizes your trends, macros, and dietitian feedback, perfect for sharing with your doctor.'
        },

        // STAFF FEATURES
        'client_progress': {
            title: 'Client Diary Monitor',
            purpose: 'Clinical Oversight',
            description: 'A deep-dive view into a client\'s daily life. Analyze their meal choices, calories, and macro distribution in real-time to provide better dietary advice.'
        }
    };

    window.showFeatureInfo = function (featureKey) {
        const info = FEATURE_KNOWLEDGE[featureKey];
        if (!info) return;

        // Create Modal if it doesn't exist
        let modal = document.getElementById('featureInfoModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'featureInfoModal';
            modal.className = 'info-modal-overlay';
            modal.innerHTML = `
                <div class="info-modal-card">
                    <div class="info-modal-header">
                        <div class="info-icon-circle"><i class="fas fa-info"></i></div>
                        <h2 id="infoTitle"></h2>
                    </div>
                    <div class="info-modal-body">
                        <div class="info-purpose-tag" id="infoPurpose"></div>
                        <p id="infoDescription"></p>
                    </div>
                    <button class="info-close-btn" onclick="closeFeatureInfo()">Got it!</button>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // Fill Data
        document.getElementById('infoTitle').innerText = info.title;
        document.getElementById('infoPurpose').innerText = info.purpose;
        document.getElementById('infoDescription').innerText = info.description;

        // Show
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('visible'), 10);
    };

    window.closeFeatureInfo = function () {
        const modal = document.getElementById('featureInfoModal');
        if (modal) {
            modal.classList.remove('visible');
            setTimeout(() => modal.style.display = 'none', 300);
        }
    };

})();
