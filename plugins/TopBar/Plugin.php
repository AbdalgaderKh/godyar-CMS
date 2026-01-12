<?php
// plugins/TopBar/Plugin.php
// يجب أن يُرجع كائن يطبّق GodyarPluginInterface

return new class implements GodyarPluginInterface {

    private string $configFile;

    public function __construct()
    {
        $this->configFile = __DIR__ . '/config.json';
    }

    public function register(PluginManager $pm): void
    {
        // شريط أعلى الموقع
        $pm->addHook('frontend_top_bar', [$this, 'renderTopBar'], 10);
    }

    /**
     * تحميل إعدادات الإضافة من config.json
     */
    private function loadConfig(): array
    {
        $defaults = [
            'bar_enabled'   => true,
            'message'       => 'مرحبًا بك في موقعنا! 👋',
            'bg_color'      => '#111827', // رمادي غامق
            'text_color'    => '#ffffff',
            'position'      => 'fixed',   // fixed | static
            'closable'      => true,
            'show_on_paths' => '*',       // * = كل الصفحات، أو قائمة مفصولة بفواصل
        ];

        if (!is_file($this->configFile)) {
            return $defaults;
        }

        $json = @file_get_contents($this->configFile);
        if (!is_string($json) || $json === '') {
            return $defaults;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $defaults;
        }

        return array_merge($defaults, $data);
    }

    /**
     * طباعة الشريط العلوي
     */
    public function renderTopBar(): void
    {
        $cfg = $this->loadConfig();

        if (empty($cfg['bar_enabled'])) {
            return;
        }

        // فلترة حسب المسار إن لزم
        $path      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $showPaths = (string)($cfg['show_on_paths'] ?? '*');

        if ($showPaths !== '*') {
            $allowed = array_filter(array_map('trim', explode(',', $showPaths)));
            $match   = false;
            foreach ($allowed as $p) {
                if ($p !== '' && str_starts_with($path, $p)) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                return;
            }
        }

        $bg   = (string)$cfg['bg_color'];
        $txt  = (string)$cfg['text_color'];
        $msg  = (string)$cfg['message'];
        $pos  = (string)$cfg['position']; // fixed / static
        $clos = !empty($cfg['closable']);

        $positionCss = $pos === 'fixed'
            ? 'position:fixed;top:0;right:0;left:0;z-index:9999;'
            : 'position:relative;';

        // مفتاح التخزين المحلي لإغلاق الشريط
        $storageKey = 'godyar_topbar_closed';

        ?>
        <div
          id="godyar-topbar"
          style="<?= htmlspecialchars($positionCss, ENT_QUOTES, 'UTF-8') ?>background:<?= htmlspecialchars($bg, ENT_QUOTES, 'UTF-8') ?>;color:<?= htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') ?>;padding:8px 16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;">
          <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div style="display:flex;align-items:center;gap:8px;flex:1;">
              <span style="font-size:16px;">📢</span>
              <div style="line-height:1.4;">
                <?= $msg // يُفضل تعديله من لوحة التحكم بنص موثوق ?>
              </div>
            </div>
            <?php if ($clos): ?>
              <button
                type="button"
                id="godyar-topbar-close"
                aria-label="إغلاق الشريط"
                style="border:none;background:transparent;color:inherit;cursor:pointer;font-size:16px;padding:4px 8px;">
                ✕
              </button>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($pos === 'fixed'): ?>
        <script>
        (function() {
          const bar   = document.getElementById('godyar-topbar');
          if (!bar) return;

          const key   = '<?= $storageKey ?>';

          try {
            if (window.localStorage && localStorage.getItem(key) === '1') {
              bar.style.display = 'none';
              return;
            }
          } catch (e) {}

          const btn = document.getElementById('godyar-topbar-close');
          if (btn) {
            btn.addEventListener('click', function() {
              bar.style.display = 'none';
              try {
                if (window.localStorage) {
                  localStorage.setItem(key, '1');
                }
              } catch (e) {}
            });
          }

          // دفع محتوى الصفحة للأسفل قليلاً لو كان الشريط ثابت
          document.addEventListener('DOMContentLoaded', function() {
            try {
              const first = document.body.firstElementChild;
              if (first && bar && bar.style.display !== 'none') {
                const h = bar.getBoundingClientRect().height;
                if (h > 0) {
                  document.body.style.paddingTop = h + 'px';
                }
              }
            } catch (e) {}
          });
        })();
        </script>
        <?php endif; ?>
        <?php
    }
};
