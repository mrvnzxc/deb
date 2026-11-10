<?php
// CUSTOMER REGISTRATION (basic information only)
require __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect(home_for($_SESSION['role']));
}

$errors = [];
$data = ['full_name' => '', 'email' => '', 'contact_no' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $data['full_name']  = trim($_POST['full_name'] ?? '');
    $data['email']      = trim($_POST['email'] ?? '');
    $data['contact_no'] = trim($_POST['contact_no'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($data['full_name'] === '' || strlen($data['full_name']) > 100) $errors[] = 'Please enter your full name.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($data['contact_no'] !== '' && !preg_match('/^09\d{9}$/', $data['contact_no'])) $errors[] = 'Contact number must be 11 digits and start with 09.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $st = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $st->execute([$data['email']]);
        if ($st->fetch()) {
            $errors[] = 'This email is already registered. Please use a different email.';
        }
    }

    if (!$errors) {
        $st = $pdo->prepare("INSERT INTO users (full_name, email, contact_no, password, role) VALUES (?, ?, ?, ?, 'customer')");
        $st->execute([
            $data['full_name'],
            $data['email'],
            $data['contact_no'] !== '' ? $data['contact_no'] : null,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        flash('success', 'Registration successful! You can now log in.');
        redirect('index.php');
    }
}

$title = 'Register';
include __DIR__ . '/includes/header.php';
?>
<main class="auth-page d-flex align-items-center py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-7 col-lg-5">
        <div class="card auth-card border-0 shadow-lg">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <span class="auth-logo mb-3"><i class="bi bi-person-plus-fill"></i></span>
              <h1 class="h4 fw-bold mb-1">Create an Account</h1>
              <p class="text-secondary mb-0">Register as a customer</p>
            </div>

            <?php foreach ($errors as $err): ?>
              <div class="alert alert-danger d-flex align-items-center gap-2 py-2"><i class="bi bi-exclamation-triangle-fill"></i><?= e($err) ?></div>
            <?php endforeach; ?>

            <form method="post">
              <?= csrf_field() ?>
              <div class="mb-3">
                <label for="full_name" class="form-label fw-semibold small">Full Name</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-person"></i></span>
                  <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($data['full_name']) ?>" maxlength="100" required>
                </div>
              </div>
              <div class="mb-3">
                <label for="email" class="form-label fw-semibold small">Email</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" id="email" name="email" value="<?= e($data['email']) ?>" maxlength="100" required>
                </div>
              </div>
              <div class="mb-3">
                <label for="contact_no" class="form-label fw-semibold small">Contact Number <span class="text-secondary fw-normal">(optional)</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                  <input type="text" class="form-control" id="contact_no" name="contact_no" value="<?= e($data['contact_no']) ?>" placeholder="09XXXXXXXXX" maxlength="11">
                </div>
              </div>
              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label for="password" class="form-label fw-semibold small">Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password" title="Show password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                  </div>
                </div>
                <div class="col-sm-6">
                  <label for="confirm_password" class="form-label fw-semibold small">Confirm Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="#confirm_password" title="Show password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                  </div>
                </div>
              </div>
              <button type="submit" class="btn btn-gradient btn-lg w-100"><i class="bi bi-person-check me-2"></i>Register</button>
            </form>

            <p class="text-center text-secondary small mt-4 mb-0">Already have an account? <a href="<?= url('index.php') ?>" class="fw-semibold text-decoration-none">Log in</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
