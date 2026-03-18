<?php
/**
 * Product create/edit form.
 * Expects: $product (array), $cats (array), $error (string|null),
 *          $action ('create'|'edit') set in calling script.
 */
if (!defined('APP_RUNNING')) exit;
$isEdit = ($action === 'edit');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="fw-bold mb-0" style="color:var(--adm-primary);">
      <i class="bi bi-<?= $isEdit?'pencil':'plus-circle' ?> me-2"></i>
      <?= $isEdit ? 'Edit Product' : 'Add Product' ?>
    </h2>
  </div>
  <a href="<?= url('admin/products.php') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= esc($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-7">
    <div class="card p-4">
      <form method="POST"
            action="<?= url('admin/products.php?action=') ?><?= $action ?><?= $isEdit?'&id='.(int)$product['id']:'' ?>">
        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">

        <div class="mb-3">
          <label class="form-label fw-semibold small">Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control"
                 value="<?= esc($product['name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold small">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= esc($product['description'] ?? '') ?></textarea>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Cost Price (KES) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">KES</span>
              <input type="number" name="cost_price" class="form-control"
                     step="0.01" min="0"
                     value="<?= esc($product['cost_price'] ?? '') ?>" required>
            </div>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Selling Price (KES) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">KES</span>
              <input type="number" name="selling_price" class="form-control"
                     step="0.01" min="0.01"
                     value="<?= esc($product['selling_price'] ?? '') ?>" required>
            </div>
          </div>
        </div>

        <!-- Live margin preview -->
        <div id="marginPreview" class="mb-3 p-3 rounded" style="background:var(--adm-bg);display:none;">
          <div class="row text-center">
            <div class="col-4">
              <div class="small text-adm-muted">Profit / unit</div>
              <div class="fw-bold" id="ppUnit">—</div>
            </div>
            <div class="col-4">
              <div class="small text-adm-muted">Margin</div>
              <div class="fw-bold" id="ppMargin">—</div>
            </div>
            <div class="col-4">
              <div class="small text-adm-muted">Markup</div>
              <div class="fw-bold" id="ppMarkup">—</div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Stock <span class="text-danger">*</span></label>
            <input type="number" name="stock" class="form-control"
                   min="0" value="<?= esc($product['stock'] ?? '0') ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">
              Reorder Level
              <i class="bi bi-info-circle text-adm-muted ms-1"
                 title="Alert when stock reaches this level"></i>
            </label>
            <input type="number" name="reorder_level" class="form-control"
                   min="0" value="<?= esc($product['reorder_level'] ?? '10') ?>">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
            <select name="category_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>"
                      <?= (int)($product['category_id']??0)===(int)$c['id']?'selected':'' ?>>
                <?= esc($c['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Image URL</label>
            <input type="url" name="image_url" class="form-control"
                   placeholder="https://…"
                   value="<?= esc($product['image_url'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-4">
          <div class="form-check">
            <input type="checkbox" name="is_active" id="chkActive" class="form-check-input"
                   value="1" <?= ($product['is_active']??1) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold small" for="chkActive">
              Active (visible in shop)
            </label>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-adm">
            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update' : 'Create' ?> Product
          </button>
          <a href="<?= url('admin/products.php') ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3" style="border-left:4px solid var(--adm-accent);">
      <h6 class="fw-semibold mb-2" style="color:var(--adm-primary);">
        <i class="bi bi-lightbulb me-1"></i>Pricing Guide
      </h6>
      <ul class="small text-adm-muted mb-0 ps-3">
        <li class="mb-1"><strong>Cost price</strong> — what you pay the supplier</li>
        <li class="mb-1"><strong>Selling price</strong> — what customers pay</li>
        <li class="mb-1">Target margin: <strong>30%+</strong> for healthy profit</li>
        <li>Set reorder level to trigger low-stock alerts early</li>
      </ul>
    </div>
  </div>
</div>

<script>
(function(){
  var cost=document.querySelector('[name=cost_price]');
  var sell=document.querySelector('[name=selling_price]');
  var box =document.getElementById('marginPreview');
  function calc(){
    var c=parseFloat(cost.value), s=parseFloat(sell.value);
    if(!c||!s||s<=0){box.style.display='none';return;}
    var prof=s-c, margin=(prof/s*100).toFixed(1), markup=(prof/c*100).toFixed(1);
    var clr=margin>=30?'var(--adm-green)':(margin>=10?'var(--adm-orange)':'var(--adm-red)');
    document.getElementById('ppUnit').textContent='KES '+prof.toFixed(2);
    document.getElementById('ppUnit').style.color=clr;
    document.getElementById('ppMargin').textContent=margin+'%';
    document.getElementById('ppMargin').style.color=clr;
    document.getElementById('ppMarkup').textContent=markup+'%';
    box.style.display='block';
  }
  cost.addEventListener('input',calc);
  sell.addEventListener('input',calc);
  calc();
})();
</script>
