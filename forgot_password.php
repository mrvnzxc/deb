<?php
// FORGOT PASSWORD - creates a reset link
require __DIR__ . '/config.php';

$message   = '';
$resetLink = '';
$email     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'error|Please enter a valid email address.';
    } else {
        $st = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND status = 'active'");
        $st->execute([$email]);
        $user = $st->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $st = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
            $st->execute([$user['user_id'], $token]);

            // Build the full reset URL
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $resetLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('reset_password.php?token=' . $token);

            // NOTE: In a live system this link is emailed to the user (e.g. with PHPMailer).
            // On localhost there is no mail server, so it is shown on screen instead.
        }
        $message = 'success|If that email is registered, a password reset link has been created. It is valid for 30 minutes.';
    }
}

$title = 'Forgot Password';
include __DIR__ . '/includes/header.php';
?>
<main class="auth-page d-flex align-items-center py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card auth-card border-0 shadow-lg">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <span class="auth-logo mb-3"><i class="bi bi-key-fill"></i></span>
              <h1 class="h4 fw-bold mb-1">Forgot Password</h1>
              <p class="text-secondary mb-0">Enter your email and we'll create a reset link.</p>
            </div>

            <?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
              <div class="alert alert-<?= $type === 'error' ? 'danger' : e($type) ?> d-flex align-items-center gap-2 py-2">
                <i class="bi <?= $type === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' ?>"></i><?= e($text) ?>
              </div>
            <?php endif; ?>

            <?php if ($resetLink): ?>
              <div class="alert alert-info small">
                <strong><i class="bi bi-info-circle me-1"></i>Demo mode (no email server):</strong><br>
                <a href="<?= e($resetLink) ?>">Click here to reset your password</a>
              </div>
            <?php endif; ?>

            <form method="post">
              <?= csrf_field() ?>
              <div class="mb-4">
                <label for="email" class="form-label fw-semibold small">Email</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required>
                </div>
              </div>
              <button type="submit" class="btn btn-gradient btn-lg w-100"><i class="bi bi-send me-2"></i>Send Reset Link</button>
            </form>

            <p class="text-center small mt-4 mb-0"><a href="<?= url('index.php') ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to log in</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
