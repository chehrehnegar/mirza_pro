<?php

declare(strict_types=1);

$rootDir = realpath(__DIR__ . '/../');
$flagFile = $rootDir . '/.installed';

$action = $_GET['action'] ?? 'none';

if (file_exists($flagFile) && $action === 'none') {
    $action = 'installed';
}

$checks = [
    'PHP ≥ 8.2'        => version_compare(PHP_VERSION, '8.2.0', '>='),
    'ext: mysqli'      => extension_loaded('mysqli'),
    'ext: PDO_MySQL'   => extension_loaded('pdo_mysql'),
    'ext: cURL'        => extension_loaded('curl'),
    'ext: OpenSSL'     => extension_loaded('openssl'),
    'allow_url_fopen'  => (bool) ini_get('allow_url_fopen'),
];

if ($action === 'createdb') {
    $tableFile = $rootDir . '/table.php';
    $backupFile = $tableFile . '.bak';
    $dbMessage = '';
    if (file_exists($backupFile)) {
        $dbMessage = 'جدول‌ها قبلاً ایجاد شده‌اند و نسخهٔ پشتیبان موجود است.';
    } elseif (!file_exists($tableFile)) {
        $dbMessage = 'فایل table.php پیدا نشد. لطفاً از قرارگیری آن در ریشه پروژه اطمینان حاصل کنید.';
    } else {
        
        @copy($tableFile, $backupFile);
        
        try {
            ob_start();
            include $tableFile;
            ob_end_clean();
            
            @unlink($tableFile);
            $dbMessage = 'جدول‌های پایگاه داده با موفقیت ایجاد شدند. نسخهٔ پشتیبان در table.php.bak ذخیره شد.';
        } catch (Throwable $e) {
            $dbMessage = 'خطا در اجرای table.php: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
    // پیام را ذخیره می‌کنیم تا در بخش HTML نمایش دهیم
    $dbStatusMsg = $dbMessage;
}

?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>نصب ربات میرزا</title>

<!-- بارگذاری فونت‌های Vazir -->
<style>
/* Vazir */
@font-face{font-family:'Vazir';src:url('../app/fonts/Vazir-Light.woff2') format('woff2'),url('../app/fonts/Vazir-Light.woff') format('woff');font-weight:300;font-style:normal;font-display:swap}
@font-face{font-family:'Vazir';src:url('../app/fonts/Vazir-Medium.woff2') format('woff2'),url('../app/fonts/Vazir-Medium.woff') format('woff');font-weight:500;font-style:normal;font-display:swap}
@font-face{font-family:'Vazir';src:url('../app/fonts/Vazir-Bold.woff2') format('woff2'),url('../app/fonts/Vazir-Bold.woff') format('woff');font-weight:700;font-style:normal;font-display:swap}

:root{
  --bg1:#0f172a; --bg2:#1e293b;
  --acc:#22d3ee; --acc2:#38bdf8;
  --ok:#10b981; --err:#ef4444; --txt:#e5e7eb;
  --card:rgba(255,255,255,.07); --card-br:16px; --blur:8px;
  --sp:16px; --fs:14px;
}

*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:'Vazir',system-ui,-apple-system,'Segoe UI',Roboto; color:var(--txt);
  background:
    radial-gradient(1200px 600px at 10% -10%, rgba(56,189,248,.35), transparent 60%),
    radial-gradient(1000px 500px at 90% 110%, rgba(34,211,238,.28), transparent 60%),
    linear-gradient(180deg, var(--bg1), var(--bg2));
  min-height:100vh; display:flex; align-items:center; justify-content:center;
  padding:24px; overflow-y:auto;
}
.wrap{width:100%; max-width:960px}
.card{
  background:var(--card); border:1px solid rgba(255,255,255,.08); border-radius:var(--card-br);
  backdrop-filter:blur(var(--blur)); box-shadow:0 12px 36px rgba(0,0,0,.25); overflow:hidden;
}
.head{padding:18px 22px; display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:1px solid rgba(255,255,255,.08)}
.title{display:flex; align-items:center; gap:10px; font-size:20px; font-weight:700}
.badge{font-size:12px; padding:4px 10px; border-radius:999px; background:rgba(34,211,238,.18); color:#a5f3fc; border:1px solid rgba(34,211,238,.35)}
.body{padding:22px}

/* دو ستون؛ در موبایل یک ستون */
.grid{display:grid; grid-template-columns:1.2fr .8fr; gap:24px}
@media (max-width:880px){
  body{align-items:flex-start; padding:16px}
  .grid{grid-template-columns:1fr; gap:18px}
}

/* فرم و فیلدها */
.form{display:flex; flex-direction:column; gap:var(--sp)}
.row{display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:var(--sp)}
@media (max-width:560px){ .row{grid-template-columns:1fr} }
.field{display:flex; flex-direction:column; gap:6px}
label{font-size:13px; opacity:.9}
input[type=text], input[type=password], input[type=url] {
  width:100%; height:44px; padding:0 14px; border-radius:12px; border:1px solid rgba(255,255,255,.12);
  background:rgba(255,255,255,.06); color:var(--txt); outline:none; font-size:var(--fs); font-weight:500;
  transition:box-shadow .2s, border-color .2s;
}
/* حفظ رنگ پس‌زمینه برای فیلدهای پرشده */
input[type=text]:not(:placeholder-shown),
input[type=password]:not(:placeholder-shown),
input[type=url]:not(:placeholder-shown) {
  background: rgba(255,255,255,.06);
}
input:focus{ box-shadow:0 0 0 3px rgba(56,189,248,.25); border-color:rgba(56,189,248,.55) }
.ltr{ direction:ltr; text-align:left; }
.ltr::placeholder{
  font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto; opacity:.7;
}

/* چشم رمز */
.input-affix{ position:relative }
.input-affix input{ padding-left:88px; padding-right:14px; }
.affix-btn{
  position:absolute; left:6px; top:6px; height:32px;
  display:inline-flex; align-items:center; gap:6px;
  padding:0 10px; border-radius:10px; border:1px solid rgba(255,255,255,.12);
  background:rgba(255,255,255,.06); color:var(--txt);
  font-size:12px; cursor:pointer; user-select:none;
  font-family:'Vazir',system-ui; font-weight:700;
}
.affix-btn svg{width:16px; height:16px; opacity:.9}

/* دکمه اصلی */
.btn{
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  width:100%; height:46px; border-radius:12px; font-size:15px; cursor:pointer; border:0;
  color:#05202a; background:linear-gradient(135deg, var(--acc), var(--acc2));
  font-family:'Vazir',system-ui; font-weight:700;
  box-shadow:0 10px 24px rgba(34,211,238,.25); transition:transform .05s;
}
.btn:active{ transform:translateY(1px) }
.btn[disabled]{ opacity:.6; cursor:not-allowed }
.loader{ width:16px; height:16px; border-radius:999px; border:2px solid rgba(255,255,255,.6); border-left-color:transparent; animation:spin .7s linear infinite }
@keyframes spin{to{transform:rotate(360deg)}}

/* ستون راست: وضعیت و راهنما */
.checks{display:grid; gap:10px}
.check{display:flex; align-items:center; justify-content:space-between; padding:10px 12px; border-radius:10px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08)}
.pill{padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid}
.ok{background:rgba(16,185,129,.15); color:#bbf7d0; border-color:rgba(16,185,129,.35)}
.bad{background:rgba(239,68,68,.12); color:#fecaca; border-color:rgba(239,68,68,.35)}
.status{padding:14px; border-radius:12px; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); margin-top:var(--sp)}
.steps-title{font-weight:700; font-size:13px; margin:0 0 8px}
.steps{margin:0; padding:0 18px; line-height:1.8; font-size:13px}
.steps li{margin:4px 0}
.center{text-align:center; margin-top:14px}
.tiny{font-size:12px; padding:8px 10px; border-radius:10px; background:rgba(255,255,255,.06); color:var(--txt); border:1px solid rgba(255,255,255,.1); text-decoration:none; margin-left:8px}
.tiny:hover{ background:rgba(255,255,255,.1) }
.foot{padding:14px 22px; border-top:1px solid rgba(255,255,255,.08); display:flex; justify-content:space-between; gap:10px; font-size:12px; opacity:.85}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">
      <div class="title">نصب‌کنندهٔ ربات میرزا</div>
      <span class="badge">نسخهٔ آسان</span>
    </div>

    <div class="body">
      <?php if ($action === 'none'): ?>
        <div class="grid">
          <!-- فرم -->
          <form id="install-form" class="form" method="post" action="install.php">
            <div class="field">
              <label>توکن ربات تلگرام</label>
              <input class="ltr" type="text" name="token" required placeholder="...ABCDEF:123456789">
            </div>

            <div class="field">
              <label>شناسه عددی ادمین</label>
              <input class="ltr" type="text" name="admin_id" required placeholder="مثال: 123456789">
            </div>

            <div class="field">
              <label>نام کاربری بات (بدون @)</label>
              <input class="ltr" type="text" name="bot_username" required placeholder="vpnrobot">
            </div>

            <div class="row">
              <div class="field">
                <label>نام دیتابیس</label>
                <input class="ltr" type="text" name="db_name" required placeholder="mirza_db">
              </div>
              <div class="field">
                <label>یوزرنیم دیتابیس</label>
                <input class="ltr" type="text" name="db_user" required placeholder="db_user">
              </div>
            </div>

            <div class="field">
              <label>رمز عبور دیتابیس</label>
              <div class="input-affix">
                <input type="password" id="dbpass" name="db_pass" required placeholder="••••••••">
                <button type="button" class="affix-btn" id="togglePass" aria-label="نمایش/مخفی رمز">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3.5" stroke="currentColor" stroke-width="1.5"/></svg>
                  <span>نمایش</span>
                </button>
              </div>
            </div>

            <div class="field">
              <label>آدرس دامنه/پوشهٔ ربات (HTTPS)</label>
              <input class="ltr" type="url" name="site_url" required placeholder="https://example.com" pattern="https://.*">
              <div class="hint" style="font-size:11px; opacity:.8; margin-top:4px">آدرس کامل سایت را وارد کنید (با https). اگر فایل‌های ربات را داخل پوشه‌ای قرار داده‌اید، نام پوشه را هم در انتهای آدرس اضافه کنید؛ در غیر این صورت فقط دامنه کافیست.</div>
            </div>

            <button class="btn" id="submitBtn" type="submit">
              <span class="btn-text">نصب و راه‌اندازی</span>
            </button>
            <div class="hint" style="font-size:11px; opacity:.8; margin-top:4px">پس از نصب، وب‌هوک با استفاده از API تلگرام ثبت می‌شود.</div>
          </form>

          <!-- ستون راست: چک‌ها و راهنمای مرحله‌ای -->
          <div>
            <div class="field">
              <label>پیش‌نیازهای محیط</label>
              <div class="checks">
                <?php foreach ($checks as $k=>$ok): ?>
                  <div class="check">
                    <small><?php echo htmlspecialchars($k,ENT_QUOTES); ?></small>
                    <span class="pill <?php echo $ok ? 'ok' : 'bad'; ?>"><?php echo $ok ? 'اوکی' : 'نیاز به بررسی'; ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="status">
              <div class="steps-title">🔎 راهنمای نصب</div>
              <ol class="steps">
                <li>یک پایگاه دادهٔ خالی بسازید.</li>
                <li>فایل‌های پروژه را روی هاست آپلود کنید.</li>
                <li>این فرم را با اطلاعات صحیح پُر کنید.</li>
                <li>پس از نصب، از همین صفحه جدول‌های پایگاه داده را ایجاد کنید.</li>
              </ol>
            </div>

            <div class="center">
              <a class="tiny" href="?action=installed">بررسی نصب</a>
              <a class="tiny" href="./">بازنشانی صفحه</a>
            </div>
          </div>
        </div>

      <?php elseif ($action === 'ok'): ?>
        <?php
          //
          $domainHostCron = '';
          if (is_file($rootDir . '/config.php')) {
              // بارگذاری متغیر $domainhosts از config.php.
              // با استفاده از @ جهت جلوگیری از خطاهای احتمالی اتصال به پایگاه داده در هنگام include.
              @include $rootDir . '/config.php';
              if (isset($domainhosts)) {
                  $domainHostCron = rtrim($domainhosts, '/');
              }
          }
          // تعریف کرون‌جاب‌ها؛ از curl برای فراخوانی اسکریپت‌های پوشه cronbot استفاده می‌کنیم
          $cronJobs = [];
          if ($domainHostCron !== '') {
              // تعریف فرمان‌های کرون‌جاب همراه با توصیف بازهٔ اجرا
              $cronJobs = [
                  ['*/1 * * * *',   "curl https://{$domainHostCron}/cronbot/croncard.php",        'هر 1 دقیقه'],
                  ['*/1 * * * *',   "curl https://{$domainHostCron}/cronbot/NoticationsService.php", 'هر 1 دقیقه'],
                  ['*/1 * * * *',   "curl https://{$domainHostCron}/cronbot/sendmessage.php",       'هر 1 دقیقه'],
                  ['*/1 * * * *',   "curl https://{$domainHostCron}/cronbot/activeconfig.php",      'هر 1 دقیقه'],
                  ['*/1 * * * *',   "curl https://{$domainHostCron}/cronbot/disableconfig.php",     'هر 1 دقیقه'],
                  ['*/1 * * * *',   "curl https://{$domainHostCron}/cronbot/iranpay1.php",         'هر 1 دقیقه'],
                  ['*/2 * * * *',   "curl https://{$domainHostCron}/cronbot/configtest.php",       'هر 2 دقیقه'],
                  ['*/2 * * * *',   "curl https://{$domainHostCron}/cronbot/gift.php",             'هر 2 دقیقه'],
                  ['*/3 * * * *',   "curl https://{$domainHostCron}/cronbot/plisio.php",           'هر 3 دقیقه'],
                  ['*/5 * * * *',   "curl https://{$domainHostCron}/cronbot/payment_expire.php",   'هر 5 دقیقه'],
                  ['*/15 * * * *',  "curl https://{$domainHostCron}/cronbot/statusday.php",        'هر 15 دقیقه'],
                  ['*/15 * * * *',  "curl https://{$domainHostCron}/cronbot/on_hold.php",          'هر 15 دقیقه'],
                  ['*/15 * * * *',  "curl https://{$domainHostCron}/cronbot/uptime_node.php",      'هر 15 دقیقه'],
                  ['*/15 * * * *',  "curl https://{$domainHostCron}/cronbot/uptime_panel.php",     'هر 15 دقیقه'],
                  ['*/30 * * * *',  "curl https://{$domainHostCron}/cronbot/expireagent.php",      'هر 30 دقیقه'],
                  ['0 */5 * * *',   "curl https://{$domainHostCron}/cronbot/backupbot.php",        'هر 5 ساعت'],
                  ['0 0 * * *',     "curl https://{$domainHostCron}/cronbot/lottery.php",          'روزانه'],
              ];
          }
        ?>
        <div class="status" style="border-color:rgba(16,185,129,.35); background:rgba(16,185,129,.12)">
          <div>🎉 نصب موفق بود. فایل پیکربندی ذخیره شد و وب‌هوک ثبت گردید.</div>
        </div>
        <div class="center" style="margin-top:20px">
          <p style="font-size:13px; color:#fef08a">جداول پایگاه داده (در صورت موجود بودن فایل <code>table.php</code>) هنگام نصب به‌طور خودکار ایجاد شدند.</p>
        </div>
        <?php if (!empty($cronJobs)): ?>
        <div class="status" style="margin-top:24px; border-color:rgba(139,92,246,.35); background:rgba(139,92,246,.12)">
          <div style="margin-bottom:8px;font-weight:700">🕒 تنظیم کرون‌جاب‌ها</div>
          <p style="font-size:13px; margin:4px 0">برای عملکرد صحیح ربات لازم است اسکریپت‌های موجود در پوشه <code>cronbot</code> به‌صورت زمان‌بندی اجرا شوند. در پنل هاست خود برای هر فرمان از جدول زیر یک کرون‌جاب ایجاد کنید.</p>
          <table style="width:100%; border-collapse:collapse; font-size:12px; direction:ltr">
            <thead>
              <tr>
                <th style="text-align:left; padding:6px; border-bottom:1px solid rgba(255,255,255,.12)">فرمان کرون</th>
                <th style="text-align:left; padding:6px; border-bottom:1px solid rgba(255,255,255,.12)">بازهٔ اجرا</th>
                <th style="text-align:left; padding:6px; border-bottom:1px solid rgba(255,255,255,.12)">کپی دستور</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($cronJobs as $cj): ?>
              <tr>
                <!-- فقط فرمان کرون (بدون زمان‌بندی) -->
                <td style="padding:4px 6px; white-space:nowrap"><?php echo htmlspecialchars($cj[1], ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="padding:4px 6px; white-space:nowrap"><?php echo htmlspecialchars($cj[2], ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="padding:4px 6px; white-space:nowrap">
                  <button type="button" class="copy-btn" data-cmd="<?php echo htmlspecialchars($cj[1], ENT_QUOTES, 'UTF-8'); ?>">کپی</button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <p style="font-size:12px; margin-top:8px; line-height:1.6">⚠️ برای حفظ امنیت و پایداری سرور از اجرای دستورات حساس مانند <code>shutdown</code>، <code>init 0</code>، <code>mkfs</code>، <code>passwd</code>، <code>chpasswd</code>، <code>stdin</code>، <code>mkfs.ext</code> و <code>mke2fs</code> در اسکریپت‌های شل اجتناب کنید.</p>
        </div>
        <?php endif; ?>
        <div class="center"><a class="tiny" href="./">بازگشت</a></div>

        <!-- استایل و اسکریپت برای دکمه‌های کپی -->
        <style>
        .copy-btn {
          padding:4px 8px;
          border-radius:8px;
          border:1px solid rgba(255,255,255,.12);
          background:rgba(255,255,255,.06);
          color:var(--txt);
          font-size:12px;
          cursor:pointer;
          font-family:'Vazir',system-ui;
        }
        .copy-btn:hover {
          background:rgba(255,255,255,.1);
        }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.copy-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var cmd = this.getAttribute('data-cmd');
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(cmd).then(() => {
                            var original = this.textContent;
                            this.textContent = 'کپی شد';
                            setTimeout(() => { this.textContent = original; }, 1500);
                        }).catch(() => {
                            // fallback: copy to a temporary textarea
                            var temp = document.createElement('textarea');
                            temp.value = cmd;
                            document.body.appendChild(temp);
                            temp.select();
                            document.execCommand('copy');
                            document.body.removeChild(temp);
                            var original = this.textContent;
                            this.textContent = 'کپی شد';
                            setTimeout(() => { this.textContent = original; }, 1500);
                        });
                    } else {
                        // fallback for older browsers
                        var temp = document.createElement('textarea');
                        temp.value = cmd;
                        document.body.appendChild(temp);
                        temp.select();
                        document.execCommand('copy');
                        document.body.removeChild(temp);
                        var original = this.textContent;
                        this.textContent = 'کپی شد';
                        setTimeout(() => { this.textContent = original; }, 1500);
                    }
                });
            });
        });
        </script>

      <?php elseif ($action === 'nok'): ?>
        <div class="status" style="border-color:rgba(239,68,68,.35); background:rgba(239,68,68,.12)">
          <div>⚠️ خطا در نصب یا ثبت وب‌هوک. لطفاً اطلاعات را بررسی کرده و دوباره تلاش کنید.</div>
        </div>
        <div class="center"><a class="tiny" href="./">تلاش مجدد</a></div>

      <?php elseif ($action === 'installed'): ?>
        <?php $isInstalled = file_exists($flagFile); ?>
        <div class="status" style="border-color:<?php echo $isInstalled ? 'rgba(245,158,11,.35)' : 'rgba(239,68,68,.35)'; ?>; background:<?php echo $isInstalled ? 'rgba(245,158,11,.12)' : 'rgba(239,68,68,.12)'; ?>">
          <div>
            <?php if ($isInstalled): ?>
              ℹ️ این ربات قبلاً نصب شده است و امکان نصب مجدد وجود ندارد.
            <?php else: ?>
              ⚠️ فایل نصب‌شده یافت نشد. به نظر می‌رسد هنوز نصب نشده است.
            <?php endif; ?>
          </div>
        </div>
        <div class="center"><a class="tiny" href="./">بازگشت</a></div>

      <?php elseif ($action === 'createdb'): ?>
        <div class="status" style="border-color:rgba(34,211,238,.35); background:rgba(34,211,238,.12)">
          <div><?php echo $dbStatusMsg ?? 'اجرای عملیات پایگاه داده.'; ?></div>
        </div>
        <div class="center" style="margin-top:20px"><a class="tiny" href="./">بازگشت</a></div>
      <?php endif; ?>
    </div>

    <div class="foot">
      <div>⚙️ نصب‌کنندهٔ میرزا</div>
      <div>ساخته شده با ❤️</div>
    </div>
  </div>
</div>

<script>
// اسکریپت کوچک برای دکمهٔ نمایش رمز و غیرفعال کردن دکمه هنگام ارسال
(function(){
  const form = document.getElementById('install-form');
  const btn  = document.getElementById('submitBtn');
  const pass = document.getElementById('dbpass');
  const tog  = document.getElementById('togglePass');
  if (tog && pass) {
    tog.addEventListener('click', function(){
      const t = pass.getAttribute('type') === 'password' ? 'text' : 'password';
      pass.setAttribute('type', t);
      const span = tog.querySelector('span');
      if (span) span.textContent = (t === 'password') ? 'نمایش' : 'مخفی';
    });
  }
  if (form && btn) {
    form.addEventListener('submit', function(){
      btn.setAttribute('disabled','disabled');
      const s = btn.querySelector('.btn-text');
      if (s) s.innerHTML = '<span class="loader"></span> در حال نصب...';
    });
  }
})();
</script>
</body>
</html>