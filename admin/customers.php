<?php
require_once __DIR__ . '/../backend/includes/bootstrap.php';
requireAdmin();

$pdo    = getDB();
$search = trim($_GET['q'] ?? '');

$where  = ["u.role='customer'"];
$params = [];
if ($search) {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$w = implode(' AND ', $where);

$cq = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $w");
$cq->execute($params);
$total  = (int)$cq->fetchColumn();
$paging = paginate($total, 25);

$lq = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.phone, u.created_at,
            COUNT(o.id) AS order_count,
            COALESCE(SUM(o.total),0) AS lifetime_value
     FROM users u
     LEFT JOIN orders o ON o.customer_id=u.id AND o.status NOT IN ('cancelled','refunded')
     WHERE $w GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?"
);
$lq->execute(array_merge($params, [$paging['per_page'], $paging['offset']]));
$customers = $lq->fetchAll();

$pageTitle = 'Customers';
include __DIR__ . '/../backend/includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="fw-bold mb-0" style="color:var(--adm-primary);">
      <i class="bi bi-people me-2"></i>Customers
    </h2>
    <small class="text-adm-muted"><?= number_format($total) ?> registered</small>
  </div>
</div>

<div class="card p-3 mb-4">
  <form method="GET" class="d-flex gap-2">
    <div class="input-group input-group-sm" style="max-width:360px;">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control"
             placeholder="Name or email…" value="<?= esc($search) ?>">
    </div>
    <button class="btn btn-adm btn-sm">Search</button>
    <?php if ($search): ?>
    <a href="<?= url('admin/customers.php') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="table-responsive">
  <table class="tbl">
    <thead><tr>
      <th>#</th><th>Name</th><th>Email</th><th>Phone</th>
      <th class="text-center">Orders</th>
      <th class="text-end">Lifetime Value</th>
      <th>Joined</th>
    </tr></thead>
    <tbody>
    <?php if (empty($customers)): ?>
    <tr><td colspan="7" class="text-center text-adm-muted py-5">
      <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;"></i>No customers found.
    </td></tr>
    <?php else: ?>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td class="small text-adm-muted"><?= $c['id'] ?></td>
      <td class="fw-semibold"><?= esc($c['name']) ?></td>
      <td class="small"><?= esc($c['email']) ?></td>
      <td class="small"><?= esc($c['phone'] ?? '—') ?></td>
      <td class="text-center">
        <?php if ($c['order_count'] > 0): ?>
        <a href="<?= url('admin/orders.php?q=') ?><?= urlencode($c['email']) ?>"
           class="badge text-decoration-none"
           style="background:rgba(46,134,193,.12);color:var(--adm-accent);font-size:.78rem;">
          <?= $c['order_count'] ?>
        </a>
        <?php else: ?>
        <span class="small text-adm-muted">0</span>
        <?php endif; ?>
      </td>
      <td class="text-end fw-bold" style="color:var(--adm-green);">
        KES <?= number_format($c['lifetime_value'],0) ?>
      </td>
      <td class="small text-adm-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
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
    <a class="page-link" href="?page=<?= $i ?><?= $search?'&q='.urlencode($search):'' ?>"><?= $i ?></a>
  </li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php include __DIR__ . '/../backend/includes/admin_footer.php'; ?>
