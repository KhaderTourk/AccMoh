class MainNavbar extends HTMLElement {
  connectedCallback() {
    this.innerHTML = `
        <nav
        class="sticky top-0 z-50 bg-white/95 dark:bg-background-dark/95 backdrop-blur-md border-b border-slate-200 dark:border-white/10">
        <div class="max-w-7xl mx-auto px-8 lg:px-16 h-14 flex items-center justify-between">
            <div class="flex items-center gap-12">
                <div class="flex items-center gap-2">
                <a href="/">
                    <img src="/assets/img/logo-l.svg" alt="Logo" class="dark:hidden" >

                    <img src="/assets/img/logo-d.svg" alt="Logo" class="hidden dark:block">
                    </a>
                </div>
                <div class="hidden md:flex items-center gap-8">
                    <a class="text-sm font-medium hover:text-primary transition-colors text-slate-600 dark:text-slate-300"
                        href="/">الرئيسية</a>

                    <div class="relative group">
                        <button
                            class="flex items-center gap-1 text-sm font-medium hover:text-primary transition-colors text-slate-600 dark:text-slate-300">
                            <span>من نحن</span>
                            <span
                                class="material-symbols-outlined text-[18px] group-hover:rotate-180 transition-transform">expand_more</span>
                        </button>
                        <ul
                            class="absolute right-0 mt-2 w-48 bg-white dark:bg-card-dark border border-slate-100 dark:border-white/10 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50 py-2">
                            <li><a href="/about-us"
                                    class="block px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary">من
                                    نحن</a></li>
                            <li><a href="/our-team"
                                    class="block px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary">فريقنا</a>
                            </li>
                        </ul>
                    </div>

                    <div class="relative group">
                        <button
                            class="flex items-center gap-1 text-sm font-medium hover:text-primary transition-colors text-slate-600 dark:text-slate-300">
                            <span>مشاريعنا</span>
                            <span
                                class="material-symbols-outlined text-[18px] group-hover:rotate-180 transition-transform">expand_more</span>
                        </button>
                        <ul
                            class="absolute right-0 mt-2 w-56 bg-white dark:bg-card-dark border border-slate-100 dark:border-white/10 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50 py-2">
                            <li><a href="/projects/kanani"
                                    class="block px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary">مشروع
                                    كنعاني</a></li>
                            <li><a href="/projects/tamkeen"
                                    class="block px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary">مشروع
                                    تمكين</a></li>
                            <li><a href="/projects/parasols"
                                    class="block px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary">مشروع
                                    المظلات</a></li>
                        </ul>
                    </div>

                    <a class="text-sm font-medium hover:text-primary transition-colors text-slate-600 dark:text-slate-300"
                        href="/grants">المنح الدراسية</a>

                    <a class="text-sm font-medium hover:text-primary transition-colors text-slate-600 dark:text-slate-300"
                        href="/success-partners">شركاء النجاح</a>

                    <a class="text-sm font-medium hover:text-primary transition-colors text-slate-600 dark:text-slate-300"
                        href="/news">الأخبار</a>
                </div>
            </div>
            <div class="flex items-center gap-6">
                <button
                    class="flex items-center gap-2 hover:text-primary transition-colors text-sm font-medium text-slate-600 dark:text-slate-300">
                    <span class="material-symbols-outlined text-[20px]">language</span>
                    <span>EN</span>
                </button>
                <button
                    class="bg-primary text-white px-7 py-3 rounded-full font-bold hover:brightness-110 transition-all shadow-lg shadow-primary/20 text-sm">
                    تبرع الآن
                </button>
            </div>
        </div>
    </nav>`;
  }
}
customElements.define("main-navbar", MainNavbar);
