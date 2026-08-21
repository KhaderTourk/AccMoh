// 1. إعدادات Tailwind الموحدة
// تم دمج كافة الألوان والخطوط والأنيميشن من جميع النسخ التي أرسلتها
tailwind.config = {
  darkMode: "class",
  theme: {
    extend: {
      animation: {
        "infinite-scroll": "infinite-scroll 25s linear infinite",
      },
      keyframes: {
        "infinite-scroll": {
          "0%": { transform: "translateX(0)" },
          "100%": { transform: "translateX(calc(50% + 2rem))" },
        },
      },
      boxShadow: {
        "card": "0 25px 50px -12px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255,255,255,0.05)",
      },
      colors: {
        primary: "#0BA66D",
        secondary: "#088A5B",
        "background-light": "#F8FAFC",
        "background-dark": "#121212",
        "card-dark": "#1E1E1E",
        "surface-dark": "#1E1E1E",
        "accent-dark": "#2A2A2A",
        "border-dark": "#2D2D2D",
        "text-primary-dark": "#E0E0E0",
        "text-secondary-dark": "#A0A0A0",
        "bg-dark-main": "#121212",
        "bg-dark-card": "#1E1E1E",
        "bg-light-main": "#F8F9FA",
        "text-dark-gray": "#333333",
      },
      fontFamily: {
        display: ["Cairo", "Tajawal", "sans-serif"],
        sans: ["Cairo", "Tajawal", "sans-serif"],
        body: ["Cairo", "Tajawal", "sans-serif"],
      },
      borderRadius: {
        DEFAULT: "0.75rem",
        xl: "1.25rem",
        "2xl": "1.5rem",
        "4xl": "2rem",
      },
    },
  },
};

// 2. وظيفة تبديل الوضع الليلي (Dark Mode Toggle)
function toggleTheme() {
  const html = document.documentElement;
  const themeIcon = document.getElementById("theme-icon");

  if (html.classList.contains("dark")) {
    html.classList.remove("dark");
    localStorage.setItem("theme", "light"); // حفظ التفضيل
    if (themeIcon) themeIcon.innerText = "dark_mode";
  } else {
    html.classList.add("dark");
    localStorage.setItem("theme", "dark"); // حفظ التفضيل
    if (themeIcon) themeIcon.innerText = "light_mode";
  }
}

// 3. تشغيل الوظائف عند تحميل الصفحة
function onReady(fn) {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fn);
  } else {
    fn();
  }
}
onReady(function () {
  // أ: تفعيل Swiper (السلايدر) إذا وجد في الصفحة
  if (document.querySelector(".successStoriesSwiper")) {
    new Swiper(".successStoriesSwiper", {
      rtl: true,
      loop: true,
      spaceBetween: 30,
      pagination: { el: ".swiper-pagination", clickable: true },
      navigation: {
        nextEl: ".swiper-button-next-custom",
        prevEl: ".swiper-button-prev-custom",
      },
      autoplay: { delay: 4000, disableOnInteraction: false },
    });
  }

  // ب: تفعيل عداد الأرقام (Counter Animation) - يدعم .counter و .stat-number
  (function initStatCounters() {
    var doneAttr = "data-counter-done";
    var duration = 1800;

    function formatNum(n, decimals) {
      try {
        var opt = decimals != null && decimals >= 0
          ? { minimumFractionDigits: decimals, maximumFractionDigits: decimals }
          : {};
        return Number(n).toLocaleString("en-US", opt);
      } catch (_) {
        return decimals != null && decimals >= 0 ? Number(n).toFixed(decimals) : String(Math.round(n));
      }
    }

    function startCounter(el) {
      if (!el || el.getAttribute(doneAttr) === "1") return;
      var targetRaw = +el.getAttribute("data-target");
      if (!Number.isFinite(targetRaw) || targetRaw < 0) return;
      el.setAttribute(doneAttr, "1");
      var divisor = +el.getAttribute("data-divisor") || 1;
      var target = targetRaw / divisor;
      var suffix = el.getAttribute("data-suffix") || "";
      var prefix = el.getAttribute("data-prefix") || "";
      var decimals = el.hasAttribute("data-decimals") ? +el.getAttribute("data-decimals") : null;
      var startTime = typeof performance !== "undefined" ? performance.now() : 0;

      function tick() {
        var elapsed = (typeof performance !== "undefined" ? performance.now() : Date.now()) - startTime;
        var progress = Math.min(elapsed / duration, 1);
        var easeOut = 1 - (1 - progress) * (1 - progress);
        var current = target * easeOut;
        el.textContent = prefix + formatNum(current, decimals) + suffix;
        if (progress < 1) requestAnimationFrame(tick);
        else el.textContent = prefix + formatNum(target, decimals) + suffix;
      }
      requestAnimationFrame(tick);
    }

    function run() {
      var counters = document.querySelectorAll(".counter, .stat-number");
      for (var i = 0; i < counters.length; i++) startCounter(counters[i]);
    }

    run();
    setTimeout(run, 150);
    setTimeout(run, 500);
    window.addEventListener("load", function () { run(); setTimeout(run, 100); });
    setTimeout(run, 1200);
  })();

  // ج: قائمة الجوال (Mobile Menu Toggle)
  const mobileMenuBtn = document.getElementById("mobile-menu-btn");
  const mobileMenu = document.getElementById("mobile-menu");
  const menuIcon = document.getElementById("menu-icon");
  if (mobileMenuBtn && mobileMenu && menuIcon) {
    mobileMenuBtn.addEventListener("click", function () {
      mobileMenu.classList.toggle("hidden");
      menuIcon.innerText = mobileMenu.classList.contains("hidden") ? "menu" : "close";
    });
  }

  // د: استعادة الثيم المفضل عند إعادة تحميل الصفحة
  if (localStorage.getItem("theme") === "dark") {
    document.documentElement.classList.add("dark");
    const themeIcon = document.getElementById("theme-icon");
    if (themeIcon) themeIcon.innerText = "light_mode";
  }
});
