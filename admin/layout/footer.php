<?php
declare(strict_types=1);

// admin/layout/footer.php
// ملاحظة: Bootstrap JS يتم تحميله في header.php.
// هذا الفوتر يضيف فقط سكربتات الصفحات (إن وُجدت).
?>
<?php
$__base = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';
$__admin = $__base . '/admin';
?>
<script src="<?= htmlspecialchars($__admin . '/assets/js/saved-filters.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script src="<?= htmlspecialchars($__admin . '/assets/js/inline-edit.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script src="<?= htmlspecialchars($__admin . '/assets/js/admin-csp.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>

<?php // Per-page scripts injected by pages (Saved Filters init, etc.) ?>
<?php if (!empty($pageScripts)) { echo $pageScripts; } ?>

</body>
</html>
