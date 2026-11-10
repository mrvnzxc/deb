<?php
// LOG IN PAGE (landing page)
require __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect(home_for($_SESSION['role']));
}

$error = '';
$email = '';
$role  = 'customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'customer';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['role'] !== $role) {
            $error = 'This email is not registered as ' . ($role === 'admin' ? 'an admin' : 'a customer') . ' account.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is inactive. Please contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['full_name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            redirect(home_for($user['role']));
        }
    }
}

// Live numbers for the landing page
$stats = [
    'terminals' => (int)$pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn(),
    'buses'     => (int)$pdo->query("SELECT COUNT(*) FROM buses WHERE status = 'available'")->fetchColumn(),
    'trips'     => (int)$pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'open'")->fetchColumn(),
];

// Background picture: drop your own photo at assets/img/terminal.jpg to replace the illustration
$bgImage = file_exists(__DIR__ . '/assets/img/terminal.jpg') ? 'assets/img/terminal.jpg' : 'assets/img/terminal.svg';

$title = 'Log In';
include __DIR__ . '/includes/header.php';
?>
<!-- Background: terminal picture + gradient overlay -->
<div class="landing-bg" style="background-image:url('<?= e(url($bgImage)) ?>')" aria-hidden="true"></div>
<div class="landing-overlay" aria-hidden="true"></div>

<!-- Top bar -->
<header class="landing-top position-relative">
  <div class="container d-flex align-items-center justify-content-between py-3">
    <a href="<?= url('index.php') ?>" class="d-flex align-items-center gap-2 text-white text-decoration-none fw-bold fs-5">
      <span class="brand-icon"><i class="bi bi-bus-front-fill"></i></span> Bus Ticket Booking
    </a>
    <a href="<?= url('register.php') ?>" class="btn btn-warning btn-sm fw-semibold rounded-pill px-3">
      <i class="bi bi-person-plus-fill me-1"></i><span class="d-none d-sm-inline">Create account</span><span class="d-sm-none">Register</span>
    </a>
  </div>
</header>

