<?php
require_once __DIR__ . '/_settings_guard.php';
require_once __DIR__ . '/_settings_meta.php';
settings_apply_context();
require_once __DIR__ . '/../layout/app_start.php';

function normalize_whatsapp_number(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    if (stripos($s, 'wa.me') !== false || stripos($s, 'whatsapp') !== false) return $s;
    return preg_replace('~\D+~', '', $s) ?? '';
}

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('verify_csrf')) { verify_csrf(); }

    try {
        $old_token = (string)settings_get('telegram.bot_token', '');
        $new_token = trim((string)($_POST['telegram_bot_token'] ?? ''));
        $token_to_save = ($new_token === '') ? $old_token : $new_token;

        settings_save([
            'social.facebook'   => trim((string)($_POST['social_facebook'] ?? '')),
            'social.twitter'    => trim((string)($_POST['social_twitter'] ?? '')),
            'social.youtube'    => trim((string)($_POST['social_youtube'] ?? '')),
            'social.telegram'   => trim((string)($_POST['social_telegram'] ?? '')),
            'social.instagram'  => trim((string)($_POST['social_instagram'] ?? '')),
            'social.whatsapp'   => normalize_whatsapp_number((string)($_POST['social_whatsapp'] ?? '')),

            'facebook.app_id'   => trim((string)($_POST['facebook_app_id'] ?? '')),
            'facebook.pixel_id' => trim((string)($_POST['facebook_pixel_id'] ?? '')),

            'telegram.bot_token' => $token_to_save,
            'telegram.chat_id'   => trim((string)($_POST['telegram_chat_id'] ?? '')),
        ]);

        $notice = __('t_08ed73869e', 'تم حفظ إعدادات السوشيال بنجاح.');
    } catch (Throwable $e) {
        $error = __('t_4fa410044f', 'حدث خطأ أثناء الحفظ.');
        @error_log('[settings_social] ' . $e->getMessage());
    }
}

$social_facebook  = settings_get('social.facebook', '');
$social_twitter   = settings_get('social.twitter', '');
$social_youtube   = settings_get('social.youtube', '');
$social_telegram  = settings_get('social.telegram', '');
$social_instagram = settings_get('social.instagram', '');
$social_whatsapp  = settings_get('social.whatsapp', '');

$facebook_app_id   = settings_get('facebook.app_id', '');
$facebook_pixel_id = settings_get('facebook.pixel_id', '');
$telegram_chat_id  = settings_get('telegram.chat_id', '');
$telegram_token_exists = settings_get('telegram.bot_token', '') !== '';
?>

<div class="row g-3">
    <div class="col-md-3">
      <?php include __DIR__ . '/_settings_nav.php'; ?>
    </div>

    <div class="col-md-9">
      <div class="card p-4">
<?php if ($notice): ?>
          <div class="alert alert-success"><?= h($notice) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <?php endif; ?>

          <h6 class="mb-2"><?= h(__('t_d6acd49b90', 'روابط السوشيال')) ?></h6>

          <div class="mb-3">
            <label class="form-label">Facebook</label>
            <input class="form-control" name="social_facebook" value="<?= h($social_facebook) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Twitter / X</label>
            <input class="form-control" name="social_twitter" value="<?= h($social_twitter) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">YouTube</label>
            <input class="form-control" name="social_youtube" value="<?= h($social_youtube) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_7fa2fe62b8', 'Telegram (قناة/يوزر/رابط)')) ?></label>
            <input class="form-control" name="social_telegram" value="<?= h($social_telegram) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Instagram</label>
            <input class="form-control" name="social_instagram" value="<?= h($social_instagram) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_231c53c0d0', 'WhatsApp (رقم دولي أو رابط wa.me)')) ?></label>
            <input class="form-control" name="social_whatsapp" value="<?= h($social_whatsapp) ?>">
            <div class="form-text"><?= h(__('t_c9b368b380', 'مثال: 9665xxxxxxxx أو https://wa.me/9665xxxxxxxx')) ?></div>
          </div>

          <hr>

          <h6 class="mb-2"><?= h(__('t_bafc936dfb', 'تكاملات (اختياري)')) ?></h6>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Facebook App ID</label>
              <input class="form-control" name="facebook_app_id" value="<?= h($facebook_app_id) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Facebook Pixel ID</label>
              <input class="form-control" name="facebook_pixel_id" value="<?= h($facebook_pixel_id) ?>">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">
                Telegram Bot Token
                <?php if ($telegram_token_exists): ?>
                  <span class="badge bg-success ms-1"><?= h(__('t_72141a31a4', 'محفوظ')) ?></span>
                <?php else: ?>
                  <span class="badge bg-secondary ms-1"><?= h(__('t_4856850b14', 'غير مضبوط')) ?></span>
                <?php endif; ?>
              </label>
              <input class="form-control" type="password" name="telegram_bot_token" value="" placeholder="<?= h(__('t_0a28bb2d32', 'اتركه فارغاً للاحتفاظ بالقيمة الحالية')) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Telegram Chat ID</label>
              <input class="form-control" name="telegram_chat_id" value="<?= h($telegram_chat_id) ?>">
            </div>
          </div>

          <button class="btn btn-primary"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
        </form>
      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
