    </main>
  </div>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php if (!empty($pageScript) && is_string($pageScript)): ?>
  <?= $pageScript ?>
<?php endif; ?>
</body>
</html>
