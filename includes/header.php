<?php
// Shared page header. Set $title before including.
// $nav = 'admin' | 'customer' | null (null = login/register pages, styled with Bootstrap)
// $active = key of the current menu item
$nav    = $nav ?? null;
$active = $active ?? '';

// key => [page, label, Bootstrap Icon]
$menus = [
    'admin' => [
        'dashboard' => ['admin/dashboard.php', 'Dashboard', 'speedometer2'],
        'routes'    => ['admin/routes.php', 'Rates & Routes', 'signpost-split'],
        'buses'     => ['admin/buses.php', 'Buses', 'bus-front'],
        'trips'     => ['admin/schedules.php', 'Trips', 'calendar-event'],
        'bookings'  => ['admin/bookings.php', 'Bookings', 'journal-text'],
    ],
    'customer' => [
        'home'     => ['customer/dashboard.php', 'Find a Bus', 'search'],
        'bookings' => ['customer/bookings.php', 'My Bookings', 'ticket-perforated'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'Bus Ticket Booking') ?> | Bus Ticket Booking</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php if ($nav): ?>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/theme.css')) ?>">
<?php else: ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= e(url('assets/css/auth.css')) ?>">
<?php endif; ?>
</head>
<body class="<?= $nav ? '' : 'auth-body' ?>">
<?php if ($nav):
    // Initials for the avatar, e.g. "System Admin" -> "SA"
    $userName = $_SESSION['name'] ?? '';
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', trim($userName)), 0, 2) as $w) {
        $initials .= strtoupper(substr($w, 0, 1));
    }
?>
<nav class="navbar">
  <a class="brand" href="<?= e(url(home_for($nav))) ?>">
    <span class="brand-icon"><i class="bi bi-bus-front-fill"></i></span>
    <span class="brand-text">Bus Ticket Booking</span>
    <?php if ($nav === 'admin'): ?><span class="brand-tag">Admin</span><?php endif; ?>
  </a>
  <button type="button" class="nav-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="nav-links"><i class="bi bi-list"></i></button>
  <div class="nav-links" id="nav-links">
    <?php foreach ($menus[$nav] as $key => $m): ?>
      <a href="<?= e(url($m[0])) ?>" class="<?= $active === $key ? 'active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><i class="bi bi-<?= e($m[2]) ?>"></i><?= e($m[1]) ?></a>
    <?php endforeach; ?>
    <div class="nav-user">
      <span class="avatar" aria-hidden="true"><?= e($initials ?: '?') ?></span>
      <span class="nav-user-text"><strong><?= e($userName) ?></strong><small><?= $nav === 'admin' ? 'Administrator' : 'Customer' ?></small></span>
      <a href="<?= url('logout.php') ?>" class="nav-logout" title="Log Out" aria-label="Log Out"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</nav>
<div class="container">
<?php show_flash(); ?>
<?php endif; ?>
