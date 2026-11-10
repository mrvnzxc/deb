<?php
// =====================================================
// General helper functions
// =====================================================

// Build a URL relative to the app root, e.g. url('admin/routes.php')
function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Redirect to a full URL, an absolute path, or a path relative to the app root
function redirect($url)
{
    if (!preg_match('#^(https?://|/)#i', $url)) {
        $url = url($url);
    }
    header('Location: ' . $url);
    exit;
}

function flash($type, $msg)
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function show_flash()
{
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $f) {
            echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
        }
        unset($_SESSION['flash']);
    }
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function check_csrf()
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Invalid request. Please go back, refresh the page and try again.');
    }
}

function peso($n)
{
    return '&#8369;' . number_format((float)$n, 2);
}

function fmt_dt($dt)
{
    return date('M d, Y h:i A', strtotime($dt));
}

function status_badge($status)
{
    $map = [
        'open' => 'success', 'confirmed' => 'success', 'paid' => 'success', 'available' => 'success', 'active' => 'success',
        'full' => 'warning', 'pending' => 'warning', 'maintenance' => 'warning',
        'cancelled' => 'danger', 'failed' => 'danger', 'unavailable' => 'danger', 'inactive' => 'danger',
        'departed' => 'muted', 'refunded' => 'info',
    ];
    $cls = $map[$status] ?? 'muted';
    return '<span class="badge badge-' . $cls . '">' . e(ucfirst($status)) . '</span>';
}

// Luhn check for card numbers
function luhn_valid($number)
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int)$number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}