<main class="auth-page landing-page d-flex align-items-center pb-5">
  <div class="container">
    <div class="row justify-content-center align-items-center gx-lg-5 gy-4">

      <!-- Hero (large screens) -->
      <div class="col-lg-6 d-none d-lg-block text-white">
        <span class="hero-badge d-inline-flex align-items-center gap-2 rounded-pill px-3 py-1 small mb-4">
          <i class="bi bi-lightning-charge-fill text-warning"></i> Fast &amp; secure online booking
        </span>
        <h1 class="hero-title display-4 mb-3">Your next trip is<br>just a <span class="text-warning">few clicks</span> away.</h1>
        <p class="lead text-white-75 mb-4">
          <i class="bi bi-geo-alt-fill text-warning me-1"></i>Find available buses, reserve your seats, and get your e-ticket instantly.
        </p>

        <!-- Live stats -->
        <div class="d-flex flex-wrap gap-3 mb-5">
          <div class="stat-pill"><i class="bi bi-building"></i><div><strong><?= $stats['terminals'] ?></strong><span>Terminals</span></div></div>
          <div class="stat-pill"><i class="bi bi-bus-front"></i><div><strong><?= $stats['buses'] ?></strong><span>Buses</span></div></div>
          <div class="stat-pill"><i class="bi bi-calendar-check"></i><div><strong><?= $stats['trips'] ?></strong><span>Open trips</span></div></div>
        </div>

        <div class="row g-4">
          <div class="col-6 d-flex align-items-start gap-3">
            <span class="feature-icon"><i class="bi bi-search"></i></span>
            <div><div class="fw-semibold">Find a bus</div><div class="small text-white-75">Search by origin, destination and date.</div></div>
          </div>
          <div class="col-6 d-flex align-items-start gap-3">
            <span class="feature-icon"><i class="bi bi-person-workspace"></i></span>
            <div><div class="fw-semibold">Pick your seats</div><div class="small text-white-75">See how many seats are left in real time.</div></div>
          </div>
          <div class="col-6 d-flex align-items-start gap-3">
            <span class="feature-icon"><i class="bi bi-wallet2"></i></span>
            <div><div class="fw-semibold">Pay your way</div><div class="small text-white-75">GCash or credit/debit card.</div></div>
          </div>
          <div class="col-6 d-flex align-items-start gap-3">
            <span class="feature-icon"><i class="bi bi-qr-code"></i></span>
            <div><div class="fw-semibold">Instant e-ticket</div><div class="small text-white-75">View and print your ticket anytime.</div></div>
          </div>
        </div>
      </div>

      <!-- Login card -->
      <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card auth-card border-0 shadow-lg">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <span class="auth-logo mb-3"><i class="bi bi-bus-front-fill"></i></span>
              <h1 class="h4 fw-bold mb-1">Bus Ticket Booking</h1>
              <p class="text-secondary mb-0">Log in to your account</p>
            </div>

            <?php show_flash(); ?>
            <?php if ($error): ?>
              <div class="alert alert-danger d-flex align-items-center gap-2 py-2"><i class="bi bi-exclamation-triangle-fill"></i><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
              <?= csrf_field() ?>
              <input type="hidden" name="role" id="role" value="<?= e($role) ?>">

              <div class="nav nav-pills nav-fill role-switch mb-4" role="group" aria-label="Account type">
                <button type="button" class="nav-link <?= $role === 'customer' ? 'active' : '' ?>" data-role="customer"><i class="bi bi-person me-1"></i>Customer</button>
                <button type="button" class="nav-link <?= $role === 'admin' ? 'active' : '' ?>" data-role="admin"><i class="bi bi-shield-lock me-1"></i>Admin</button>
              </div>

              <div class="mb-3">
                <label for="email" class="form-label fw-semibold small">Email</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" required>
                </div>
              </div>
              <div class="mb-2">
                <label for="password" class="form-label fw-semibold small">Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" class="form-control" id="password" name="password" placeholder="Your password" required>
                  <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password" title="Show password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>
              </div>
              <div class="text-end mb-4">
                <a href="<?= url('forgot_password.php') ?>" class="small text-decoration-none">Forgot password?</a>
              </div>

              <button type="submit" class="btn btn-gradient btn-lg w-100"><i class="bi bi-box-arrow-in-right me-2"></i>Log In</button>
            </form>

            <p class="text-center text-secondary small mt-4 mb-0" id="register-link" style="<?= $role === 'admin' ? 'display:none' : '' ?>">
              No account yet? <a href="<?= url('register.php') ?>" class="fw-semibold text-decoration-none">Register here</a>
            </p>
          </div>
          <div class="card-footer bg-transparent border-0 pb-4 px-3 pt-0">
            <div class="d-flex justify-content-center flex-wrap gap-2 small text-secondary">
              <span class="trust-chip"><i class="bi bi-phone"></i>GCash</span>
              <span class="trust-chip"><i class="bi bi-credit-card"></i>Card</span>
              <span class="trust-chip"><i class="bi bi-shield-lock"></i>Secure</span>
              <span class="trust-chip"><i class="bi bi-printer"></i>Printable</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<footer class="landing-foot position-relative text-center small py-3">
  <i class="bi bi-bus-front me-1"></i>&copy; <?= date('Y') ?> Bus Ticket Booking
  <span class="mx-2">&middot;</span><i class="bi bi-clock me-1"></i>Book anytime, 24/7
</footer>

<script>
document.querySelectorAll('.role-switch .nav-link').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('.role-switch .nav-link').forEach(function (t) { t.classList.remove('active'); });
    tab.classList.add('active');
    document.getElementById('role').value = tab.dataset.role;
    document.getElementById('register-link').style.display = tab.dataset.role === 'admin' ? 'none' : '';
  });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
