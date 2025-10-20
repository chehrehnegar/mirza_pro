<?php

declare(strict_types=1);

$rootDir    = realpath(__DIR__ . '/../');
$rootDir    = realpath(__DIR__ . '/../');
// مسیرهای فایل پیکربندی و پرچم نصب
$configFile = $rootDir . '/config.php';
$flagFile   = $rootDir . '/.installed';

/** ارسال پاسخ خطا همراه با کد مناسب و متوقف کردن برنامه. */
function respondError(string $message, int $statusCode = 500): void {
    http_response_code($statusCode);
    echo $message;
    exit;
}

/** جایگزینی مقادیر در فایل config.php. نسخهٔ پشتیبان با پسوند .bak ذخیره می‌شود. */
function writeConfig(string $filePath, array $replacements): void {
    $contents = file_get_contents($filePath);
    if ($contents === false) {
        respondError('خطا در خواندن فایل config.php', 500);
    }
    @copy($filePath, $filePath . '.bak');
    @copy($filePath, $filePath . '.bak');
    $contents = str_replace(
        ['{database_name}', '{username_db}', '{password_db}', '{API_KEY}', '{admin_number}', '{domain_name}', '{username_bot}'],
        [
            $replacements['db_name'],
            $replacements['db_user'],
            $replacements['db_pass'],
            $replacements['token'],
            $replacements['admin_id'],
            $replacements['domain_host'],
            $replacements['bot_username'],
        ],
        $contents
    );
    if (file_put_contents($filePath, $contents) === false) {
        respondError('خطا در نوشتن فایل config.php', 500);
    }
}

/** ثبت وب‌هوک برای ربات تلگرام. در صورت موفقیت true برمی‌گرداند. */
function registerWebhook(string $token, string $siteUrl): bool {
    
    $siteUrl = rtrim($siteUrl, '/');
    $hookUrl = $siteUrl . '/index.php';
    
    $endpoint = 'https://api.telegram.org/bot' . urlencode($token) . '/setWebhook?url=' . urlencode($hookUrl);
    $responseBody = @file_get_contents($endpoint);
    
    if ($responseBody === false) {
        
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $responseBody = curl_exec($ch);
            curl_close($ch);
        } else {
            $responseBody = false;
        }
    }
    if ($responseBody === false) {
        return false;
    }
    $json = json_decode($responseBody, true);
    return is_array($json) && !empty($json['ok']);
}

// جلوگیری از نصب مجدد اگر پرچم موجود باشد
if (file_exists($flagFile)) {
    header('Location: index.php?action=installed');
    exit;
}

// فقط متد POST قابل قبول است
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// دریافت و تمیز کردن ورودی‌ها
$token       = trim($_POST['token']       ?? '');
$adminId     = trim($_POST['admin_id']     ?? '');
$botUsername = trim($_POST['bot_username'] ?? '');
$dbName      = trim($_POST['db_name']      ?? '');
$dbUser      = trim($_POST['db_user']      ?? '');
$dbPass      = trim($_POST['db_pass']      ?? '');
$siteUrl     = trim($_POST['site_url']     ?? '');

// تابع کمکی برای فراخوانی API تلگرام و بازگرداندن آرایهٔ JSON یا null در صورت خطا
function telegramApi(string $token, string $method, array $params = []): ?array {
    $query = http_build_query($params);
    $url   = "https://api.telegram.org/bot" . urlencode($token) . "/" . $method;
    if ($query !== '') {
        $url .= '?' . $query;
    }
    $res = @file_get_contents($url);
    if ($res === false) {
        return null;
    }
    return json_decode($res, true);
}

// الگوی صحت توکن ربات (حداقل 6 تا 12 رقم سپس ':' و سپس 35 کاراکتر حروف و عدد و زیرخط)
function isValidTelegramToken(string $token): bool {
    return (bool)preg_match('/^\d{6,12}:[A-Za-z0-9_-]{35}$/', $token);
}

// الگوی صحت شناسه عددی کاربر تلگرام (6 تا 12 رقم)
function isValidTelegramId(string $id): bool {
    return (bool)preg_match('/^\d{6,12}$/', $id);
}

