<?php
// includes/helpers.php
// دوال مساعدة إضافية لموقع جويار

declare(strict_types=1);

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// polyfill بسيط في حال السيرفر لا يدعم str_starts_with (احتياطاً)
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

// محاولة تحميل إعدادات الذكاء الاصطناعي لو الملف موجود
$gdyAiConfigFile = __DIR__ . '/ai_config.php';
if (is_file($gdyAiConfigFile)) {
    require_once $gdyAiConfigFile;
}

/**
 * تطبيق قاموس المصطلحات على HTML المقال
 * - يبحث عن المصطلحات الفعّالة في جدول gdy_glossary
 * - يضيف span مع data-definition ليظهر التلميح في الواجهة
 * هذا هو الوضع اليدوي القديم (يمكن الإبقاء عليه أو تجاهله إذا اعتمدت على الذكاء الاصطناعي فقط).
 */
if (!function_exists('gdy_apply_glossary')) {
    function gdy_apply_glossary(PDO $pdo, string $html): string
    {
        static $cache = null;

        if ($html === '') {
            return $html;
        }

        if ($cache === null) {
            try {
                $stmt = $pdo->query("SELECT term, short_definition FROM gdy_glossary WHERE is_active = 1 ORDER BY CHAR_LENGTH(term) DESC");
                $cache = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                @error_log('[gdy_glossary] fetch terms failed: ' . $e->getMessage());
                $cache = [];
            }
        }

        if (!$cache) {
            return $html;
        }

        foreach ($cache as $row) {
            $term = trim((string)($row['term'] ?? ''));
            $def  = trim((string)($row['short_definition'] ?? ''));

            if ($term === '' || $def === '') {
                continue;
            }

            $pattern = '/(' . preg_quote($term, '/') . ')/u';

            // نستخدم دالة استبدال مخصصة حتى لا نغيّر داخل HTML tags
            $html = preg_replace_callback($pattern, function ($m) use ($def) {
                $text = $m[1];

                // لا نلفّ داخل attributes أو داخل tags
                if (preg_match('/^<|>$/', $text)) {
                    return $text;
                }

                return '<span class="gdy-glossary-term" data-definition="' . h($def) . '">' . $text . '</span>';
            }, $html, 3);
        }

        return $html;
    }
}

/**
 * استدعاء ChatGPT لاستخراج المصطلحات + تعريفاتها من نص عربي
 * يعمل تلقائياً بدون أي تدخل يدوي من لوحة التحكم
 */
if (!function_exists('gdy_ai_glossary_suggest_terms')) {
    function gdy_ai_glossary_suggest_terms(string $plainText): array
    {
        $plainText = trim($plainText);
        if ($plainText === '') {
            return [];
        }

        // لو لم يتم تعريف مفتاح الـ API لا نفعل شيئاً
        if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
            @error_log('[gdy_ai_glossary] OPENAI_API_KEY is not defined');
            return [];
        }

        // نختصر النص لو طويل جداً حتى لا نستهلك توكنات كثيرة
        if (mb_strlen($plainText, 'UTF-8') > 4000) {
            $plainText = mb_substr($plainText, 0, 4000, 'UTF-8');
        }

        $endpoint = 'https://api.openai.com/v1/chat/completions';

        $systemPrompt = 'أنت خبير لغة عربية لموقع أخبار.
مهمتك قراءة نص خبر أو مقال، واختيار المصطلحات أو الأسماء أو الاختصارات
التي قد تحتاج شرحاً للقارئ العربي العادي، ثم إعطاء تعريف مبسط لكل مصطلح.

القواعد:
- اختر من 3 إلى 8 مصطلحات كحد أقصى.
- تجنّب الكلمات السهلة والواضحة جداً.
- اكتب التعريف بجملتين أو ثلاث جمل قصيرة.
- أرجِع النتيجة بصيغة JSON فقط، بدون أي كلام إضافي.
- مثال:
[{"term": "الفيدرالي الأمريكي", "definition": "هو البنك المركزي للولايات المتحدة..."}]';

        $userPrompt = "النص:\n" . $plainText;

        $payload = [
            'model' => 'gpt-4.1-mini', // نموذج سريع واقتصادي من OpenAI
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role'    => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'max_tokens'  => 320,
            'temperature' => 0.3,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: ' . 'Bearer ' . OPENAI_API_KEY,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            @error_log('[gdy_ai_glossary] curl error: ' . curl_error($ch));
            curl_close($ch);
            return [];
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($status >= 400 || !is_array($data)) {
            @error_log('[gdy_ai_glossary] bad status: ' . $status . ' body: ' . $response);
            return [];
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        $content = trim((string)$content);

        // أحياناً يرجّع ```json ... ```
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z0-9]*\s*/', '', $content);
            $content = preg_replace('/```$/', '', $content);
            $content = trim($content);
        }

        $terms = json_decode($content, true);
        if (!is_array($terms)) {
            @error_log('[gdy_ai_glossary] JSON decode failed: ' . $content);
            return [];
        }

        $clean = [];
        foreach ($terms as $row) {
            $term = trim((string)($row['term'] ?? ''));
            $def  = trim((string)($row['definition'] ?? ''));

            if ($term === '' || $def === '') {
                continue;
            }

            $clean[] = [
                'term'       => $term,
                'definition' => $def,
            ];
        }

        return $clean;
    }
}

/**
 * يلفّ المصطلحات داخل HTML المقال باستخدام النتائج القادمة من ChatGPT
 * بدون أي تدخل يدوي
 */
if (!function_exists('gdy_ai_glossary_annotate')) {
    function gdy_ai_glossary_annotate(string $html): string
    {
        $html  = (string)$html;
        $plain = trim(strip_tags($html));

        // لو النص قصير جداً، لا داعي للاتصال بالـ API
        if (mb_strlen($plain, 'UTF-8') < 80) {
            return $html;
        }

        $terms = gdy_ai_glossary_suggest_terms($plain);
        if (!$terms) {
            return $html;
        }

        foreach ($terms as $row) {
            $term = trim($row['term']);
            $def  = trim($row['definition']);

            if ($term === '' || $def === '') {
                continue;
            }

            // نهرب المصطلح للاستخدام في regex
            $pattern = '/' . preg_quote($term, '/') . '/u';

            $replacement = '<span class="gdy-glossary-term" data-definition="' . h($def) . '">$0</span>';

            // نستبدل أول 3 مرات فقط من كل مصطلح
            $html = preg_replace($pattern, $replacement, $html, 3);
        }

        return $html;
    }
}
