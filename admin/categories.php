<?php
require_once __DIR__ . '/../backend/includes/bootstrap.php';
requireAdmin();

$pdo    = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$error  = null;

// ── CREATE ────────────────────────────────────────────────────────────────
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { $error = 'Invalid request.'; }
    else {
        $name = trim($_POST['name'] ?? '');
        if (strlen($name) < 2) { $error = 'Category name is required.'; }
    }
    if (!$error) {
        try {
            $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
            flashSet('success', "Category \"$name\" created.");
        } catch (PDOException $e) {
            $error = 'That category name already exists.';
        }
    }
    if (!$error) { header('Location: ' . url('admin/categories.php')); exit; }
}

// ── RENAME ────────────────────────────────────────────────────────────────
if ($action === 'rename' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { flashSet('danger','Invalid request.'); header('Location: ' . url('admin/categories.php')); exit; }
    $name = trim($_POST['name'] ?? '');
    if (strlen($name) >= 2) {
        $pdo->prepare('UPDATE categories SET name=? WHERE id=?')->execute([$name, $id]);
        flashSet('success', 'Category updated.');
    }
    header('Location: ' . url('admin/categories.php')); exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { flashSet('danger','Invalid request.'); header('Location: ' . url('admin/categories.php')); exit; }
    $chk = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id=?');
    $chk->execute([$id]);
    if ((int)$chk->fetchColumn() > 0) {
        flashSet('danger', 'Cannot delete — products are assigned to this category.');
    } else {
        $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
        flashSet('success', 'Category deleted.');
    }
    header('Location: ' . url('admin/categories.php')); exit;
}

// ── LIST ──────────────────────────────────────────────────────────────────
$cats = $pdo->query(
    'SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id=c.id
     GROUP BY c.id ORDER BY c.name'
)->fetchAll();

$pageTitle = 'Categories';
include __DIR__ . '/../backend/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0" style="color:var(--adm-primary);">
    <i class="bi bi-tags me-2"></i>Categories
  </h2>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= esc($error) ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- Add new -->
  <div class="col-lg-4">
    <div class="card p-4">
      <h6 class="fw-semibold mb-3" style="color:var(--adm-primary);">Add Category</h6>
      <form method="POST" action="<?= url('admin/categories.php?action=create') ?>">
        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
        <div class="mb-3">
          <input type="text" name="name" class="form-control"
                 placeholder="Category name" required>
        </div>
        <button type="submit" class="btn btn-adm">
          <i class="bi bi-plus-lg me-1"></i>Add
        </button>
      </form>
    </div>
  </div>

  <!-- List -->
  <div class="col-lg-8">
    <div class="table-responsive">
      <table class="tbl">
        <thead><tr>
          <th>#</th><th>Name</th>
          <th class="text-center">Products</th>
          <th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($cats)): ?>
        <tr><td colspan="4" class="text-center text-adm-muted py-4">No categories yet.</td></tr>
        <?php else: ?>
        <?php foreach ($cats as $c): ?>
        <tr>
          <td class="small text-adm-muted"><?= $c['id'] ?></td>
          <td class="fw-semibold"><?= esc($c['name']) ?></td>
          <td class="text-center">
            <a href="<?= url('admin/products.php?cat=') ?><?= $c['id'] ?>"
               class="badge text-decoration-none"
               style="background:rgba(46,134,193,.1);color:var(--adm-accent);">
              <?= $c['product_count'] ?>
            </a>
          </td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                    data-bs-toggle="modal" data-bs-target="#renameModal"
                    data-id="<?= $c['id'] ?>" data-name="<?= esc($c['name']) ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <?php if ($c['product_count'] == 0): ?>
            <form method="POST" action="<?= url('admin/categories.php?action=delete&id=') ?><?= $c['id'] ?>"
                  class="d-inline" onsubmit="return confirm('Delete category?')">
              <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Rename modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" style="color:var(--adm-primary);">Rename Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="renameForm" method="POST">
        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
        <div class="modal-body">
          <input type="text" name="name" id="renameInput" class="form-control" required>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-adm">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraScript = <<<JS
document.getElementById('renameModal').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('renameInput').value = btn.dataset.name;
    document.getElementById('renameForm').action = '/admin/categories.php?action=rename&id=' + btn.dataset.id;
});
JS;
include __DIR__ . '/../backend/includes/admin_footer.php';
?>
