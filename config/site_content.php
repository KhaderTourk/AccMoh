<?php

/**
 * تجميع النصوص القابلة للتحرير من لوحة التحكم.
 * كل مجموعة تمثل قسماً أو صفحة، وتحتوي على المفاتيح (من ملفات الترجمة) مع وصف للمسؤول.
 */
return [
    'home' => [
        'label_ar' => 'الصفحة الرئيسية',
        'label_en' => 'Home Page',
        'items' => [
            'ui.hero_default_title' => ['label_ar' => 'عنوان البطل', 'label_en' => 'Hero title'],
            'ui.hero_default_subtitle' => ['label_ar' => 'نص البطل التعريفي', 'label_en' => 'Hero subtitle'],
            'ui.cta_default' => ['label_ar' => 'نص زر الإجراء الافتراضي', 'label_en' => 'Default CTA text'],
        ],
    ],
    'nav' => [
        'label_ar' => 'القائمة والتنقل',
        'label_en' => 'Navigation',
        'items' => [
            'ui.nav_home' => ['label_ar' => 'الرئيسية', 'label_en' => 'Home'],
            'ui.nav_lang_switch' => ['label_ar' => 'مفتاح تبديل اللغة', 'label_en' => 'Language switch label'],
            'ui.menu' => ['label_ar' => 'القائمة', 'label_en' => 'Menu'],
        ],
    ],
    'footer' => [
        'label_ar' => 'التذييل',
        'label_en' => 'Footer',
        'items' => [
            'ui.footer_desc' => ['label_ar' => 'وصف التذييل', 'label_en' => 'Footer description'],
            'ui.footer_links_heading' => ['label_ar' => 'عنوان الروابط', 'label_en' => 'Links heading'],
            'ui.follow_us' => ['label_ar' => 'تابعنا', 'label_en' => 'Follow us'],
            'ui.rights_reserved' => ['label_ar' => 'حقوق النشر', 'label_en' => 'Copyright'],
            'ui.privacy_policy' => ['label_ar' => 'سياسة الخصوصية', 'label_en' => 'Privacy policy'],
            'ui.terms_of_use' => ['label_ar' => 'شروط الاستخدام', 'label_en' => 'Terms of use'],
            'ui.contact_us' => ['label_ar' => 'تواصل معنا', 'label_en' => 'Contact us'],
        ],
    ],
    'general' => [
        'label_ar' => 'عام',
        'label_en' => 'General',
        'items' => [
            'ui.site_name' => ['label_ar' => 'اسم الموقع', 'label_en' => 'Site name'],
            'ui.link_copied' => ['label_ar' => 'تم نسخ الرابط', 'label_en' => 'Link copied'],
            'ui.copy_link' => ['label_ar' => 'نسخ الرابط', 'label_en' => 'Copy link'],
        ],
    ],
];
