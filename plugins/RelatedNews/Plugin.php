<?php
// plugins/RelatedNews/Plugin.php
// يجب أن يُرجع كائن يطبّق GodyarPluginInterface

return new class implements GodyarPluginInterface {

    public function register(PluginManager $pm): void
    {
        // hook يتم استدعاؤه أسفل محتوى صفحة الخبر
        $pm->addHook('frontend_news_after', [$this, 'renderRelated'], 10);
    }

    /**
     * عرض مقالات ذات صلة أسفل الخبر
     *
     * ملاحظة:
     *   سيتم استدعاؤها من صفحة الخبر كالتالي:
     *     g_do_hook('frontend_news_after', $newsRow, $pdo);
     *
     * @param array $news      الصف الحالي (صف الخبر المعروض)
     * @param \PDO  $pdo       اتصال قاعدة البيانات
     */
    public function renderRelated(array $news, \PDO $pdo): void
    {
        $currentId  = (int)($news['id'] ?? 0);
        $categoryId = isset($news['category_id']) ? (int)$news['category_id'] : 0;

        if ($currentId <= 0) {
            return;
        }

        try {
            // إذا ما عنده تصنيف، نطلع آخر الأخبار المنشورة فقط
            if ($categoryId > 0) {
                $sql = "
                    SELECT id, title, slug, created_at
                    FROM news
                    WHERE status = 'published'
                      AND id <> :id
                      AND category_id = :cat
                    ORDER BY created_at DESC
                    LIMIT 4
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id'  => $currentId,
                    ':cat' => $categoryId,
                ]);
            } else {
                $sql = "
                    SELECT id, title, slug, created_at
                    FROM news
                    WHERE status = 'published'
                      AND id <> :id
                    ORDER BY created_at DESC
                    LIMIT 4
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => $currentId,
                ]);
            }

            $related = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            @error_log('[RelatedNews] ' . $e->getMessage());
            return;
        }

        if (!$related) {
            return;
        }

        // رابط الخبر الأمامي (عدّل حسب الروتر عندك)
        $makeUrl = function(array $row): string {
            $id   = (int)($row['id'] ?? 0);
            $slug = (string)($row['slug'] ?? '');
            if ($slug !== '') {
                // مثال: index.php?page=news_detail&slug=...
                return '/godyar/index.php?page=news_detail&slug=' . urlencode($slug);
            }
            return '/godyar/index.php?page=news_detail&id=' . $id;
        };

        ?>
        <section class="related-news my-5">
          <h3 class="mb-3">مقالات ذات صلة</h3>
          <div class="row">
            <?php foreach ($related as $item): ?>
              <div class="col-md-6 col-lg-3 mb-3">
                <article class="card h-100">
                  <div class="card-body">
                    <h4 class="h6 card-title">
                      <a href="<?= htmlspecialchars($makeUrl($item), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8') ?>
                      </a>
                    </h4>
                    <?php if (!empty($item['created_at'])): ?>
                      <div class="text-muted small mt-1">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        <?= htmlspecialchars((string)$item['created_at'], ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php
    }
};
