# دليل رفع AccMa على Hostinger

## متطلبات الاستضافة
- PHP **8.2** أو أحدث
- MySQL / MariaDB
- امتدادات PHP: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `gd`, `zip`
- SSH (موصى به جداً) أو على الأقل File Manager + Terminal من hPanel
- تفعيل SSL (HTTPS)

---

## أ) تجهيز المشروع على جهازك قبل الرفع

1. تأكد أن الكود محدّث ويعمل محلياً.
2. لا ترفع ملف `.env` المحلي كما هو.
3. ارفع المشروع **بدون**:
   - `.env`
   - `node_modules` (إن وجد)
   - ملفات كاش غير لازمة
4. الأفضل رفعه كـ ZIP من جذر المشروع مع `vendor` **أو** رفعه بدون `vendor` وتشغيل `composer install` عبر SSH.

> إذا استضافتك تدعم SSH وComposer: ارفع بدون `vendor` ثم ثبّته على السيرفر (أنظف).  
> إذا لا يوجد Composer على السيرفر: ارفع مع مجلد `vendor` بعد تشغيل `composer install --optimize-autoloader --no-dev` محلياً.

أمر محلي موصى به قبل الرفع (إن سترفع vendor):

```bash
composer install --optimize-autoloader --no-dev
```

---

## ب) إنشاء قاعدة البيانات في Hostinger

من **hPanel → Databases → MySQL Databases**:

1. أنشئ Database
2. أنشئ User
3. اربط المستخدم بقاعدة البيانات بصلاحيات كاملة
4. احفظ:
   - DB name
   - DB user
   - DB password
   - Host (غالباً `localhost`)

---

## ج) رفع الملفات (الطريقة الموصى بها)

### الهيكل الصحيح
اجعل جذر الموقع يشير إلى مجلد `public` داخل Laravel.

مثال:

```text
/home/USER/domains/your-domain.com/laravel/     ← كل مشروع AccMa هنا
/home/USER/domains/your-domain.com/public_html/ ← يشير إلى laravel/public
```

### خيار 1 (الأفضل): تغيير Document Root
في Hostinger (حسب نوع الخطة):
1. ارفع المشروع كاملاً إلى مجلد مثل `laravel`
2. من إعدادات الدومين اجعل Document Root = `.../laravel/public`

### خيار 2: محتوى public داخل public_html
1. ارفع المشروع إلى مجلد خارج `public_html` باسم `accma` مثلاً
2. انسخ محتويات `accma/public/*` إلى `public_html/`
3. عدّل `public_html/index.php` ليصبح المساران صحيحين:

```php
require __DIR__.'/../accma/vendor/autoload.php';
$app = require_once __DIR__.'/../accma/bootstrap/app.php';
```

(عدّل `../accma` حسب اسم المجلد الفعلي)

---

## د) إعداد ملف `.env` على السيرفر

1. انسخ `.env.example` إلى `.env` على السيرفر
2. عدّل القيم التالية على الأقل:

```env
APP_NAME=AccMa
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=sync
CACHE_STORE=file

SANCTUM_STATEFUL_DOMAINS=your-domain.com,www.your-domain.com
```

3. ولّد المفتاح:

```bash
php artisan key:generate
```

---

## هـ) أوامر التشغيل بعد الرفع (SSH)

من جذر مشروع Laravel:

```bash
composer install --optimize-autoloader --no-dev
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### صلاحيات المجلدات (مهم)
اجعل هذه قابلة للكتابة:

```bash
chmod -R 775 storage bootstrap/cache
```

من File Manager يمكن ضبط Permissions لـ `storage` و `bootstrap/cache` إلى `775`.

---

## و) الدخول للنظام

- الرابط: `https://your-domain.com/cp/login`
- الافتراضي بعد الـ seed:
  - البريد: `admin@example.com`
  - كلمة المرور: `password`

**غيّر كلمة المرور فوراً** من إدارة المستخدمين.

الجذر `/` يحوّل تلقائياً إلى `/cp`.

---

## ز) تفعيل وضع Offline للمتصفح بعد الرفع

1. ادخل وأنت متصل بالإنترنت
2. افتح `/cp/offline`
3. اضغط **تحديث الكاش**
4. بعدها يمكن الإدخال بدون نت ثم المزامنة

تأكد أن الموقع يعمل على **HTTPS** حتى يعمل Service Worker بشكل صحيح في أغلب المتصفحات.

---

## ح) فحص سريع بعد الرفع

- [ ] `https://your-domain.com/cp/login` يفتح
- [ ] تسجيل الدخول يعمل
- [ ] Dashboard تظهر الأرصدة بدون أخطاء
- [ ] إضافة عميل/دفعة تعمل
- [ ] `/cp/offline` تفتح وتحدّث الكاش
- [ ] `/api/v1/auth/login` يرجع token (للموبايل لاحقاً)
- [ ] `APP_DEBUG=false`
- [ ] SSL أخضر

---

## أخطاء شائعة وحلولها

### 500 Internal Server Error
- تحقق من `.env` و`APP_KEY`
- تحقق صلاحيات `storage` و`bootstrap/cache`
- شاهد `storage/logs/laravel.log`

### صفحة بيضاء
- PHP أقل من 8.2 → ارفعه من hPanel إلى 8.2/8.3
- مسار `index.php` خاطئ إن استخدمت خيار public_html

### جداول غير موجودة
```bash
php artisan migrate --force
php artisan db:seed --force
```

### CSS/JS لا تظهر
- تأكد أن Document Root هو مجلد `public`
- امسح كاش المتصفح

### Offline لا يعمل
- لازم HTTPS
- افتح `/cp/offline` وأنت Online مرة واحدة
- تأكد أن Service Worker مسجّل في أدوات المطوّر → Application

### خطأ CSRF عند المزامنة
- تأكد أنك مسجّل دخول في نفس النطاق
- لا تفتح الموقع من `http` و`https` بالتبادل

---

## توصية أمنية سريعة للإنتاج
1. غيّر بريد/كلمة مرور الأدمن
2. `APP_DEBUG=false`
3. فعّل SSL إجباري
4. خذ نسخة احتياطية يومية من MySQL من hPanel
5. لا تشارك `.env` مع أحد

---

## ملخص المسار الأقصر
1. أنشئ DB في Hostinger  
2. ارفع المشروع ووجّه الدومين إلى `public`  
3. أنشئ `.env` من المثال  
4. `composer install` + `key:generate` + `migrate --seed` + `storage:link` + cache  
5. ادخل `/cp` وغيّر كلمة المرور  
6. افتح `/cp/offline` وحدّث الكاش  
