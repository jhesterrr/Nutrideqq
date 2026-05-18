<?php
// navigation.php

function getNavigationLinks($user_role, $current_page = '')
{
    $nav_links = [];
    $user_role = strtolower(trim($user_role ?? ''));

    if ($user_role === 'admin') {
        $nav_links = [
            'header_main' => ['type' => 'header', 'text' => 'Main'],
            'dashboard.php' => [
                'href' => 'dashboard.php',
                'icon' => 'fas fa-home',
                'text' => 'Dashboard',
                'active' => ($current_page === 'dashboard.php' ? true : null)
            ],

            'header_comm' => ['type' => 'header', 'text' => 'Communication'],
            'admin-internal-messages.php' => [
                'href' => 'admin-internal-messages.php',
                'icon' => 'fas fa-headset',
                'text' => 'Internal Messages',
                'active' => ($current_page === 'admin-internal-messages.php' ? true : null)
            ],
            'staff-messages.php' => [
                'href' => 'staff-messages.php',
                'icon' => 'fas fa-envelope',
                'text' => 'Staff Messages',
                'active' => ($current_page === 'staff-messages.php' ? true : null)
            ],
            'staff-client-diary.php' => [
                'href' => 'staff-client-diary.php',
                'icon' => 'fas fa-book-open',
                'text' => 'Client Food Diaries',
                'active' => ($current_page === 'staff-client-diary.php' ? true : null)
            ],

            'header_mgmt' => ['type' => 'header', 'text' => 'Management'],
            'admin-client-management.php' => [
                'href' => 'admin-client-management.php',
                'icon' => 'fas fa-users',
                'text' => 'Client Management',
                'active' => ($current_page === 'admin-client-management.php' ? true : null)
            ],
            'admin-user-management.php' => [
                'href' => 'admin-user-management.php',
                'icon' => 'fas fa-users-cog',
                'text' => 'User Management',
                'active' => ($current_page === 'admin-user-management.php' ? true : null)
            ],
            'admin-staff-management.php' => [
                'href' => 'admin-staff-management.php',
                'icon' => 'fas fa-user-tie',
                'text' => 'Staff Management',
                'active' => ($current_page === 'admin-staff-management.php' ? true : null)
            ],

            'header_tools' => ['type' => 'header', 'text' => 'Tools'],
            'anthropometric-information.php' => [
                'href'   => 'anthropometric-information.php',
                'icon'   => 'fas fa-stethoscope',
                'text'   => 'Client Health Progress',
                'active' => ($current_page === 'anthropometric-information.php' ? true : null)
            ],
            'dietary-information.php' => [
                'href'   => 'dietary-information.php',
                'icon'   => 'fas fa-file-medical-alt',
                'text'   => 'Nutrient Breakdown',
                'active' => ($current_page === 'dietary-information.php' ? true : null)
            ],
            'Nutrition-Calculator.php' => [
                'href'   => 'Nutrition-Calculator.php',
                'icon'   => 'fas fa-calculator',
                'text'   => 'Calories & Weight',
                'active' => ($current_page === 'Nutrition-Calculator.php' ? true : null)
            ],
            'food-exchange.php' => [
                'href'   => 'food-exchange.php',
                'icon'   => 'fas fa-exchange-alt',
                'text'   => 'Food Exchanges',
                'active' => ($current_page === 'food-exchange.php' ? true : null)
            ],
            'fct-library.php' => [
                'href'   => 'fct-library.php',
                'icon'   => 'fas fa-book-medical',
                'text'   => 'Food Database',
                'active' => ($current_page === 'fct-library.php' ? true : null)
            ]
        ];
    } elseif ($user_role === 'staff') {
        $nav_links = [
            'header_main' => ['type' => 'header', 'text' => 'Main'],
            'dashboard.php' => [
                'href' => 'dashboard.php',
                'icon' => 'fas fa-home',
                'text' => 'Dashboard',
                'active' => ($current_page === 'dashboard.php' ? true : null)
            ],

            'header_comm' => ['type' => 'header', 'text' => 'Communication'],
            'staff-help.php' => [
                'href' => 'staff-help.php',
                'icon' => 'fas fa-headset',
                'text' => 'Help Center',
                'active' => ($current_page === 'staff-help.php' ? true : null)
            ],
            'staff-messages.php' => [
                'href' => 'staff-messages.php',
                'icon' => 'fas fa-envelope',
                'text' => 'Messages',
                'active' => ($current_page === 'staff-messages.php' ? true : null)
            ],
            'staff-client-diary.php' => [
                'href' => 'staff-client-diary.php',
                'icon' => 'fas fa-book-open',
                'text' => 'Client Diaries',
                'active' => ($current_page === 'staff-client-diary.php' ? true : null)
            ],

            'header_mgmt' => ['type' => 'header', 'text' => 'Management'],
            'user-management-staff.php' => [
                'href' => 'user-management-staff.php',
                'icon' => 'fas fa-users',
                'text' => 'Client Management',
                'active' => ($current_page === 'user-management-staff.php' ? true : null)
            ],

            'header_med_tools' => ['type' => 'header', 'text' => 'Nutrition Tools'],
            'anthropometric-information.php' => [
                'href'   => 'anthropometric-information.php',
                'icon'   => 'fas fa-stethoscope',
                'text'   => 'Client Health Progress',
                'active' => ($current_page === 'anthropometric-information.php' ? true : null)
            ],
            'dietary-information.php' => [
                'href'   => 'dietary-information.php',
                'icon'   => 'fas fa-file-medical-alt',
                'text'   => 'Nutrient Breakdown',
                'active' => ($current_page === 'dietary-information.php' ? true : null)
            ],
            'Nutrition-Calculator.php' => [
                'href'   => 'Nutrition-Calculator.php',
                'icon'   => 'fas fa-calculator',
                'text'   => 'Calories & Weight',
                'active' => ($current_page === 'Nutrition-Calculator.php' ? true : null)
            ],
            'food-exchange.php' => [
                'href'   => 'food-exchange.php',
                'icon'   => 'fas fa-exchange-alt',
                'text'   => 'Food Exchanges',
                'active' => ($current_page === 'food-exchange.php' ? true : null)
            ],
            'fct-library.php' => [
                'href'   => 'fct-library.php',
                'icon'   => 'fas fa-book-medical',
                'text'   => 'Food Database',
                'active' => ($current_page === 'fct-library.php' ? true : null)
            ]
        ];
    } else {
        // Regular user navigation
        $nav_links = [
            'header_main' => ['type' => 'header', 'text' => 'Main'],
            'dashboard.php' => [
                'href' => 'dashboard.php',
                'icon' => 'fas fa-home',
                'text' => 'Dashboard',
                'active' => ($current_page === 'dashboard.php' ? true : null)
            ],

            'header_tracker' => ['type' => 'header', 'text' => 'Health Tracking'],
            'user-health-tracker.php' => [
                'href' => 'user-health-tracker.php',
                'icon' => 'fas fa-heartbeat',
                'text' => 'My Health Tracking',
                'active' => ($current_page === 'user-health-tracker.php' ? true : null)
            ],
            'user-diary.php' => [
                'href' => 'user-diary.php',
                'icon' => 'fas fa-book-open',
                'text' => 'My Diary',
                'active' => ($current_page === 'user-diary.php' ? true : null)
            ],

            'header_comm' => ['type' => 'header', 'text' => 'Communication'],
            'user-messages.php' => [
                'href' => 'user-messages.php',
                'icon' => 'fas fa-comments',
                'text' => 'Messages',
                'active' => ($current_page === 'user-messages.php' ? true : null)
            ],

            'header_tools' => ['type' => 'header', 'text' => 'Tools'],
            'Nutrition-Calculator.php' => [
                'href' => 'Nutrition-Calculator.php',
                'icon' => 'fas fa-calculator',
                'text' => 'Calories & Weight',
                'active' => ($current_page === 'Nutrition-Calculator.php' ? true : null)
            ],
            'food-exchange.php' => [
                'href' => 'food-exchange.php',
                'icon' => 'fas fa-exchange-alt',
                'text' => 'Food Exchanges',
                'active' => ($current_page === 'food-exchange.php' ? true : null)
            ],
            'dietary-information.php' => [
                'href' => 'dietary-information.php',
                'icon' => 'fas fa-file-medical-alt',
                'text' => 'Nutrient Breakdown',
                'active' => ($current_page === 'dietary-information.php' ? true : null)
            ],
            'fct-library.php' => [
                'href' => 'fct-library.php',
                'icon' => 'fas fa-book-medical',
                'text' => 'Food Database',
                'active' => ($current_page === 'fct-library.php' ? true : null)
            ]
        ];
    }

    return $nav_links;
}

function getUserRoleText($role)
{
    switch ($role) {
        case 'admin':
            return 'System Administrator';
        case 'staff':
            return 'Dietician staff';
        default:
            return 'User';
    }
}
?>