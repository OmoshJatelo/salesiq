<?php
/**
 * Product create/edit form.
 * Expects: $product (array), $cats (array), $error (string|null),
 *          $action ('create'|'edit') set in calling script.
 */
if (!defined('APP_RUNNING')) exit;
$isEdit = ($action === 'edit');

// Upload directory — relative to project root
$uploadDir = __DIR__ . '/../../uploads/products/';
$uploadUrl = url('uploads/products/');
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0" style="color:var(--adm-primary);">
    <i class="bi bi-<?= $isEdit ? 'pencil' : 'plus-circle' ?> me-2"></i>
    <?= $isEdit ? 'Edit Product' : 'Add Product' ?>
  </h2>
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
            action="<?= url('admin/products.php?action=') ?><?= $action ?><?= $isEdit ? '&id='.(int)$product['id'] : '' ?>"
            enctype="multipart/form-data">
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
            <label class="form-label fw-semibold small">Reorder Level</label>
            <input type="number" name="reorder_level" class="form-control"
                   min="0" value="<?= esc($product['reorder_level'] ?? '10') ?>">
          </div>
        </div>

        <div class="mb-3">
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

        <!-- Image upload -->
        <div class="mb-4">
          <label class="form-label fw-semibold small">Product Image</label>

          <?php
          $currentImage = $product['image_url'] ?? '';
          $isUploaded   = $currentImage && str_contains($currentImage, 'uploads/products/');
          $fullImgUrl   = $currentImage
              ? (str_starts_with($currentImage, 'http') ? $currentImage : url($currentImage))
              : '';
          ?>

          <?php if ($fullImgUrl): ?>
          <div class="mb-2 d-flex align-items-center gap-3">
            <img src="<?= esc($fullImgUrl) ?>" alt="Current image"
                 style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid var(--adm-border);">
            <div>
              <div class="small fw-semibold">Current image</div>
              <div class="form-check mt-1">
                <input type="checkbox" name="remove_image" id="removeImg" class="form-check-input" value="1">
                <label class="form-check-label small text-danger" for="removeImg">Remove image</label>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Drag & drop upload area -->
          <div id="dropZone"
               style="border:2px dashed var(--adm-border);border-radius:8px;padding:24px;
                      text-align:center;cursor:pointer;transition:all .2s;background:var(--adm-bg);"
               onclick="document.getElementById('imageFile').click()"
               ondragover="event.preventDefault();this.style.borderColor='var(--adm-accent)'"
               ondragleave="this.style.borderColor='var(--adm-border)'"
               ondrop="handleDrop(event)">
            <i class="bi bi-cloud-upload" style="font-size:2rem;color:var(--adm-muted);"></i>
            <div class="small mt-1" style="color:var(--adm-muted);">
              Drag & drop or <span style="color:var(--adm-accent);font-weight:600;">click to upload</span>
            </div>
            <div style="font-size:.72rem;color:var(--adm-muted);margin-top:4px;">
              JPG, PNG, WebP — max 2MB
            </div>
          </div>

          <input type="file" id="imageFile" name="image_file"
                 accept="image/jpeg,image/png,image/webp,image/gif"
                 style="display:none;" onchange="previewImage(this)">

          <!-- Preview -->
          <div id="imagePreview" style="display:none;margin-top:12px;">
            <img id="previewImg" src="" alt="Preview"
                 style="max-width:200px;max-height:200px;object-fit:cover;
                        border-radius:8px;border:1px solid var(--adm-border);">
            <div class="small mt-1" style="color:var(--adm-muted);" id="previewName"></div>
            <button type="button" onclick="clearImage()"
                    class="btn btn-sm btn-outline-danger mt-1">
              <i class="bi bi-x me-1"></i>Remove
            </button>
          </div>

          <!-- Keep existing URL option -->
          <div class="mt-2">
            <label class="form-label fw-semibold small mt-2">
              Or paste an image URL
              <small class="text-muted fw-normal">(optional, overridden by upload)</small>
            </label>
            <input type="url" name="image_url" id="imageUrl" class="form-control form-control-sm"
                   placeholder="https://example.com/image.jpg"
                   value="<?= esc(!$isUploaded ? ($product['image_url'] ?? '') : '') ?>">
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
    <div class="card p-3 mb-3" style="border-left:4px solid var(--adm-accent);">
      <h6 class="fw-semibold mb-2" style="color:var(--adm-primary);">
        <i class="bi bi-image me-1"></i>Image Tips
      </h6>
      <ul class="small text-adm-muted mb-0 ps-3">
        <li class="mb-1">Use a <strong>square image</strong> (1:1 ratio) for best results</li>
        <li class="mb-1">Recommended size: <strong>600×600px</strong> or larger</li>
        <li class="mb-1">Max file size: <strong>2MB</strong></li>
        <li class="mb-1">Formats: JPG, PNG, WebP</li>
        <li>White or transparent background works best</li>
      </ul>
    </div>

    <div class="card p-3" style="border-left:4px solid var(--adm-green);">
      <h6 class="fw-semibold mb-2" style="color:var(--adm-primary);">
        <i class="bi bi-lightbulb me-1"></i>Pricing Guide
      </h6>
      <ul class="small text-adm-muted mb-0 ps-3">
        <li class="mb-1"><strong>Cost price</strong> — what you pay the supplier</li>
        <li class="mb-1"><strong>Selling price</strong> — what customers pay</li>
        <li class="mb-1">Target margin: <strong>30%+</strong> for healthy profit</li>
        <li>Set reorder level to trigger low-stock alerts</li>
      </ul>
    </div>
  </div>
</div>

<script>
function previewImage(input) {
    var file = input.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        alert('Image must be under 2MB');
        input.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('previewName').textContent = file.name + ' (' + (file.size/1024).toFixed(0) + 'KB)';
        document.getElementById('imagePreview').style.display = 'block';
        document.getElementById('dropZone').style.display = 'none';
        // Clear URL field when file is selected
        document.getElementById('imageUrl').value = '';
    };
    reader.readAsDataURL(file);
}

function clearImage() {
    document.getElementById('imageFile').value = '';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('dropZone').style.display = 'block';
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').style.borderColor = 'var(--adm-border)';
    var file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        var dt = new DataTransfer();
        dt.items.add(file);
        var input = document.getElementById('imageFile');
        input.files = dt.files;
        previewImage(input);
    }
}

// Margin calculator
(function(){
    var cost = document.querySelector('[name=cost_price]');
    var sell = document.querySelector('[name=selling_price]');
    var box  = document.getElementById('marginPreview');
    function calc() {
        var c = parseFloat(cost.value), s = parseFloat(sell.value);
        if (!c || !s || s <= 0) { box.style.display = 'none'; return; }
        var prof   = s - c;
        var margin = (prof / s * 100).toFixed(1);
        var markup = (prof / c * 100).toFixed(1);
        var clr    = margin >= 30 ? 'var(--adm-green)' : (margin >= 10 ? 'var(--adm-orange)' : 'var(--adm-red)');
        document.getElementById('ppUnit').textContent   = 'KES ' + prof.toFixed(2);
        document.getElementById('ppUnit').style.color   = clr;
        document.getElementById('ppMargin').textContent = margin + '%';
        document.getElementById('ppMargin').style.color = clr;
        document.getElementById('ppMarkup').textContent = markup + '%';
        box.style.display = 'block';
    }
    cost.addEventListener('input', calc);
    sell.addEventListener('input', calc);
    calc();
})();
</script>