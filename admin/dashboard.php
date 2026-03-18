<?php
require_once __DIR__ . '/../backend/includes/bootstrap.php';
requireStaff();

$pdo   = getDB();
$range = $_GET['range'] ?? '30';
$since = match($range) {
    '7'  => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
    '90' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
    default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
};
$rangeLabel = match($range) { '7'=>'Last 7 days','90'=>'Last 90 days',default=>'Last 30 days' };

// ── KPIs ──────────────────────────────────────────────────────────────────
$kq = $pdo->prepare(
    "SELECT COUNT(DISTINCT o.id) AS orders,
            COALESCE(SUM(o.total),0) AS revenue,
            COALESCE(SUM(oi.quantity*(oi.unit_price-oi.cost_price)),0) AS profit
     FROM orders o
     JOIN order_items oi ON oi.order_id=o.id
     WHERE o.status NOT IN ('cancelled','refunded') AND o.created_at>=$since"
);
$kq->execute();
$kpis = $kq->fetch();

$pendingCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing')"
)->fetchColumn();

$productCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
$customerCount= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();

// ── Low stock ─────────────────────────────────────────────────────────────
$lowStock = $pdo->query(
    "SELECT id,name,stock,reorder_level FROM products
     WHERE stock<=reorder_level AND is_active=1 ORDER BY stock ASC LIMIT 8"
)->fetchAll();

// ── Top 5 products ────────────────────────────────────────────────────────
$topQ = $pdo->prepare(
    "SELECT p.name, SUM(oi.quantity) AS units, SUM(oi.quantity*oi.unit_price) AS revenue
     FROM order_items oi
     JOIN products p ON p.id=oi.product_id
     JOIN orders o ON o.id=oi.order_id
     WHERE o.status NOT IN ('cancelled','refunded') AND o.created_at>=$since
     GROUP BY oi.product_id ORDER BY revenue DESC LIMIT 5"
);
$topQ->execute();
$topProducts = $topQ->fetchAll();

// ── Daily chart data (last 30 days always) ────────────────────────────────
$dailyQ = $pdo->query(
    "SELECT DATE(o.created_at) AS day,
            SUM(o.total) AS revenue,
            SUM(oi.quantity*(oi.unit_price-oi.cost_price)) AS profit
     FROM orders o JOIN order_items oi ON oi.order_id=o.id
     WHERE o.status NOT IN ('cancelled','refunded')
       AND o.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
     GROUP BY DATE(o.created_at) ORDER BY day"
);
$dailyData = $dailyQ->fetchAll();
$dailyMap  = [];
foreach ($dailyData as $d) $dailyMap[date('d M',strtotime($d['day']))] = $d;
$chartDays = $chartRev = $chartProfit = [];
for ($i = 29; $i >= 0; $i--) {
    $day          = date('d M', strtotime("-{$i} days"));
    $chartDays[]  = $day;
    $chartRev[]   = round($dailyMap[$day]['revenue'] ?? 0, 2);
    $chartProfit[]= round($dailyMap[$day]['profit']  ?? 0, 2);
}

