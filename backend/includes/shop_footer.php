<?php if (!defined('APP_RUNNING')) exit; ?>
</main>

<footer class="shop-footer mt-auto">
  <div class="container">
    <div class="row g-4 py-4">
      <div class="col-md-4">
        <div class="shop-brand mb-2"><i class="bi bi-bag-heart-fill"></i><span>SalesIQ</span></div>
        <p class="small opacity-75 mb-0">Quality products, secure M-Pesa payments, fast Kenya-wide delivery.</p>
      </div>
      <div class="col-md-4">
        <h6 class="fw-bold text-white mb-2">Shop</h6>
        <div class="d-flex flex-column gap-1">
          <a href="<?= url('shop.php') ?>"      class="footer-link">All Products</a>
          <a href="<?= url('cart.php') ?>"      class="footer-link">Cart</a>
          <a href="<?= url('my_orders.php') ?>" class="footer-link">My Orders</a>
        </div>
      </div>
      <div class="col-md-4">
        <h6 class="fw-bold text-white mb-2">Contact</h6>
        <p class="small opacity-75 mb-1"><i class="bi bi-telephone me-2"></i>+254 700 000 000</p>
        <p class="small opacity-75 mb-1"><i class="bi bi-envelope me-2"></i>support@salesiq.co.ke</p>
        <p class="small opacity-75 mb-0"><i class="bi bi-geo-alt me-2"></i>Nairobi, Kenya</p>
      </div>
    </div>
    <div class="border-top border-white border-opacity-25 pt-3 pb-2 text-center">
      <small class="opacity-50">&copy; <?= date('Y') ?> SalesIQ</small>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('frontend/js/shop.js') ?>"></script>
<?php if (isset($extraScript)): ?><script><?= $extraScript ?></script><?php endif; ?>
</body>
</html>
