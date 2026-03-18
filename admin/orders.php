<?php
require_once __DIR__ . '/../backend/includes/bootstrap.php';
requireStaff();

$pdo    = getDB();
$viewId = (int)($_GET['id'] ?? 0);

// ── STATUS UPDATE ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['status'])) {
    if (!verifyCsrf()) { flashSet('danger','Invalid request.'); header('Location: ' . url('admin/orders.php')); exit; }
    $oid    = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['status'];
    $ok     = ['pending','paid','processing','shipped','delivered','cancelled','refunded'];
    if (in_array($status,$ok)) {
        $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status,$oid]);
        logAction('UPDATE','order',$oid,['status'=>$status]);
        flashSet('success',"Order #$oid updated to \"$status\".");
    }
    header('Location: ' . url('admin/orders.php?id=').$oid); exit;
}

// ── SINGLE ORDER VIEW ─────────────────────────────────────────────────────
if ($viewId > 0) {
    $s = $pdo->prepare(
        'SELECT o.*,u.name AS cname,u.email AS cemail,u.phone AS cphone
         FROM orders o JOIN users u ON u.id=o.customer_id WHERE o.id=?'
    );
    $s->execute([$viewId]);
    $order = $s->fetch();
    if (!$order) { flashSet('danger','Order not found.'); header('Location: ' . url('admin/orders.php')); exit; }

    $items = $pdo->prepare(
        'SELECT oi.*, (oi.quantity*(oi.unit_price-oi.cost_price)) AS profit
         FROM order_items oi WHERE oi.order_id=?'
    );
    $items->execute([$viewId]);
    $orderItems = $items->fetchAll();

    $allStatuses = ['pending','paid','processing','shipped','delivered','cancelled','refunded'];
    $stColors    = ['paid'=>'success','delivered'=>'success','pending'=>'warning',
                    'processing'=>'primary','shipped'=>'info','cancelled'=>'danger','refunded'=>'secondary'];

    $pageTitle = "Order #$viewId";
    include __DIR__ . '/../backend/includes/admin_header.php';
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="fw-bold mb-0" style="color:var(--adm-primary);">
          <i class="bi bi-bag me-2"></i>Order #<?= $viewId ?>
        </h2>
        <small class="text-adm-muted"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></small>
      </div>
      <a href="<?= url('admin/orders.php') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Orders
      </a>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <!-- Items table -->
        <div class="card p-4 mb-4">
          <h6 class="fw-semibold mb-3" style="color:var(--adm-primary);">Items</h6>
          <div class="table-responsive">
            <table class="tbl">
              <thead><tr>
                <th>Product</th><th class="text-center">Qty</th>
                <th class="text-end">Unit Price</th><th class="text-end">Subtotal</th>
                <th class="text-end">Profit</th>
              </tr></thead>
              <tbody>
              <?php foreach ($orderItems as $item): ?>
              <tr>
                <td class="fw-semibold"><?= esc($item['product_name']) ?></td>
                <td class="text-center"><?= $item['quantity'] ?></td>
                <td class="text-end">KES <?= number_format($item['unit_price'],2) ?></td>
                <td class="text-end fw-bold">KES <?= number_format($item['quantity']*$item['unit_price'],2) ?></td>
                <td class="text-end" style="color:var(--adm-green);">KES <?= number_format($item['profit'],2) ?></td>
              </tr>
              <?php endforeach; ?>
              <tr style="background:color-mix(in srgb,var(--adm-card) 90%,var(--adm-accent));">
                <td colspan="3" class="fw-bold">Totals</td>
                <td class="text-end fw-bold">KES <?= number_format($order['total'],2) ?></td>
                <td class="text-end fw-bold" style="color:var(--adm-green);">
                  KES <?= number_format(array_sum(array_column($orderItems,'profit')),2) ?>
                </td>
              </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Update status -->
        <div class="card p-4">
          <h6 class="fw-semibold mb-3" style="color:var(--adm-primary);">Update Status</h6>
          <form method="POST" action="<?= url('admin/orders.php?id=') ?><?= $viewId ?>"
                class="d-flex gap-2 align-items-center flex-wrap">
            <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="order_id"   value="<?= $viewId ?>">
            <select name="status" class="form-select form-select-sm" style="max-width:200px;">
              <?php foreach ($allStatuses as $s): ?>
              <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-adm btn-sm">Update</button>
          </form>
        </div>
      </div>

      <div class="col-lg-4">
        <!-- Customer -->
        <div class="card p-4 mb-3">
          <h6 class="fw-semibold mb-2" style="color:var(--adm-primary);">Customer</h6>
          <div class="small">
            <div class="fw-bold mb-1"><?= esc($order['cname']) ?></div>
            <div class="text-adm-muted mb-1"><i class="bi bi-envelope me-1"></i><?= esc($order['cemail']) ?></div>
            <?php if ($order['cphone']): ?>
            <div class="text-adm-muted"><i class="bi bi-telephone me-1"></i><?= esc($order['cphone']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Payment -->
        <div class="card p-4 mb-3">
          <h6 class="fw-semibold mb-2" style="color:var(--adm-primary);">Payment</h6>
          <div class="small">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-adm-muted">Method</span>
              <span class="fw-semibold <?= $order['payment_method']==='mpesa'?'text-success':'' ?>">
                <?= $order['payment_method']==='mpesa' ? 'M-Pesa' : ucfirst($order['payment_method']) ?>
              </span>
            </div>
            <?php if ($order['mpesa_code']): ?>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-adm-muted">Code</span>
              <span class="fw-bold font-monospace"><?= esc($order['mpesa_code']) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-adm-muted">Phone</span>
              <span><?= esc($order['mpesa_phone']) ?></span>
            </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-adm-muted">Subtotal</span>
              <span>KES <?= number_format($order['subtotal'],2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-adm-muted">Delivery</span>
              <span><?= $order['delivery_fee']==0 ? '<span style="color:var(--adm-green);">Free</span>' : 'KES '.number_format($order['delivery_fee'],2) ?></span>
            </div>
            <div class="d-flex justify-content-between border-top mt-2 pt-2 fw-bold">
              <span>Total</span><span>KES <?= number_format($order['total'],2) ?></span>
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="card p-4">
          <h6 class="fw-semibold mb-2" style="color:var(--adm-primary);">Status</h6>
          <span class="st st-<?= $order['status'] ?> fs-6"><?= ucfirst($order['status']) ?></span>
          <?php if ($order['notes']): ?>
          <div class="mt-3 small text-adm-muted">
            <strong>Notes:</strong> <?= esc($order['notes']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    include __DIR__ . '/../backend/includes/admin_footer.php';
    exit;
}

// ── ORDER LIST ────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$where  = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = 'o.status=?'; $params[] = $filterStatus; }
if ($search) {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR o.mpesa_code LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$w = implode(' AND ', $where);

$cq = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id=o.customer_id WHERE $w");
$cq->execute($params);
$total  = (int)$cq->fetchColumn();
$paging = paginate($total, 20);

$lq = $pdo->prepare(
    "SELECT o.id,o.total,o.status,o.payment_method,o.mpesa_code,o.created_at,
            u.name AS cname, u.email AS cemail, COUNT(oi.id) AS item_count
     FROM orders o
     JOIN users u ON u.id=o.customer_id
     LEFT JOIN order_items oi ON oi.order_id=o.id
     WHERE $w GROUP BY o.id ORDER BY o.created_at DESC LIMIT ? OFFSET ?"
);
$lq->execute(array_merge($params, [$paging['per_page'], $paging['offset']]));
$orders = $lq->fetchAll();

// Status pill counts
$scq = $pdo->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status");
$statusCounts = [];
foreach ($scq->fetchAll() as $r) $statusCounts[$r['status']] = $r['cnt'];
$allStatuses = ['pending','paid','processing','shipped','delivered','cancelled','refunded'];

$pageTitle = 'Orders';
include __DIR__ . '/../backend/includes/admin_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="fw-bold mb-0" style="color:var(--adm-primary);">
      <i class="bi bi-bag me-2"></i>Orders
    </h2>
    <small class="text-adm-muted"><?= number_format($total) ?> order<?= $total!==1?'s':'' ?></small>
  </div>
</div>

<!-- Filters -->
<div class="card p-3 mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-sm-5">
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control"
               placeholder="Customer name, email or M-Pesa code…"
               value="<?= esc($search) ?>">
      </div>
    </div>
    <div class="col-sm-3">
      <select name="status" class="form-select form-select-sm">
        <option value="">All statuses</option>
        <?php foreach ($allStatuses as $s): ?>
        <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-adm btn-sm">Filter</button>
      <?php if ($search||$filterStatus): ?>
      <a href="<?= url('admin/orders.php') ?>" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Status pills -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <?php foreach ($allStatuses as $s):
      $cnt = $statusCounts[$s] ?? 0;
      if (!$cnt) continue;
  ?>
  <a href="<?= url('admin/orders.php?status=') ?><?= $s ?>"
     class="badge text-decoration-none"
     style="background:<?= $filterStatus===$s?'var(--adm-accent)':'var(--adm-bg)' ?>;
            color:<?= $filterStatus===$s?'#fff':'var(--adm-text)' ?>;
            border:1px solid var(--adm-border);padding:.4em .8em;font-size:.78rem;">
    <?= ucfirst($s) ?> (<?= $cnt ?>)
  </a>
  <?php endforeach; ?>
</div>

<div class="table-responsive">
  <table class="tbl">
    <thead><tr>
      <th>#</th><th>Customer</th><th class="text-center">Items</th>
      <th class="text-end">Total</th><th>Payment</th><th>Date</th><th>Status</th><th class="text-end"></th>
    </tr></thead>
    <tbody>
    <?php if (empty($orders)): ?>
    <tr><td colspan="8" class="text-center text-adm-muted py-5">
      <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;"></i>No orders found.
    </td></tr>
    <?php else: ?>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td class="small text-adm-muted">#<?= $o['id'] ?></td>
      <td>
        <div class="small fw-semibold"><?= esc($o['cname']) ?></div>
        <div style="font-size:.75rem;color:var(--adm-muted);"><?= esc($o['cemail']) ?></div>
      </td>
      <td class="text-center small"><?= $o['item_count'] ?></td>
      <td class="text-end fw-bold small">KES <?= number_format($o['total'],0) ?></td>
      <td class="small">
        <?php if ($o['payment_method']==='mpesa'): ?>
        <span style="color:var(--adm-green);font-weight:600;">M-Pesa</span>
        <?php if ($o['mpesa_code']): ?>
        <br><span style="font-size:.7rem;color:var(--adm-muted);"><?= esc($o['mpesa_code']) ?></span>
        <?php endif; ?>
        <?php else: ?>
        <?= ucfirst($o['payment_method']) ?>
        <?php endif; ?>
      </td>
      <td class="small text-adm-muted"><?= date('d M Y',strtotime($o['created_at'])) ?></td>
      <td><span class="st st-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
      <td class="text-end">
        <a href="<?= url('admin/orders.php?id=') ?><?= $o['id'] ?>"
           class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($paging['total_pages'] > 1): ?>
<nav class="mt-3"><ul class="pagination justify-content-center">
  <?php for ($i=1; $i<=$paging['total_pages']; $i++): ?>
  <li class="page-item <?= $i===$paging['page']?'active':'' ?>">
    <a class="page-link" href="?page=<?= $i ?><?= $filterStatus?'&status='.$filterStatus:'' ?><?= $search?'&q='.urlencode($search):'' ?>">
      <?= $i ?>
    </a>
  </li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php include __DIR__ . '/../backend/includes/admin_footer.php'; ?>
