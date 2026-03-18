<?php
require_once __DIR__ . '/../backend/includes/bootstrap.php';
requireStaff();

$pdo    = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$error  = null;

$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

function validateProduct(array $p): ?string {
    if (strlen(trim($p['name'] ?? '')) < 2) return 'Product name is required.';
    if (!is_numeric($p['cost_price'] ?? '') || (float)$p['cost_price'] < 0)   return 'Enter a valid cost price.';
    if (!is_numeric($p['selling_price'] ?? '') || (float)$p['selling_price'] <= 0) return 'Selling price must be greater than zero.';
    if (!is_numeric($p['stock'] ?? '') || (int)$p['stock'] < 0) return 'Stock must be 0 or more.';
    if (!(int)($p['category_id'] ?? 0)) return 'Select a category.';
    return null;
}

// ── CREATE ────────────────────────────────────────────────────────────────
if ($action === 'create') {
    requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf()) { $error = 'Invalid request.'; }
        else { $error = validateProduct($_POST); }

        if (!$error) {
            $pdo->prepare(
                'INSERT INTO products (name,description,cost_price,selling_price,stock,reorder_level,
                                       image_url,is_active,category_id,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                htmlspecialchars(strip_tags(trim($_POST['name']))),
                htmlspecialchars(strip_tags(trim($_POST['description'] ?? ''))),
                number_format((float)$_POST['cost_price'],2,'.',''),
                number_format((float)$_POST['selling_price'],2,'.',''),
                (int)$_POST['stock'],
                max(0,(int)($_POST['reorder_level']??10)),
                trim($_POST['image_url']??'')?:null,
                isset($_POST['is_active'])?1:0,
                (int)$_POST['category_id'],
                $_SESSION['user_id'],
            ]);
            $newId = $pdo->lastInsertId();
            logAction('CREATE','product',$newId,['name'=>$_POST['name']]);
            flashSet('success','Product created.');
            header('Location: ' . url('admin/products.php')); exit;
        }
    }
    $pageTitle = 'Add Product';
    include __DIR__ . '/../backend/includes/admin_header.php';
    $product = $_POST;
    include __DIR__ . '/../backend/php/product_form.php';
    include __DIR__ . '/../backend/includes/admin_footer.php';
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────────────────
if ($action === 'edit') {
    requireAdmin();
    $s = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $s->execute([$id]);
    $product = $s->fetch();
    if (!$product) { flashSet('danger','Product not found.'); header('Location: ' . url('admin/products.php')); exit; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf()) { $error = 'Invalid request.'; }
        else { $error = validateProduct($_POST); }

        if (!$error) {
            $pdo->prepare(
                'UPDATE products SET name=?,description=?,cost_price=?,selling_price=?,stock=?,
                                     reorder_level=?,image_url=?,is_active=?,category_id=?
                 WHERE id=?'
            )->execute([
                htmlspecialchars(strip_tags(trim($_POST['name']))),
                htmlspecialchars(strip_tags(trim($_POST['description']??''))),
                number_format((float)$_POST['cost_price'],2,'.',''),
                number_format((float)$_POST['selling_price'],2,'.',''),
                (int)$_POST['stock'],
                max(0,(int)($_POST['reorder_level']??10)),
                trim($_POST['image_url']??'')?:null,
                isset($_POST['is_active'])?1:0,
                (int)$_POST['category_id'],
                $id,
            ]);
            logAction('UPDATE','product',$id,['name'=>$_POST['name']]);
            flashSet('success','Product updated.');
            header('Location: ' . url('admin/products.php')); exit;
        }
        $product = array_merge($product, $_POST);
        $product['id'] = $id;
    }
    $pageTitle = 'Edit Product';
    include __DIR__ . '/../backend/includes/admin_header.php';
    include __DIR__ . '/../backend/php/product_form.php';
    include __DIR__ . '/../backend/includes/admin_footer.php';
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    if (!verifyCsrf()) { flashSet('danger','Invalid request.'); header('Location: ' . url('admin/products.php')); exit; }
    $s = $pdo->prepare('SELECT name FROM products WHERE id=?');
    $s->execute([$id]);
    $pr = $s->fetch();
    if ($pr) {
        $chk = $pdo->prepare('SELECT 1 FROM order_items WHERE product_id=? LIMIT 1');
        $chk->execute([$id]);
        if ($chk->fetch()) {
            flashSet('danger',"Cannot delete \"{$pr['name']}\" — it has order history. Deactivate it instead.");
        } else {
            $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
            logAction('DELETE','product',$id,['name'=>$pr['name']]);
            flashSet('success','Product deleted.');
        }
    }
    header('Location: ' . url('admin/products.php')); exit;
}

// ── LIST ──────────────────────────────────────────────────────────────────
$search    = trim($_GET['q']  ?? '');
$filterCat = (int)($_GET['cat'] ?? 0);
$filterLow = isset($_GET['low']);

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = 'p.name LIKE ?'; $params[] = "%$search%"; }
if ($filterCat) { $where[] = 'p.category_id=?'; $params[] = $filterCat; }
if ($filterLow)   $where[] = 'p.stock<=p.reorder_level';
$w = implode(' AND ', $where);

$cq = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $w");
$cq->execute($params);
$total  = (int)$cq->fetchColumn();
$paging = paginate($total, 20);

$lq = $pdo->prepare(
    "SELECT p.*, c.name AS cat_name,
            ROUND((p.selling_price-p.cost_price)/NULLIF(p.selling_price,0)*100,1) AS margin_pct
     FROM products p LEFT JOIN categories c ON c.id=p.category_id
     WHERE $w ORDER BY p.name ASC LIMIT ? OFFSET ?"
);
$lq->execute(array_merge($params,[$paging['per_page'],$paging['offset']]));
$products = $lq->fetchAll();

