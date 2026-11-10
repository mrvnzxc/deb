<?php
// RESET PASSWORD - opened from the forgot password link
require __DIR__ . '/config.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$errors = [];

$st = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND is_used = 0 AND expires_at > NOW()');
$st->execute([$token]);
$reset = $st->fetch();

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
        $pdo->prepare('UPDATE password_resets SET is_used = 1 WHERE user_id = ?')
            ->execute([$reset['user_id']]);
        $pdo->commit();

        flash('success', 'Your password has been changed. You can now log in.');
        redirect('index.php');
    }
}

$title = 'Reset Password';
include __DIR__ . '/includes/header.php';
?>
<main class="auth-page d-flex align-items-center py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card auth-card border-0 shadow-lg">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <span class="auth-logo mb-3"><i class="bi bi-shield-lock-fill"></i></span>
              <h1 class="h4 fw-bold mb-1">Reset Password</h1>
            </div>

            <?php if (!$reset): ?>
              <div class="alert alert-danger d-flex align-items-center gap-2 py-2"><i class="bi bi-exclamation-triangle-fill"></i>This reset link is invalid or has expired.</div>
              <p class="text-center"><a href="<?= url('forgot_password.php') ?>" class="text-decoration-none"><i class="bi bi-arrow-repeat me-1"></i>Request a new link</a></p>
            <?php else: ?>
              <p class="text-secondary text-center">Enter your new password.</p>
              <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2"><i class="bi bi-exclamation-triangle-fill"></i><?= e($err) ?></div>
              <?php endforeach; ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="mb-3">
                  <label for="password" class="form-label fw-semibold small">New Password</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password" title="Show password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                  </div>
                </div>
                <div class="mb-4">
                  <label for="confirm_password" class="form-label fw-semibold small">Confirm New Password</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="#confirm_password" title="Show password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                  </div>
                </div>
                <button type="submit" class="btn btn-gradient btn-lg w-100"><i class="bi bi-check2-circle me-2"></i>Change Password</button>
              </form>
            <?php endif; ?>

            <p class="text-center small mt-4 mb-0"><a href="<?= url('index.php') ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to log in</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
