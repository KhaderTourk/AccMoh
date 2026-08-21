# إعداد البريد الإلكتروني

## المشكلة الحالية

حالياً، الإعداد الافتراضي للبريد الإلكتروني هو `log`، مما يعني أن الإيميلات تُسجل في ملف log فقط ولا تُرسل فعلياً.

## الحل: إعداد SMTP

### الطريقة 1: استخدام Gmail (موصى به للاختبار)

1. افتح ملف `.env` في جذر المشروع
2. أضف أو عدّل الإعدادات التالية:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your-email@gmail.com"
MAIL_FROM_NAME="جمعية أصدقاء جامعة بيرزيت"
```

**ملاحظة مهمة:** لاستخدام Gmail، تحتاج إلى:
- تفعيل "App Password" من إعدادات حساب Google
- الذهاب إلى: Google Account → Security → 2-Step Verification → App passwords

### الطريقة 2: استخدام Mailtrap (للاختبار والتطوير)

1. سجل في [Mailtrap.io](https://mailtrap.io)
2. احصل على بيانات SMTP من حسابك
3. أضف في `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@bzufa.ps"
MAIL_FROM_NAME="جمعية أصدقاء جامعة بيرزيت"
```

### الطريقة 3: استخدام SMTP الخاص بالخادم

إذا كان لديك خادم بريد إلكتروني خاص:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="جمعية أصدقاء جامعة بيرزيت"
```

## التحقق من الإعدادات

بعد تحديث `.env`:

1. امسح الكاش:
```bash
php artisan config:clear
php artisan cache:clear
```

2. اختبر الإرسال من خلال:
   - إرسال طلب تطوع من الموقع
   - إرسال طلب شراكة تمكين

3. تحقق من ملفات log في `storage/logs/laravel.log` إذا كان هناك أي أخطاء

## استقبال الإيميلات

الإيميلات تُرسل إلى:
- البريد المحدد في لوحة التحكم (`/cp/site-settings`) في حقل "البريد الإلكتروني"
- أو `eltrukk@gmail.com` كبريد افتراضي إذا لم يتم تحديد بريد

## استكشاف الأخطاء

### الإيميلات لا تصل

1. تحقق من أن `MAIL_MAILER=smtp` وليس `log`
2. تحقق من صحة بيانات SMTP
3. تحقق من ملف `storage/logs/laravel.log` للأخطاء
4. تأكد من أن البريد لا يذهب إلى مجلد Spam

### خطأ "Connection refused"

- تحقق من أن `MAIL_HOST` و `MAIL_PORT` صحيحة
- تأكد من أن الخادم يسمح بالاتصالات الخارجية

### خطأ "Authentication failed"

- تحقق من `MAIL_USERNAME` و `MAIL_PASSWORD`
- في Gmail، تأكد من استخدام App Password وليس كلمة المرور العادية
