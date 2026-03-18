<?php if (!defined('APP_RUNNING')) exit; ?>
</main>
<footer class="py-3 text-center border-top" style="font-size:.8rem;color:var(--adm-muted);">
  &copy; <?= date('Y') ?> SalesIQ Admin
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if (isset($extraScript)): ?><script><?= $extraScript ?></script><?php endif; ?>
</body>
</html>
