class MainFooter extends HTMLElement {
  connectedCallback() {
    this.innerHTML = `
  <footer class="bg-white dark:bg-[#0C0C0C] border-t border-slate-100 dark:border-white/5 pt-20 pb-10">
        <div class="main-container">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-6">
                <div class="col-span-1">
                    <div class="flex items-center gap-2 mb-6">
                    <a href="/">
                        <img src="/assets/img/logo-footer-l.svg" alt="Logo" class="dark:hidden">

                        <img src="/assets/img/logo-footer-d.svg" alt="Logo" class="hidden dark:block">
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold mb-6 text-slate-900 dark:text-white">روابط سريعة</h3>
                    <ul class="space-y-4 text-sm text-slate-500 dark:text-text-secondary-dark">
                        <li><a class="hover:text-primary transition-colors flex items-center gap-2" href="/"><span
                                    class="w-1.5 h-1.5 rounded-full bg-primary/40"></span>الرئيسية</a></li>
                        <li><a class="hover:text-primary transition-colors flex items-center gap-2" href="/about-us"><span
                                    class="w-1.5 h-1.5 rounded-full bg-primary/40"></span>من نحن</a></li>
                        <li><a class="hover:text-primary transition-colors flex items-center gap-2" href="#"><span
                                    class="w-1.5 h-1.5 rounded-full bg-primary/40"></span>مشاريعنا</a></li>
                        <li><a class="hover:text-primary transition-colors flex items-center gap-2" href="/grants"><span
                                    class="w-1.5 h-1.5 rounded-full bg-primary/40"></span>المنح الدراسية</a></li>
                        <li><a class="hover:text-primary transition-colors flex items-center gap-2" href="/news"><span
                                    class="w-1.5 h-1.5 rounded-full bg-primary/40"></span>الأخبار</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold mb-6 text-slate-900 dark:text-white">تواصل معنا</h3>
                    <ul class="space-y-4 text-sm text-slate-500 dark:text-text-secondary-dark">
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary text-lg">location_on</span>
                            <span>فلسطين، بيرزيت، حرم جامعة بيرزيت</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary text-lg">call</span>
                            <span dir="ltr">+970 2 298 2000</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary text-lg">mail</span>
                            <span>info@fobzu.org</span>
                        </li>
                    </ul>
                </div>

                <div class="col-span-1">
                    <div>
                        <h3 class="font-bold mb-6 text-slate-900 dark:text-white">نشرتنا الإخبارية</h3>
                        <p class="text-sm text-slate-500 dark:text-text-secondary-dark mb-4">اشترك ليصلك جديد المنح
                            والبرامج
                            التدريبية.</p>
                        <div class="relative group">
                            <input
                                class="w-full bg-slate-100 dark:bg-white/5 border border-transparent focus:border-primary/50 rounded-xl py-3 px-4 focus:ring-0 text-sm dark:text-white placeholder:text-slate-400 transition-all"
                                placeholder="البريد الإلكتروني" type="email" />
                            <button
                                class="absolute left-2 top-1.5 bg-primary text-white p-1.5 rounded-lg hover:scale-105 active:scale-95 transition-all shadow-md shadow-primary/20">
                                <span class="material-symbols-outlined text-sm">send</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-4">
                        <h3 class="font-bold mb-6 text-slate-900 dark:text-white">تابعنا على</h3>
                        <a href="#"
                            class="group w-9 h-9 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center transition-all duration-300 hover:bg-[#1877F2] hover:-translate-y-1">
                            <svg class="w-5 h-5 fill-slate-600 dark:fill-slate-400 group-hover:fill-white"
                                viewBox="0 0 24 24">
                                <path
                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                            </svg>
                        </a>
                        <a href="#"
                            class="group w-9 h-9 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center transition-all duration-300 hover:bg-[#1DA1F2] hover:-translate-y-1">
                            <svg class="w-5 h-5 fill-slate-600 dark:fill-slate-400 group-hover:fill-white"
                                viewBox="0 0 24 24">
                                <path
                                    d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.84 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z" />
                            </svg>
                        </a>
                        <a href="#"
                            class="group w-9 h-9 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center transition-all duration-300 hover:bg-[#E4405F] hover:-translate-y-1">
                            <svg class="w-5 h-5 fill-slate-600 dark:fill-slate-400 group-hover:fill-white"
                                viewBox="0 0 24 24">
                                <path
                                    d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.015 7.053.072 3.51.232 1.567 2.16 1.407 5.7c-.058 1.281-.072 1.689-.072 4.948 0 3.259.014 3.668.072 4.948.16 3.537 2.103 5.471 5.64 5.631 1.28.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 3.534-.16 5.468-2.103 5.631-5.64.058-1.28.072-1.689.072-4.948 0-3.259-.014-3.668-.072-4.948-.161-3.534-2.103-5.468-5.64-5.631C15.668.015 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <div
                class="pt-8 border-t border-slate-100 dark:border-white/5 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-slate-400 dark:text-text-secondary-dark">
                <p>© 2026 جمعية أصدقاء جامعة بيرزيت. جميع الحقوق محفوظة.</p>
                <div class="flex gap-6">
                    <a href="#" class="hover:text-primary transition-colors">سياسة الخصوصية</a>
                    <a href="#" class="hover:text-primary transition-colors">شروط الاستخدام</a>
                </div>
            </div>
        </div>
    </footer>  
`;
  }
}
customElements.define("main-footer", MainFooter);
