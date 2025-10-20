<?php

declare(strict_types=1);

$rootDir    = realpath(__DIR__ . '/../');
$rootDir    = realpath(__DIR__ . '/../');
$configFile = $rootDir . '/config.php';
$flagFile   = $rootDir . '/.installed';

function respondError(string $message, int $statusCode = 500): void {
    http_response_code($statusCode);
    echo $message;
    exit;
}

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

if (file_exists($flagFile)) {
    header('Location: index.php?action=installed');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$token       = trim($_POST['token']       ?? '');
$adminId     = trim($_POST['admin_id']     ?? '');
$botUsername = trim($_POST['bot_username'] ?? '');
$dbName      = trim($_POST['db_name']      ?? '');
$dbUser      = trim($_POST['db_user']      ?? '');
$dbPass      = trim($_POST['db_pass']      ?? '');
$siteUrl     = trim($_POST['site_url']     ?? '');

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

function isValidTelegramToken(string $token): bool {
    return (bool)preg_match('/^\d{6,12}:[A-Za-z0-9_-]{35}$/', $token);
}

function isValidTelegramId(string $id): bool {
    return (bool)preg_match('/^\d{6,12}$/', $id);
}

if ($token === '' || $adminId === '' || $botUsername === '' || $dbName === '' || $dbUser === '' || $dbPass === '' || $siteUrl === '') {
    respondError('لطفاً تمام فیلدهای فرم را پر کنید.', 400);
}
if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
    respondError('آدرس سایت معتبر نیست.', 400);
}
if (stripos($siteUrl, 'https://') !== 0) {
    respondError('آدرس سایت باید با https شروع شود.', 400);
}

if (!isValidTelegramToken($token)) {
    respondError('توکن ربات واردشده معتبر نیست.', 400);
}
if (!isValidTelegramId($adminId)) {
    respondError('شناسهٔ عددی ادمین نامعتبر است.', 400);
}

$botInfo = telegramApi($token, 'getMe');
if (!is_array($botInfo) || empty($botInfo['ok']) || empty($botInfo['result']['username'])) {
    respondError('توکن ربات را بررسی کنید. عدم توانایی دریافت اطلاعات ربات.', 400);
}

$chatInfo = telegramApi($token, 'getChat', ['chat_id' => $adminId]);
if (!is_array($chatInfo) || empty($chatInfo['ok'])) {
    respondError('حساب مدیر شناسایی نشد. ابتدا ربات را استارت کنید و سپس دوباره نصب را امتحان کنید.', 400);
}

$botUsername = $botInfo['result']['username'] ?? $botUsername;

$parts = parse_url($siteUrl);
$host = $parts['host'] ?? '';
$path = isset($parts['path']) ? trim($parts['path'], '/') : '';
if ($host === '') {
    respondError('دامنهٔ سایت قابل تشخیص نیست.', 400);
}
$domainHost = $host . ($path !== '' ? '/' . $path : '');


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli('localhost', $dbUser, $dbPass, $dbName);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    respondError('خطا در اتصال به پایگاه داده: ' . $e->getMessage(), 500);
}

writeConfig($configFile, [
    'db_name'      => $dbName,
    'db_user'      => $dbUser,
    'db_pass'      => $dbPass,
    'token'        => $token,
    'admin_id'     => $adminId,
    'domain_host'  => $domainHost,
    'bot_username' => $botUsername,
]);

$tableFile      = $rootDir . '/table.php';
$backupTable    = $rootDir . '/table.php.bak';
if (is_file($tableFile) && !is_file($backupTable)) {
    try {
        ob_start();
        require $tableFile;
        ob_end_clean();
    } catch (Throwable $e) {
    }
    @copy($tableFile, $backupTable);
    @unlink($tableFile);
}

@file_put_contents($flagFile, 'installed:' . date('c'));

registerWebhook($token, $siteUrl);

header('Location: index.php?action=ok');
exit;