$pageTitle = 'Products';
include __DIR__ . '/../backend/includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="fw-bold mb-0" style="color:var(--adm-primary);">
      <i class="bi bi-box-seam me-2"></i>Products
    </h2>
    <small class="text-adm-muted"><?= number_format($total) ?> product<?= $total!==1?'s':'' ?></small>
  </div>
  <?php if (isAdmin()): ?>
  <a href="<?= url('admin/products.php?action=create') ?>" class="btn btn-adm">
    <i class="bi bi-plus-lg me-1"></i>Add Product
  </a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card p-3 mb-4">
  <form method="GET" action="<?= url('admin/products.php') ?>" class="row g-2 align-items-end">
    <div class="col-sm-4">
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Search…" value="<?= esc($search) ?>">
      </div>
    </div>
    <div class="col-sm-3">
      <select name="cat" class="form-select form-select-sm">
        <option value="">All categories</option>
        <?php foreach ($cats as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $filterCat===(int)$c['id']?'selected':'' ?>><?= esc($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <div class="form-check mb-0">
        <input type="checkbox" name="low" id="chkLow" class="form-check-input"
               <?= $filterLow?'checked':'' ?>>
        <label class="form-check-label small" for="chkLow" style="color:var(--adm-red);">Low stock only</label>
      </div>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-adm btn-sm">Filter</button>
      <?php if ($search||$filterCat||$filterLow): ?>
      <a href="<?= url('admin/products.php') ?>" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="table-responsive">
  <table class="tbl">
    <thead><tr>
      <th>#</th><th>Name</th><th>Category</th>
      <th class="text-end">Cost</th><th class="text-end">Price</th>
      <th class="text-center">Margin</th><th class="text-end">Stock</th>
      <th class="text-center">Active</th>
      <?php if (isAdmin()): ?><th class="text-end">Actions</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php if (empty($products)): ?>
    <tr><td colspan="9" class="text-center text-adm-muted py-5">
      <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;"></i>No products found.
    </td></tr>
    <?php else: ?>
    <?php foreach ($products as $p):
        $mc = $p['margin_pct'] >= 30 ? 'var(--adm-green)' : ($p['margin_pct'] >= 10 ? 'var(--adm-orange)' : 'var(--adm-red)');
        $isLow = $p['stock'] <= $p['reorder_level'];
    ?>
    <tr>
      <td class="small text-adm-muted"><?= $p['id'] ?></td>
      <td>
        <div class="fw-semibold small"><?= esc($p['name']) ?></div>
        <?php if ($p['description']): ?>
        <div style="font-size:.73rem;color:var(--adm-muted);">
          <?= esc(mb_substr($p['description'],0,50)) ?>…
        </div>
        <?php endif; ?>
      </td>
      <td><span class="badge bg-light text-dark border" style="font-size:.73rem;"><?= esc($p['cat_name']??'') ?></span></td>
      <td class="text-end small text-adm-muted">KES <?= number_format($p['cost_price'],2) ?></td>
      <td class="text-end small fw-semibold" style="color:var(--adm-accent);">KES <?= number_format($p['selling_price'],2) ?></td>
      <td class="text-center">
        <span class="badge rounded-pill" style="background:<?= $mc ?>;color:#fff;font-size:.72rem;">
          <?= $p['margin_pct'] ?>%
        </span>
      </td>
      <td class="text-end fw-semibold <?= $isLow ? '' : '' ?>" style="color:<?= $isLow?'var(--adm-red)':'inherit' ?>;">
        <?= $p['stock'] ?>
        <?php if ($isLow): ?><i class="bi bi-exclamation-circle ms-1"></i><?php endif; ?>
      </td>
      <td class="text-center">
        <?= $p['is_active']
            ? '<span class="badge" style="background:rgba(39,174,96,.15);color:var(--adm-green);">Yes</span>'
            : '<span class="badge" style="background:rgba(231,76,60,.15);color:var(--adm-red);">No</span>' ?>
      </td>
      <?php if (isAdmin()): ?>
      <td class="text-end">
        <a href="<?= url('admin/products.php?action=edit&id=') ?><?= $p['id'] ?>"
           class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-pencil"></i></a>
        <button type="button" class="btn btn-sm btn-outline-danger"
                data-bs-toggle="modal" data-bs-target="#delModal"
                data-id="<?= $p['id'] ?>" data-name="<?= esc($p['name']) ?>">
          <i class="bi bi-trash"></i>
        </button>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($paging['total_pages'] > 1): ?>
<nav class="mt-3"><ul class="pagination justify-content-center">
  <?php for ($i=1;$i<=$paging['total_pages'];$i++): ?>
  <li class="page-item <?= $i===$paging['page']?'active':'' ?>">
    <a class="page-link" href="?page=<?= $i ?><?= $search?'&q='.urlencode($search):'' ?><?= $filterCat?'&cat='.$filterCat:'' ?><?= $filterLow?'&low=1':'' ?>">
      <?= $i ?>
    </a>
  </li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php if (isAdmin()): ?>
<div class="modal fade" id="delModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" style="color:var(--adm-red);">
          <i class="bi bi-exclamation-triangle me-2"></i>Delete Product
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Delete <strong id="delName"></strong>? Products with order history cannot be deleted — deactivate them instead.
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <form id="delForm" method="POST">
          <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash me-1"></i>Delete
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
$extraScript = <<<JS
document.getElementById('delModal').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('delName').textContent = btn.dataset.name;
    document.getElementById('delForm').action = '/admin/products.php?action=delete&id=' + btn.dataset.id;
});
JS;
endif;
include __DIR__ . '/../backend/includes/admin_footer.php';
?>