// ── Recent orders ─────────────────────────────────────────────────────────
$recentOrders = $pdo->query(
    "SELECT o.id,o.total,o.status,o.created_at,o.payment_method,
            u.name AS cname, COUNT(oi.id) AS item_count
     FROM orders o
     JOIN users u ON u.id=o.customer_id
     LEFT JOIN order_items oi ON oi.order_id=o.id
     GROUP BY o.id ORDER BY o.created_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/../backend/includes/admin_header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Heading + range selector -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="fw-bold mb-0" style="color:var(--adm-primary);">
      <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </h2>
    <small class="text-adm-muted"><?= $rangeLabel ?></small>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <a href="<?= url('admin/orders.php') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-bag me-1"></i>All Orders
    </a>
    <?php if (isAdmin()): ?>
    <a href="<?= url('admin/products.php?action=create') ?>" class="btn btn-adm btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Add Product
    </a>
    <?php endif; ?>
    <div class="btn-group btn-group-sm">
      <?php foreach (['7'=>'7d','30'=>'30d','90'=>'90d'] as $v=>$lbl): ?>
      <a href="?range=<?= $v ?>"
         class="btn <?= $range===$v ? 'btn-adm' : 'btn-outline-secondary' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- KPI row -->
<div class="row g-3 mb-4">
<?php
$kpiData = [
    ['label'=>'Revenue',   'val'=>'KES '.number_format($kpis['revenue'],0),    'icon'=>'bi-cash-stack',     'clr'=>'var(--adm-accent)'],
    ['label'=>'Profit',    'val'=>'KES '.number_format($kpis['profit'],0),     'icon'=>'bi-graph-up-arrow', 'clr'=>'var(--adm-green)'],
    ['label'=>'Orders',    'val'=>number_format($kpis['orders']),              'icon'=>'bi-bag',            'clr'=>'var(--adm-orange)'],
    ['label'=>'Pending',   'val'=>$pendingCount,                               'icon'=>'bi-hourglass-split','clr'=>$pendingCount>0?'var(--adm-red)':'var(--adm-green)'],
    ['label'=>'Products',  'val'=>$productCount,                               'icon'=>'bi-box-seam',       'clr'=>'var(--adm-purple)'],
    ['label'=>'Customers', 'val'=>$customerCount,                              'icon'=>'bi-people',         'clr'=>'var(--adm-accent)'],
];
foreach ($kpiData as $k): ?>
<div class="col-6 col-md-4 col-xl-2">
  <div class="card kpi-card p-3" style="border-left-color:<?= $k['clr'] ?>;">
    <div class="d-flex align-items-center gap-2 mb-1">
      <i class="bi <?= $k['icon'] ?>" style="font-size:1.1rem;color:<?= $k['clr'] ?>;"></i>
      <span class="small text-adm-muted text-uppercase fw-semibold" style="font-size:.7rem;"><?= $k['label'] ?></span>
    </div>
    <div class="kpi-value" style="font-size:1.3rem;color:<?= $k['clr'] ?>;"><?= $k['val'] ?></div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card p-4 h-100">
      <h6 class="fw-semibold mb-3" style="color:var(--adm-primary);">
        <i class="bi bi-graph-up me-1"></i>Revenue & Profit — Last 30 Days
      </h6>
      <canvas id="revChart" style="max-height:240px;"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card p-4 h-100">
      <h6 class="fw-semibold mb-3" style="color:var(--adm-primary);">
        <i class="bi bi-trophy me-1"></i>Top Products
      </h6>
      <?php if (empty($topProducts)): ?>
      <p class="small text-adm-muted">No sales yet.</p>
      <?php else: ?>
      <canvas id="topChart" style="max-height:220px;"></canvas>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent orders + low stock -->
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0" style="color:var(--adm-primary);">
          <i class="bi bi-bag me-1"></i>Recent Orders
        </h6>
        <a href="<?= url('admin/orders.php') ?>" class="btn btn-sm btn-outline-secondary">View all</a>
      </div>
      <div class="table-responsive">
        <table class="tbl">
          <thead><tr>
            <th>#</th><th>Customer</th><th class="text-center">Items</th>
            <th class="text-end">Total</th><th>Payment</th><th>Status</th><th></th>
          </tr></thead>
          <tbody>
          <?php if (empty($recentOrders)): ?>
          <tr><td colspan="7" class="text-center text-adm-muted py-4">No orders yet.</td></tr>
          <?php else: ?>
          <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td class="small text-adm-muted">#<?= $o['id'] ?></td>
            <td class="small fw-semibold"><?= esc($o['cname']) ?></td>
            <td class="text-center small"><?= $o['item_count'] ?></td>
            <td class="text-end small fw-bold">KES <?= number_format($o['total'],0) ?></td>
            <td class="small">
              <?= $o['payment_method']==='mpesa'
                ? '<span style="color:var(--adm-green);font-weight:600;">M-Pesa</span>'
                : ucfirst($o['payment_method']) ?>
            </td>
            <td>
              <span class="st st-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
            </td>
            <td>
              <a href="<?= url('admin/orders.php?id=') ?><?= $o['id'] ?>"
                 class="btn btn-sm btn-outline-secondary py-0">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0" style="color:var(--adm-primary);">
          <i class="bi bi-exclamation-triangle me-1"></i>Low Stock
        </h6>
        <a href="<?= url('admin/products.php?low=1') ?>" class="btn btn-sm btn-outline-secondary">View all</a>
      </div>
      <?php if (empty($lowStock)): ?>
      <div class="text-center py-3">
        <i class="bi bi-check-circle" style="font-size:2rem;color:var(--adm-green);"></i>
        <p class="small text-adm-muted mt-2 mb-0">All products well stocked.</p>
      </div>
      <?php else: ?>
      <?php foreach ($lowStock as $p):
          $clr = $p['stock']==0 ? 'var(--adm-red)' : 'var(--adm-orange)';
          $bg  = $p['stock']==0 ? '#fdedec' : '#fef9e7';
      ?>
      <div class="d-flex justify-content-between align-items-center p-2 rounded mb-1"
           style="background:<?= $bg ?>;">
        <div>
          <div class="small fw-semibold" style="color:<?= $clr ?>;"><?= esc($p['name']) ?></div>
          <div style="font-size:.72rem;color:var(--adm-muted);">
            <?= $p['stock']==0 ? 'Out of stock' : $p['stock'].' left' ?>
            · reorder at <?= $p['reorder_level'] ?>
          </div>
        </div>
        <a href="<?= url('admin/products.php?action=edit&id=') ?><?= $p['id'] ?>"
           class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.7rem;">Edit</a>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$dj = json_encode($chartDays);
$rj = json_encode($chartRev);
$pj = json_encode($chartProfit);
$tn = json_encode(array_column($topProducts,'name'));
$tr = json_encode(array_map(fn($r)=>round($r['revenue'],2),$topProducts));

$extraScript = <<<JS
(function(){
  var dark=document.body.classList.contains('theme-dark');
  var grid=dark?'rgba(255,255,255,.06)':'rgba(0,0,0,.06)';
  var lbl=dark?'#7a92a6':'#6c7a89';
  Chart.defaults.color=lbl;

  new Chart(document.getElementById('revChart'),{
    type:'line',
    data:{labels:$dj,datasets:[
      {label:'Revenue',data:$rj,borderColor:'#2e86c1',backgroundColor:'rgba(46,134,193,.1)',
       borderWidth:2,pointRadius:2,fill:true,tension:.4},
      {label:'Profit', data:$pj,borderColor:'#27ae60',backgroundColor:'rgba(39,174,96,.08)',
       borderWidth:2,pointRadius:2,fill:true,tension:.4}
    ]},
    options:{responsive:true,interaction:{mode:'index',intersect:false},
      plugins:{legend:{position:'top',labels:{boxWidth:12,padding:12}},
        tooltip:{callbacks:{label:c=>'KES '+c.parsed.y.toLocaleString('en-KE',{minimumFractionDigits:2})}}},
      scales:{
        x:{grid:{color:grid},ticks:{maxTicksLimit:8,maxRotation:0}},
        y:{grid:{color:grid},ticks:{callback:v=>'KES '+v.toLocaleString()}}
      }}
  });

  var te=document.getElementById('topChart');
  if(te) new Chart(te,{type:'bar',
    data:{labels:$tn,datasets:[{label:'Revenue',data:$tr,borderRadius:4,
      backgroundColor:['rgba(46,134,193,.8)','rgba(39,174,96,.8)','rgba(243,156,18,.8)',
                        'rgba(142,68,173,.8)','rgba(231,76,60,.8)']}]},
    options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false},
      tooltip:{callbacks:{label:c=>'KES '+c.parsed.x.toLocaleString('en-KE',{minimumFractionDigits:2})}}},
      scales:{x:{grid:{color:grid},ticks:{callback:v=>'KES '+v.toLocaleString()}},
              y:{grid:{display:false}}}}
  });
})();
JS;
include __DIR__ . '/../backend/includes/admin_footer.php';
?>
