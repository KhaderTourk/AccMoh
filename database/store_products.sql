-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 05, 2026 at 08:38 AM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bzufa`
--

-- --------------------------------------------------------

--
-- Table structure for table `store_products`
--

DROP TABLE IF EXISTS `store_products`;
CREATE TABLE IF NOT EXISTS `store_products` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `name_ar` varchar(191) NOT NULL,
  `name_en` varchar(191) DEFAULT NULL,
  `slug_ar` varchar(191) DEFAULT NULL,
  `slug_en` varchar(191) DEFAULT NULL,
  `description_ar` text,
  `description_en` text,
  `price` decimal(12,2) NOT NULL,
  `old_price` decimal(12,2) DEFAULT NULL,
  `discount_percent` tinyint UNSIGNED DEFAULT NULL,
  `image_path` varchar(191) DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `stock` int UNSIGNED NOT NULL DEFAULT '0',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_products_category_id_foreign` (`category_id`)
) ;

--
-- Dumping data for table `store_products`
--

INSERT INTO `store_products` (`id`, `category_id`, `name_ar`, `name_en`, `slug_ar`, `slug_en`, `description_ar`, `description_en`, `price`, `old_price`, `discount_percent`, `image_path`, `images`, `stock`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(5, 8, 'فاصل كتاب بتطريز يدوي - نقشة رقم 1', 'Hand-Embroidered Bookmark - Pattern No. 1', 'bookmark-pattern1', 'hand-embroidered-bookmark-pattern-no-1', 'اصل كتاب قماشي مشغول يدوياً بدقة فائقة، يتميز بنقشة تطريز فلسطينية فريدة (رقم 1) بألوان كلاسيكية. قطعة ناعمة وأنيقة تضفي لمسة تراثية على كتبك، ومثالية كهدية تذكارية راقية.', 'A finely handcrafted fabric bookmark featuring a unique Palestinian embroidery pattern (Pattern No. 1). This elegant piece adds a touch of heritage to your reading experience, making it a perfect personal accessory or a thoughtful gift.', 45.00, 45.00, 0, 'store/products/1DVSJFnVNQeteJOlzkm4fSFZLkXPPfyC6Tv3Q54R.jpg', '[\"store\\/products\\/1DVSJFnVNQeteJOlzkm4fSFZLkXPPfyC6Tv3Q54R.jpg\"]', 200, 0, 1, '2026-02-20 16:41:35', '2026-03-17 08:45:19'),
(6, 6, 'حزام حقيبة: تطريز \"سنابل\" يدوي', 'Wheat Stalks (Sanabel) Hand-Embroidered Bag Strap', 'wheat-stalks-bag-strap', 'wheat-stalks-sanabel-hand-embroidered-bag-strap', 'حزام حقيبة مشغول يدوياً بنقشة \"سنابل القمح\" الفلسطينية، التي ترمز للخير والعطاء. يجمع بين أصالة التراث واللمسة العصرية، ليضفي تميزاً وأناقة على حقيبتك المفضلة.', 'Hand-embroidered bag strap featuring the \"Wheat Stalks\" (Sanabel) pattern, a Palestinian symbol of prosperity and growth. .A beautiful blend of heritage and modern style, designed to add a unique touch to your favorite bag', 150.00, 150.00, 0, 'store/products/SgJ3ZJTnqjbukTBcqFgrkJo9xwItrY8CbXR8WMQH.jpg', '[\"store\\/products\\/SgJ3ZJTnqjbukTBcqFgrkJo9xwItrY8CbXR8WMQH.jpg\"]', 10, 0, 1, '2026-03-17 08:41:17', '2026-03-17 08:41:17'),
(7, 4, 'برواز شجرة الزيتون من الخرز و اسلاك النحاس (حجم كبير)', 'Olive Tree Frame Made of Beads and Copper Wires (Large Size)', 'large-olive-tree-frame', 'olive-tree-frame-made-of-beads-and-copper-wires-large-size', 'برواز شجرة زيتون مصنوع يدوياً من الخرز والأسلاك النحاسية، يجسّد جمال ورمزية شجرة الزيتون. قطعة فنية أنيقة مناسبة لتزيين المنزل أو كهدية مميزة.', 'A beautiful piece of art featuring a meticulously crafted olive tree made of beads and copper wire. This elegant frame embodies beauty and symbolism, making it a perfect decorative accent or a unique gift.', 240.00, 240.00, 0, 'store/products/ZtoU7SHJiHCCIi4y4OIatwbYdJpL6FvI8HcUrTo5.jpg', '[\"store\\/products\\/ZtoU7SHJiHCCIi4y4OIatwbYdJpL6FvI8HcUrTo5.jpg\"]', 100, 0, 1, '2026-03-17 08:44:51', '2026-03-17 08:44:51'),
(8, 4, 'برواز قبة ثوب فلسطيني صغيرة + طبق صغير منسوج من قش القمح', 'Mini Thobe chest Embroidery & Straw Plate', 'mini-thobe-chest-straw-plate', 'mini-thobe-chest-embroidery-straw-plate', 'برواز يضم جزء مطرز يدوياً من قبة الثوب مع طبق قش صغير، يجسّد دقة الحرف التقليدية وعراقة الهوية. قطعة فنية أنيقة مناسبة لتزيين المنزل أو كهدية مميزة.', 'A hand-embroidered miniature of a Thobe chest piece with a small woven straw plate, embodying traditional craftsmanship and cultural identity. An elegant art piece perfect for home decor or as a unique gift.', 180.00, 180.00, 0, 'store/products/vI3nioIDhq8S4ucNnr7cq81TdO22AMRhEKvcjNvB.jpg', '[\"store\\/products\\/vI3nioIDhq8S4ucNnr7cq81TdO22AMRhEKvcjNvB.jpg\"]', 39, 0, 1, '2026-03-17 08:48:15', '2026-03-17 08:48:15'),
(9, 3, 'حقيبة \"قبة الخليل\" – تطريز يدوي حجم حقيقي', 'Hebron Heritage Tote: Life-Sized Hand-Embroidered Qubbeh', 'hebron-tote-bag', 'hebron-heritage-tote-life-sized-hand-embroidered-qubbeh', 'حقيبة قماشية فاخرة تحمل عبق مدينة الخليل العريقة، مزينة بـ \"قبة ثوب خليلي\" كاملة بحجمها الحقيقي ومطرزة يدوياً بكل دقة. قطعة تجمع بين الأصالة الفلسطينية والعملية العصرية، لتكون رفيقتك الأنيقة في كل مكان.', 'stitched \"Hebronite Qubbeh,\" meticulously embroidered to reflect authentic Palestinian artistry. A perfect blend of cultural luxury and modern everyday use.', 200.00, 200.00, 0, 'store/products/Y2Bn2D6oBx23BQdf0T2ElbNmo08kz9vDQ8IowRRg.jpg', '[\"store\\/products\\/Y2Bn2D6oBx23BQdf0T2ElbNmo08kz9vDQ8IowRRg.jpg\"]', 5, 0, 1, '2026-03-17 09:01:21', '2026-03-17 09:01:21'),
(10, 4, 'برواز عصفور الشمس الفلسطيني - رقم 1', 'Palestine Sunbird Art Frame - No. 1', 'sunbird1-frame', 'palestine-sunbird-art-frame-no-1', 'لوحة فنية مشغولة يدوياً بتقنية \"التارة\" (الرسم بالخيوط)، تجسّد عصفور الشمس الفلسطيني بألوانه الزاهية وتفاصيله الدقيقة. قطعة تعكس جمال الطبيعة الفلسطينية بلمسة حرفية راقية.', 'A handcrafted masterpiece using the \"Needle Painting\" technique, capturing the vibrant beauty of the Palestine Sunbird. This elegant piece brings a touch of Palestinian nature and artistic craftsmanship to your space.', 220.00, 220.00, 0, 'store/products/SayfsD6aDyvE4CAyNZi0ZhKkNcLpX9ZlZhZGKe4V.jpg', '[\"store\\/products\\/SayfsD6aDyvE4CAyNZi0ZhKkNcLpX9ZlZhZGKe4V.jpg\"]', 20, 0, 1, '2026-03-17 09:24:05', '2026-03-17 09:24:05'),
(11, 5, 'صندوق خشبي بتفاصيل مطرزة يدوياً', 'Hand-Embroidered Wooden Keepsake Box', 'wooden-box-glass-top', 'hand-embroidered-wooden-keepsake-box', 'صندوق خشبي فاخر مصنوع من الخشب الطبيعي، مزين بحزام من التطريز الفلسطيني اليدوي بألوان زاهية ومنوعة. يتميز بغطاء زجاجي شفاف يسمح برؤية محتوياته، مما يجعله مثالياً لحفظ المجوهرات، المقتنيات الثمينة، أو كقطعة ديكور راقية تعكس الأصالة.', 'A premium wooden box crafted from natural wood and adorned with a hand-embroidered Palestinian pattern in vibrant colors. Featuring a clear glass lid, it is perfect for storing jewelry and keepsakes or as an elegant decor piece that showcases traditional craftsmanship.', 180.00, 180.00, 0, 'store/products/pKrSVCEEtd2Hgo8b30P4cIEFpZkPXvwfSoYpUS4R.jpg', '[\"store\\/products\\/pKrSVCEEtd2Hgo8b30P4cIEFpZkPXvwfSoYpUS4R.jpg\"]', 5, 0, 1, '2026-03-17 09:34:58', '2026-03-17 09:34:58'),
(12, 8, 'فاصل كتاب بتطريز يدوي - نقشة رقم 2', 'Hand-Embroidered Bookmark - Pattern No. 2', 'bookmark-pattern2', 'hand-embroidered-bookmark-pattern-no-2', 'فاصل كتاب قماشي مشغول يدوياً بعناية، يتميز بنقشة مطرزة غنية بالألوان (رقم2) تضفي بهجة على وقت قراءتك. قطعة فنية صغيرة تجمع بين دقة العمل اليدوي وتناسق الألوان الجذاب، مثالية للاقتناء الشخصي أو كهدية لطيفة.', 'A handcrafted fabric bookmark featuring a vibrant, multi-colored embroidery pattern (Pattern No. 2). This artistic piece combines meticulous hand-stitching with a cheerful color palette, making it a perfect companion for your books or a delightful gift.', 45.00, 45.00, 0, 'store/products/UnlilaCemtLihpXxnIkFV1nldrkXPFNMEstUEiV0.jpg', '[\"store\\/products\\/UnlilaCemtLihpXxnIkFV1nldrkXPFNMEstUEiV0.jpg\"]', 0, 0, 1, '2026-03-17 09:59:52', '2026-03-17 10:05:38'),
(13, 8, 'فاصل كتاب بتطريز يدوي - نقشة رقم 3', 'Hand-Embroidered Bookmark - Pattern No. 3', 'bookmark-pattern3', 'hand-embroidered-bookmark-pattern-no-3', 'فاصل كتاب قماشي مشغول يدوياً بدقة عالية، يتميز بنقشة هندسية زاهية (رقم 3) تجمع بين اللونين البنفسجي والبرتقالي. يزدان بقطعة معدنية (ليرة) تعزز من طابعه التراثي، ليكون رفيقاً أنيقاً لصفحات كتبك أو هدية مميزة للأصدقاء.', 'A finely handcrafted fabric bookmark featuring a vibrant geometric Palestinian pattern (Pattern No. 3) in purple and orange. Adorned with a traditional metal coin (Lira) for an extra heritage touch, it’s the perfect accessory for book lovers or a unique gift.', 45.00, 45.00, 0, 'store/products/cK3r5bFpCeXRHHrB7HMsIkRW7PFRtugN18k9cOez.jpg', '[\"store\\/products\\/cK3r5bFpCeXRHHrB7HMsIkRW7PFRtugN18k9cOez.jpg\"]', 20, 0, 1, '2026-03-17 10:04:05', '2026-03-17 10:13:55'),
(14, 4, 'لوحة \"قبة الخليل\" التراثية – الإصدار الأسود', 'Hebron Heritage Qubbeh Art – Black Edition', 'black-hebronqubbeh-frame', 'hebron-heritage-qubbeh-art-black-edition', 'لوحة جدارية فاخرة تحتفي بتراث مدينة الخليل العريق. تتميز بتطريز يدوي كثيف لـ \"قبة الثوب\" بحجمها الطبيعي، مشغولة بدقة على قماش أسود يمنح الألوان عمقاً وفخامة استثنائية. قطعة فنية مثالية كمركز اهتمام في ديكورك الداخلي أو كهدية تذكارية ملكية.', 'A premium wall art piece celebrating the timeless heritage of Hebron. This life-sized \"Qubbeh\" is meticulously hand-embroidered on a deep black fabric, providing a dramatic contrast that makes the traditional colors pop. An exquisite centerpiece for any space or a prestigious cultural gift.', 500.00, 500.00, 0, 'store/products/T0ovaYx9dtQK8yRTae1ZMwePxFk5yT75BvbsFqRA.jpg', '[\"store\\/products\\/T0ovaYx9dtQK8yRTae1ZMwePxFk5yT75BvbsFqRA.jpg\"]', 2, 0, 1, '2026-03-17 10:13:44', '2026-03-17 10:13:44'),
(15, 3, 'لوحة \"قبة الخليل\" التراثية – الإصدار الأبيض', 'Hebron Heritage Qubbeh Art – White Edition', 'loh-kb-alkhlyl-altrathy-alasdar-alabyd', 'hebron-heritage-qubbeh-art-white-edition', 'إصدار مشرق وراقي من لوحة \"قبة الخليل\" التراثية، حيث يتجلى التطريز اليدوي الفلسطيني بألوانه الدافئة على قماش أبيض ناصع. تمنح الخلفية البيضاء هذه القطعة مظهراً عصرياً يبرز تفاصيل الغرز ودقة العمل اليدوي، مما يجعلها قطعة ديكور مثالية للمساحات التي تبحث عن الجمال والأصالة بروح متجددة.', 'A bright and sophisticated version of the Hebron Heritage Qubbeh Art, where traditional Palestinian embroidery shines against a crisp white fabric. The white background offers a clean, modern look that beautifully showcases the intricate stitches and artistic details. A perfect decor piece for spaces that blend classic heritage with contemporary light.', 500.00, 500.00, 0, 'store/products/e0HA0pqZ9ymPTpc5mfJtFbxLh2MCPzXWs37P7D2Z.jpg', '[\"store\\/products\\/e0HA0pqZ9ymPTpc5mfJtFbxLh2MCPzXWs37P7D2Z.jpg\"]', 3, 0, 1, '2026-03-17 10:18:16', '2026-03-17 10:18:16');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
