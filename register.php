<?php
require_once __DIR__ . '/backend/includes/bootstrap.php';
if (isLoggedIn()) {
    header('Location: ' . (isStaff() ? '/admin/dashboard.php' : '/shop.php'));
    exit;
}
$error     = handleRegister();
$pageTitle = 'Create Account';
include __DIR__ . '/backend/includes/shop_header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="text-center mb-4">
      <i class="bi bi-person-plus-fill" style="font-size:2.6rem;color:var(--accent);"></i>
      <h4 class="fw-bold mt-2 mb-0">Create an account</h4>
      <small style="color:var(--muted);">Join us and start shopping</small>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-2"></i><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= url('register.php') ?>">
      <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">

      <div class="mb-3">
        <label class="form-label fw-semibold small">Full name</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="name" class="form-control" placeholder="Jane Doe"
                 value="<?= esc($_POST['name'] ?? '') ?>" autocomplete="name" required>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold small">Email address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control" placeholder="jane@example.com"
                 value="<?= esc($_POST['email'] ?? '') ?>" autocomplete="email" required>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold small">
          Phone <small class="text-muted fw-normal">(M-Pesa number)</small>
        </label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-telephone"></i></span>
          <input type="tel" name="phone" class="form-control" placeholder="0712 345 678"
                 value="<?= esc($_POST['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold small">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" class="form-control"
                 placeholder="At least 6 characters" required>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold small">Confirm password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
          <input type="password" name="password_confirm" class="form-control"
                 placeholder="Re-enter password" required>
        </div>
      </div>

      <button type="submit" class="btn-primary-shop w-100 py-2 justify-content-center">
        <i class="bi bi-person-plus"></i> Create Account
      </button>
    </form>

    <hr class="my-3">
    <p class="text-center mb-0" style="font-size:.87rem;color:var(--muted);">
      Already have an account? <a href="<?= url('login.php') ?>" class="fw-semibold">Sign in</a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/backend/includes/shop_footer.php'; ?>
