<?php
require_once __DIR__ . '/backend/includes/bootstrap.php';
if (isLoggedIn()) {
    header('Location: ' . (isStaff() ? '/admin/dashboard.php' : '/shop.php'));
    exit;
}
$error     = handleLogin();
$pageTitle = 'Sign In';
include __DIR__ . '/backend/includes/shop_header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="text-center mb-4">
      <i class="bi bi-bag-heart-fill" style="font-size:2.6rem;color:var(--accent);"></i>
      <h4 class="fw-bold mt-2 mb-0">Welcome back</h4>
      <small style="color:var(--muted);">Sign in to continue shopping</small>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-2"></i><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= url('login.php') ?>">
      <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">

      <div class="mb-3">
        <label class="form-label fw-semibold small">Email address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control"
                 placeholder="you@example.com"
                 value="<?= esc($_POST['email'] ?? '') ?>"
                 autocomplete="email" required>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold small">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" class="form-control"
                 placeholder="••••••••"
                 autocomplete="current-password" required>
        </div>
      </div>

      <button type="submit" class="btn-primary-shop w-100 py-2 justify-content-center">
        <i class="bi bi-box-arrow-in-right"></i> Sign In
      </button>
    </form>

    <hr class="my-3">
    <p class="text-center mb-0" style="font-size:.87rem;color:var(--muted);">
      No account yet? <a href="<?= url('register.php') ?>" class="fw-semibold">Register here</a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/backend/includes/shop_footer.php'; ?>
