// head-config.js
const headContent = `
   <meta charset="utf-8" />
<meta content="width=device-width, initial-scale=1.0" name="viewport" />
<title>جمعية أصدقاء جامعة بيرزيت - Friends of Birzeit University Association</title>
<link href="https://fonts.googleapis.com" rel="preconnect" />
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&amp;display=swap"
    rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
    rel="stylesheet" />
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<link rel="stylesheet" href="style.css">


<script src="script.js" defer></script>

<script src="navbar.js" defer></script>
<script src="footer.js" defer></script>
`;

// حقن المحتوى في الـ head قبل أي شيء آخر
document.head.insertAdjacentHTML('beforeend', headContent);