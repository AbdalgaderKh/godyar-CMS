<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
/**
 * Godyar Admin — AI helper endpoint
 *
 * هذا الملف يقدّم نقاط نهاية بسيطة مدعومة بالذكاء الاصطناعي
 * لتوليد العناوين، الملخصات، وتحسين المحتوى.
 *
 * ملاحظة مهمة:
 * - يجب ضبط متغير البيئة OPENAI_API_KEY في الخادم حتى يتم تفعيل التكامل الفعلي مع OpenAI.
 * - في حال عدم تفعيل المفتاح، سيُرجع السكربت اقتراحات بسيطة محلية بدون استدعاء خارجي
 *   ولن يسبب أي خطأ 500.
 */

// تحديد مسار الجذر (مجلد godyar)
$root = realpath(__DIR__ . '/../../');
if ($root === false) {
    $root = dirname(__DIR__, 2);
}

// تحميل bootstrap & auth إن وُجدا، لكن بدون كسر النظام إن لم يكونا موجودين
$bootstrap = $root . '/includes/bootstrap.php';
$authFile  = $root . '/includes/auth.php';

if (is_file($bootstrap)) {
    require_once $bootstrap;
}
if (is_file($authFile)) {
    require_once $authFile;
}

if (php_sapi_name() === 'cli') {
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول إن توفرت فئة Auth
if (class_exists('Godyar\\Auth') && method_exists('Godyar\\Auth', 'isLoggedIn')) {
    if (!\Godyar\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => __('t_6a59df050e', 'غير مصرح – الرجاء تسجيل الدخول من جديد.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => __('t_a8c3b46d2d', 'طريقة الطلب غير مسموحة. استخدم POST فقط.')], JSON_UNESCAPED_UNICODE);
    exit;
}

$action  = isset($_POST['action']) ? (string)$_POST['action'] : '';
$title   = trim((string)($_POST['title'] ?? ''));
$excerpt = trim((string)($_POST['excerpt'] ?? ''));
$content = trim((string)($_POST['content'] ?? ''));
$topic   = trim((string)($_POST['topic'] ?? ''));

if ($action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => __('t_0759958c50', 'لم يتم تحديد نوع المهمة المطلوبة.')], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * دالة مساعده لاستدعاء OpenAI إن تم تفعيل المفتاح
 */
function godyar_ai_call(string $systemPrompt, string $userPrompt, int $maxTokens = 256): ?string
{
    $apiKey = getenv('OPENAI_API_KEY') ?: '';

    if ($apiKey === '') {
        return null;
    }

    if (!function_exists('curl_init')) {
        return null;
    }

    $payload = [
        'model' => 'gpt-4.1-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ],
        'max_tokens' => $maxTokens,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return null;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }

    $text = $data['choices'][0]['message']['content'] ?? '';
    if (!is_string($text) || trim($text) === '') {
        return null;
    }

    return trim($text);
}

function godyar_local_title(string $topic, string $content): string
{
    $base = $topic !== '' ? $topic : mb_substr($content, 0, 60);
    $base = trim($base);
    if ($base === '') {
        $base = __('t_2ccf096217', 'عنوان خبر جديد');
    }
    return __('t_6381e8ec2c', 'تغطية خاصة: ') . $base;
}

function godyar_local_excerpt(string $content): string
{
    $clean = strip_tags($content);
    if ($clean === '') {
        $clean = __('t_eab7b6c972', 'ملخص قصير للخبر سيتم توليده تلقائياً بعد كتابة المحتوى.');
    }
    if (mb_strlen($clean) > 180) {
        $clean = mb_substr($clean, 0, 177) . '...';
    }
    return $clean;
}

function godyar_local_improve(string $content): string
{
    if (trim($content) === '') {
        return __('t_9edc9ef306', 'الرجاء كتابة فقرة أولاً ليتم تحسينها.');
    }
    $text = preg_replace('/[ \t]+/u', ' ', $content);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);
    return trim($text);
}

try {
    switch ($action) {
        case 'suggest_title':
            $topicText   = $topic !== '' ? $topic : $content;
            $aiResult    = godyar_ai_call(
                __('t_80b24fb13b', 'أنت مساعد تحرير أخبار عربي محترف. اكتب عنواناً صحفياً قصيراً وجذاباً من 60 حرفاً كحد أقصى دون رموز إضافية.'),
                $topicText,
                64
            );
            $finalTitle = $aiResult ?? godyar_local_title($topic, $content);
            echo json_encode(['success' => true, 'title' => $finalTitle], JSON_UNESCAPED_UNICODE);
            break;

        case 'suggest_excerpt':
            $source = $content !== '' ? $content : $title;
            $aiResult = godyar_ai_call(
                __('t_5e9ccfcfc8', 'أنت مساعد كتابة عربي. اكتب ملخصاً تعريفياً موجزاً لخبر بالعربية من 160 حرفاً تقريباً بدون تنسيق HTML.'),
                $source,
                96
            );
            $finalExcerpt = $aiResult ?? godyar_local_excerpt($source);
            echo json_encode(['success' => true, 'excerpt' => $finalExcerpt], JSON_UNESCAPED_UNICODE);
            break;

        case 'improve_content':
            $aiResult = godyar_ai_call(
                __('t_96682a34f8', 'حسّن النص العربي التالي لغوياً وأسلوبياً مع الحفاظ على المعنى، وأعده منسقاً في فقرات قصيرة مناسبة للنشر كمحتوى خبر. لا تضف عناوين فرعية.'),
                $content,
                512
            );
            $finalContent = $aiResult ?? godyar_local_improve($content);
            echo json_encode(['success' => true, 'content' => $finalContent], JSON_UNESCAPED_UNICODE);
            break;

        case 'suggest_seo_tags':
            $source = $title . ' ' . $excerpt . ' ' . $content;
            $aiResult = godyar_ai_call(
                __('t_6ab1eef631', 'استخرج حتى 8 كلمات مفتاحية عربية مفصولة بفواصل، بدون أرقام أو علامات اقتباس، مناسبة لتحسين ظهور الخبر في محركات البحث.'),
                $source,
                64
            );
            if ($aiResult === null) {
                $words = preg_split('/\s+/u', strip_tags($source));
                $words = array_filter($words, function ($w) {
                    $w = trim($w);
                    return mb_strlen($w) >= 4;
                });
                $words = array_slice(array_unique($words), 0, 8);
                $aiResult = implode(', ', $words);
            }
            echo json_encode(['success' => true, 'tags' => trim($aiResult)], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => __('t_855f8ff1b4', 'نوع المهمة غير مدعوم.')], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    @error_log('[Godyar AI] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => __('t_296a798734', 'حدث خطأ غير متوقع أثناء معالجة طلب الذكاء الاصطناعي.')], JSON_UNESCAPED_UNICODE);
}