// بررسی صحت ورودی‌ها
if ($token === '' || $adminId === '' || $botUsername === '' || $dbName === '' || $dbUser === '' || $dbPass === '' || $siteUrl === '') {
    respondError('لطفاً تمام فیلدهای فرم را پر کنید.', 400);
}
if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
    respondError('آدرس سایت معتبر نیست.', 400);
}
if (stripos($siteUrl, 'https://') !== 0) {
    respondError('آدرس سایت باید با https شروع شود.', 400);
}

// اعتبارسنجی توکن و شناسهٔ مدیر
if (!isValidTelegramToken($token)) {
    respondError('توکن ربات واردشده معتبر نیست.', 400);
}
if (!isValidTelegramId($adminId)) {
    respondError('شناسهٔ عددی ادمین نامعتبر است.', 400);
}

// دریافت اطلاعات ربات از تلگرام و اطمینان از معتبر بودن توکن
$botInfo = telegramApi($token, 'getMe');
if (!is_array($botInfo) || empty($botInfo['ok']) || empty($botInfo['result']['username'])) {
    respondError('توکن ربات را بررسی کنید. عدم توانایی دریافت اطلاعات ربات.', 400);
}

// بررسی اینکه آیا کاربر (مدیر) ربات را استارت کرده است یا خیر
$chatInfo = telegramApi($token, 'getChat', ['chat_id' => $adminId]);
if (!is_array($chatInfo) || empty($chatInfo['ok'])) {
    respondError('حساب مدیر شناسایی نشد. ابتدا ربات را استارت کنید و سپس دوباره نصب را امتحان کنید.', 400);
}

// به‌روز کردن نام کاربری ربات با مقدار دریافتی از تلگرام (دقت بیشتر)
$botUsername = $botInfo['result']['username'] ?? $botUsername;

// استخراج دامنه و مسیر برای ذخیره در config
$parts = parse_url($siteUrl);
$host = $parts['host'] ?? '';
$path = isset($parts['path']) ? trim($parts['path'], '/') : '';
if ($host === '') {
    respondError('دامنهٔ سایت قابل تشخیص نیست.', 400);
}
// ساختن مقدار domainHost بر اساس دامنه و مسیر واردشده توسط کاربر (بدون تغییر یا افزودن مسیر جدید).
$domainHost = $host . ($path !== '' ? '/' . $path : '');

// آدرس سایت (siteUrl) همان مقداری است که کاربر وارد کرده است. پیش از این فیلترها، اعتبارسنجی شده است.
// این آدرس برای ثبت وب‌هوک استفاده خواهد شد.
// بنابراین، $siteUrl را تغییر نمی‌دهیم و از مقدار خام کاربر (trim شده) استفاده می‌کنیم.

// تست اتصال به پایگاه داده
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli('localhost', $dbUser, $dbPass, $dbName);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    respondError('خطا در اتصال به پایگاه داده: ' . $e->getMessage(), 500);
}

// جایگزینی مقادیر در فایل config.php
writeConfig($configFile, [
    'db_name'      => $dbName,
    'db_user'      => $dbUser,
    'db_pass'      => $dbPass,
    'token'        => $token,
    'admin_id'     => $adminId,
    'domain_host'  => $domainHost,
    'bot_username' => $botUsername,
]);

// ایجاد جداول پایگاه داده در صورت عدم وجود بکاپ table.php.bak
$tableFile      = $rootDir . '/table.php';
$backupTable    = $rootDir . '/table.php.bak';
if (is_file($tableFile) && !is_file($backupTable)) {
    // ابتدا اجرای اسکریپت ساخت جداول برای ایجاد پایگاه داده
    try {
        ob_start();
        require $tableFile;
        ob_end_clean();
    } catch (Throwable $e) {
        // خطا در اجرای table.php را نادیده می‌گیریم؛ کاربر می‌تواند دستی اقدام کند
    }
    // سپس تهیهٔ نسخهٔ پشتیبان و حذف فایل اصلی برای امنیت بیشتر
    @copy($tableFile, $backupTable);
    @unlink($tableFile);
}

// ایجاد فایل علامت نصب
@file_put_contents($flagFile, 'installed:' . date('c'));

// ثبت وب‌هوک؛ در صورت خطا نادیده گرفته می‌شود
registerWebhook($token, $siteUrl);

// هدایت کاربر به صفحه index.php برای مشاهدهٔ نتیجه
header('Location: index.php?action=ok');
exit;