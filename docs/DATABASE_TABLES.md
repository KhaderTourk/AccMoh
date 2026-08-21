# مقترح جداول قاعدة البيانات — جمعية أصدقاء جامعة بيرزيت

هذا المستند يعرض شكل الجداول المقترحة للمراجعة قبل الاعتماد أو إنشاء الـ migrations.  
كل جدول مرفق بوصف مختصر وحقوله مع نوع البيانات والغرض.

---

## 1. إعدادات الموقع والصفحة الرئيسية

### 1.1 `site_settings` — إعدادات الموقع العامة

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| logo_path | string, nullable | مسار شعار الموقع (الوضع الفاتح) |
| logo_dark_path | string, nullable | مسار الشعار للوضع الداكن |
| action_color | string, nullable | لون الإجراءات (مثل #hex) |
| donation_url | string, nullable | رابط صفحة التبرع |
| contact_email | string, nullable | البريد الإلكتروني |
| contact_phone | string, nullable | رقم الهاتف |
| address_ar | text, nullable | العنوان (عربي) |
| address_en | text, nullable | العنوان (إنجليزي) |
| facebook_url | string, nullable | رابط فيسبوك |
| twitter_url | string, nullable | رابط تويتر |
| instagram_url | string, nullable | رابط انستغرام |
| linkedin_url | string, nullable | رابط لينكدإن |
| default_locale | string, default 'ar' | اللغة الافتراضية (ar/en) |
| created_at | timestamp | |
| updated_at | timestamp | |

**ملاحظة:** يُفترض سجل واحد فقط (Singleton). يمكن استخدام أول سجل أو حقل `key` إذا تحوّل الجدول إلى key-value لاحقاً.

---

### 1.2 `home_settings` — إعدادات الصفحة الرئيسية (Hero + CTA)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| hero_type | enum('image','video') | نوع الوسائط في Hero |
| hero_media_path | string, nullable | مسار الصورة أو الفيديو |
| hero_title_ar | string, nullable | عنوان Hero (عربي) |
| hero_title_en | string, nullable | عنوان Hero (إنجليزي) |
| hero_subtitle_ar | text, nullable | النص التعريفي القصير (عربي) |
| hero_subtitle_en | text, nullable | النص التعريفي القصير (إنجليزي) |
| cta_text_ar | string, nullable | نص زر الإجراء (عربي) |
| cta_text_en | string, nullable | نص زر الإجراء (إنجليزي) |
| cta_url | string, nullable | رابط زر الإجراء |
| created_at | timestamp | |
| updated_at | timestamp | |

**ملاحظة:** سجل واحد فقط للصفحة الرئيسية.

---

### 1.3 `home_statistics` — إحصائيات إنفوجرافيك (الرئيسية)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| value | integer | الرقم (عدد المستفيدين، المشاريع، إلخ) |
| label_ar | string | وصف الرقم (عربي) |
| label_en | string, nullable | وصف الرقم (إنجليزي) |
| icon | string, nullable | اسم أيقونة أو مسار صورة اختياري |
| sort_order | integer, default 0 | ترتيب العرض |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 2. من نحن والفريق

### 2.1 `about_page` — صفحة التعريف (فيديو قصة الجمعية)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| story_video_url | string, nullable | رابط فيديو "قصة الجمعية" |
| created_at | timestamp | |
| updated_at | timestamp | |

**ملاحظة:** سجل واحد. نصوص الرؤية/الرسالة/الأهداف/القيم في الجدول التالي.

---

### 2.2 `about_sections` — أقسام من نحن (الرؤية، الرسالة، الأهداف، القيم)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| type | enum('vision','mission','goals','values') | نوع القسم |
| title_ar | string | عنوان القسم (عربي) |
| title_en | string, nullable | عنوان القسم (إنجليزي) |
| content_ar | text | المحتوى (عربي) |
| content_en | text, nullable | المحتوى (إنجليزي) |
| sort_order | integer, default 0 | ترتيب العرض |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 2.3 `team_members` — أعضاء الفريق (مجلس إدارة + تنفيذي + فريق عمل)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| name_ar | string | الاسم (عربي) |
| name_en | string, nullable | الاسم (إنجليزي) |
| title_ar | string | المسمى الوظيفي (عربي) |
| title_en | string, nullable | المسمى الوظيفي (إنجليزي) |
| photo_path | string, nullable | مسار الصورة (فارغ لفريق العمل إذا بدون صور) |
| type | enum('board','executive','staff') | مجلس إدارة / تنفيذي / فريق عمل |
| sort_order | integer, default 0 | ترتيب العرض |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 3. المشاريع

### 3.1 `kanani_settings` — إعدادات مشروع كنعاني

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| intro_video_url | string, nullable | رابط الفيديو التعريفي |
| intro_text_ar | text, nullable | نبذة عن المشروع (عربي) |
| intro_text_en | text, nullable | نبذة عن المشروع (إنجليزي) |
| store_url | string, nullable | رابط "زيارة المتجر" |
| created_at | timestamp | |
| updated_at | timestamp | |

**ملاحظة:** سجل واحد.

---

### 3.2 `kanani_gallery_items` — معرض منتجات كنعاني

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| image_path | string | مسار الصورة |
| title_ar | string | عنوان العنصر (عربي) |
| title_en | string, nullable | عنوان العنصر (إنجليزي) |
| description_ar | text, nullable | الوصف (عربي) |
| description_en | text, nullable | الوصف (إنجليزي) |
| sort_order | integer, default 0 | ترتيب العرض |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 3.3 `tamkeen_partnerships` — شراكات تمكين (بطاقات الشراكة)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| supporter_name_ar | string | اسم الجهة الداعمة (عربي) |
| supporter_name_en | string, nullable | اسم الجهة الداعمة (إنجليزي) |
| start_date | date, nullable | تاريخ البدء |
| beneficiaries_count | integer, nullable | عدد الطلبة المستفيدين |
| logo_path | string, nullable | شعار الجهة (اختياري) |
| link | string, nullable | رابط (اختياري) |
| sort_order | integer, default 0 | ترتيب العرض |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 3.4 `parasols_settings` — إعدادات مشروع المظلات (النص الوصفي)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| description_ar | text, nullable | وصف فكرة مساحات الإعلانات (عربي) |
| description_en | text, nullable | وصف فكرة مساحات الإعلانات (إنجليزي) |
| created_at | timestamp | |
| updated_at | timestamp | |

**ملاحظة:** سجل واحد.

---

### 3.5 `parasols_regions` — مناطق المظلات (جامعة بيرزيت، بلدة بيرزيت، رام الله)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| name_ar | string | اسم المنطقة (عربي) |
| name_en | string, nullable | اسم المنطقة (إنجليزي) |
| sort_order | integer, default 0 | ترتيب العرض |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 3.6 `parasols_images` — صور المظلات حسب المنطقة

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| region_id | foreignId → parasols_regions.id | المنطقة |
| image_path | string | مسار الصورة |
| title_ar | string, nullable | عنوان الصورة (عربي) |
| title_en | string, nullable | عنوان الصورة (إنجليزي) |
| sort_order | integer, default 0 | ترتيب العرض |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 4. المنح الجامعية

### 4.1 `scholarships` — المنح المتاحة

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| title_ar | string | عنوان المنحة (عربي) |
| title_en | string, nullable | عنوان المنحة (إنجليزي) |
| slug_ar | string, nullable | slug للرابط (عربي) |
| slug_en | string, nullable | slug للرابط (إنجليزي) |
| summary_ar | text, nullable | ملخص للمطالعة في القائمة (عربي) |
| summary_en | text, nullable | ملخص (إنجليزي) |
| image_path | string, nullable | صورة تعبيرية |
| application_start_date | date, nullable | بداية فترة التقديم |
| application_end_date | date, nullable | نهاية فترة التقديم |
| details_ar | text, nullable | التفاصيل الكاملة (عربي) |
| details_en | text, nullable | التفاصيل الكاملة (إنجليزي) |
| conditions_ar | text, nullable | الشروط (عربي) |
| conditions_en | text, nullable | الشروط (إنجليزي) |
| required_documents_ar | text, nullable | الأوراق المطلوبة (عربي) |
| required_documents_en | text, nullable | الأوراق المطلوبة (إنجليزي) |
| application_form_pdf_path | string, nullable | مسار ملف PDF استمارة التقديم للتحميل |
| is_active | boolean, default true | ظاهرة في الموقع أم لا |
| sort_order | integer, default 0 | ترتيب العرض في القائمة |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 4.2 `scholarship_applications` — طلبات التقديم على المنح

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| scholarship_id | foreignId → scholarships.id | المنحة |
| applicant_name | string | اسم مقدم الطلب |
| applicant_id_number | string | رقم الهوية |
| file_path | string | مسار الملف المرفوع (PDF/Word) |
| status | enum('pending','under_review','approved','rejected'), default 'pending' | حالة الطلب |
| admin_notes | text, nullable | ملاحظات إدارية (داخلي) |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 5. الشركاء والأخبار وقصص النجاح

### 5.1 `partners` — شركاء النجاح (أفراد / شركات)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| name_ar | string | الاسم (عربي) |
| name_en | string, nullable | الاسم (إنجليزي) |
| logo_path | string, nullable | مسار الشعار |
| type | enum('individual','company') | فرد / شركة (مؤسسة) |
| link | string, nullable | رابط الموقع (اختياري) |
| sort_order | integer, default 0 | ترتيب العرض |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 5.2 `news` — الأخبار

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| title_ar | string | عنوان الخبر (عربي) |
| title_en | string, nullable | عنوان الخبر (إنجليزي) |
| slug_ar | string, nullable | slug للرابط (عربي) |
| slug_en | string, nullable | slug للرابط (إنجليزي) |
| summary_ar | text, nullable | ملخص / تفاصيل مختصرة (عربي) |
| summary_en | text, nullable | ملخص (إنجليزي) |
| content_ar | text, nullable | المحتوى الكامل (عربي) |
| content_en | text, nullable | المحتوى الكامل (إنجليزي) |
| image_path | string, nullable | صورة الخبر |
| video_url | string, nullable | فيديو (اختياري) |
| published_at | timestamp, nullable | تاريخ النشر (للعرض والتصفية) |
| is_published | boolean, default true | منشور أم مسودة |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 5.3 `success_stories` — قصص النجاح (الرئيسية)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| title_ar | string | عنوان القصة (عربي) |
| title_en | string, nullable | عنوان القصة (إنجليزي) |
| content_ar | text, nullable | النص (عربي) |
| content_en | text, nullable | النص (إنجليزي) |
| image_path | string, nullable | صورة |
| sort_order | integer, default 0 | ترتيب العرض |
| is_featured | boolean, default false | إبراز في الرئيسية |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 6. النماذج والطلبات

### 6.1 `empowerment_requests` — طلبات المساهمة في التمكين

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| name | string | الاسم |
| email | string | البريد الإلكتروني |
| phone | string, nullable | الهاتف |
| message | text, nullable | الرسالة |
| status | enum('pending','contacted','closed'), default 'pending' | حالة الطلب (اختياري) |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### 6.2 `membership_requests` — طلبات انضمام الهيئة العامة

| الحقل | النوع | الوصف |
|-------|--------|--------|
| id | bigInteger, PK | المعرّف |
| name | string | الاسم |
| email | string | البريد الإلكتروني |
| phone | string, nullable | الهاتف |
| file_path | string, nullable | مسار الملف المرفوع (نموذج مكتمل إن وُجد) |
| message | text, nullable | رسالة أو ملاحظات |
| status | enum('pending','contacted','approved','closed'), default 'pending' | حالة الطلب (اختياري) |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 7. الجداول الموجودة مسبقاً

| الجدول | الاستخدام |
|--------|-----------|
| users | حسابات إدارة الموقع (لوحة التحكم) |
| cache | كاش Laravel |
| jobs | قوائم انتظار المهام |

---

## ملخص عدد الجداول المقترحة (جديدة)

| المجموعة | الجداول |
|----------|---------|
| إعدادات والرئيسية | site_settings, home_settings, home_statistics |
| من نحن والفريق | about_page, about_sections, team_members |
| كنعاني | kanani_settings, kanani_gallery_items |
| تمكين | tamkeen_partnerships |
| المظلات | parasols_settings, parasols_regions, parasols_images |
| المنح | scholarships, scholarship_applications |
| محتوى عام | partners, news, success_stories |
| نماذج وطلبات | empowerment_requests, membership_requests |
| **المجموع** | **18 جدولاً جديداً** |

---

بعد المراجعة والموافقة يمكن تحويل هذا المقترح إلى migrations في Laravel. إذا رغبت بتعديل اسم جدول أو إضافة/حذف حقل، حدّد الجدول والحقل والتعديل المطلوب.
