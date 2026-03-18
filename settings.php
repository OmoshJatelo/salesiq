<?php
require_once __DIR__ . '/backend/includes/bootstrap.php';
requireAuth();

$pdo   = getDB();
$user  = getCurrentUser();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid request.';
    } else {
        $theme = in_array($_POST['theme'] ?? '', ['light','dark']) ? $_POST['theme'] : 'light';
        $phone = trim($_POST['phone'] ?? '');

        $pdo->prepare('UPDATE users SET theme_pref=?, phone=? WHERE id=?')
            ->execute([$theme, $phone ?: null, $_SESSION['user_id']]);

        flashSet('success', 'Preferences saved.');
        header('Location: ' . url('settings.php')); exit;
    }
}
$user = getCurrentUser();

$pageTitle = 'Settings';
if (isStaff()) {
    include __DIR__ . '/backend/includes/admin_header.php';
} else {
    include __DIR__ . '/backend/includes/shop_header.php';
}
?>

<?php if (isStaff()): ?>
<h2 class="fw-bold mb-4" style="color:var(--adm-primary);">
  <i class="bi bi-gear me-2"></i>Settings
</h2>
<?php else: ?>
<h4 class="fw-bold mb-4" style="color:var(--primary);">
  <i class="bi bi-gear me-2"></i>Account Settings
</h4>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= esc($error) ?></div>
<?php endif; ?>

<div class="row <?= isStaff() ? '' : 'justify-content-center' ?>">
  <div class="col-lg-5">
    <div class="card p-4" style="<?= isStaff() ? '' : 'border:1px solid var(--border);border-radius:var(--radius);' ?>">
      <form method="POST" action="<?= url('settings.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">

        <!-- Phone -->
        <div class="mb-4">
          <label class="form-label fw-semibold small">
            M-Pesa Phone Number
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input type="tel" name="phone" class="form-control"
                   placeholder="0712 345 678"
                   value="<?= esc($user['phone'] ?? '') ?>">
          </div>
          <div class="form-text">Pre-filled at checkout for faster payment</div>
        </div>

        <!-- Theme -->
        <div class="mb-4">
          <label class="form-label fw-semibold small">Display Theme</label>
          <div class="row g-3">
            <div class="col-6">
              <label class="d-block border rounded p-3 text-center <?= $user['theme_pref']==='light'?'border-primary':'' ?>"
                     style="cursor:pointer;">
                <input type="radio" name="theme" value="light" class="d-none"
                       <?= $user['theme_pref']==='light'?'checked':'' ?>>
                <div class="rounded mb-2" style="height:30px;background:linear-gradient(135deg,#f4f6f9,#fff);"></div>
                <small class="fw-semibold">Light</small>
              </label>
            </div>
            <div class="col-6">
              <label class="d-block border rounded p-3 text-center <?= $user['theme_pref']==='dark'?'border-primary':'' ?>"
                     style="cursor:pointer;">
                <input type="radio" name="theme" value="dark" class="d-none"
                       <?= $user['theme_pref']==='dark'?'checked':'' ?>>
                <div class="rounded mb-2" style="height:30px;background:linear-gradient(135deg,#0f1923,#192433);"></div>
                <small class="fw-semibold">Dark</small>
              </label>
            </div>
          </div>
        </div>

        <button type="submit"
                class="<?= isStaff() ? 'btn btn-adm' : 'btn-primary-shop' ?>"
                style="<?= isStaff() ? '' : 'border-radius:var(--radius-sm);' ?>">
          <i class="bi bi-check-lg me-1"></i>Save Preferences
        </button>
      </form>
    </div>
  </div>
</div>

<?php
$extraScript = <<<JS
document.querySelectorAll('input[name="theme"]').forEach(function(r) {
    r.addEventListener('change', function() {
        document.body.classList.toggle('theme-dark', this.value === 'dark');
    });
});
JS;
if (isStaff()) {
    include __DIR__ . '/backend/includes/admin_footer.php';
} else {
    include __DIR__ . '/backend/includes/shop_footer.php';
}
?>
