<?php
// =====================================================
// Authentication / access-control helpers
// =====================================================

// Landing page (relative to the app root) for each role
function home_for($role)
{
    return $role === 'admin' ? 'admin/dashboard.php' : 'customer/dashboard.php';
}

// Only allow logged-in users with the given role
function require_login($role = null)
{
    if (empty($_SESSION['user_id'])) {
        flash('error', 'Please log in first.');
        redirect('index.php');
    }
    if ($role !== null && $_SESSION['role'] !== $role) {
        redirect(home_for($_SESSION['role']));
    }
}
