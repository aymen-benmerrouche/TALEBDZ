/* ============================================================
   app.js — UniChat  |  All JavaScript
   Handles: theme · language (EN/AR/FR) · nav · admin panels
   Mobile-optimized with touch support
   ============================================================ */

// ── Prevent double-tap zoom on buttons (iOS) ────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Prevent double-tap zoom on interactive elements
  const touchElements = document.querySelectorAll('button, .btn, .sb-item, .lang-opt, .tab-btn');
  touchElements.forEach(el => {
    el.style.touchAction = 'manipulation';
  });
  
  // Initialize responsive image loading
  initResponsiveImages();
  
  // Add swipe gesture support for mobile menu
  initSwipeGestures();
});

// ── Responsive image loading ────────────────────────────────
function initResponsiveImages() {
  const images = document.querySelectorAll('img[loading="lazy"]');
  if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          if (img.dataset.src) {
            img.src = img.dataset.src;
            imageObserver.unobserve(img);
          }
        }
      });
    });
    images.forEach(img => imageObserver.observe(img));
  }
}

// ── Swipe gesture support ───────────────────────────────────
function initSwipeGestures() {
  let touchStartX = 0;
  let touchEndX = 0;
  
  document.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
  }, { passive: true });
  
  document.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
  }, { passive: true });
  
  function handleSwipe() {
    const swipeThreshold = 50;
    const diff = touchStartX - touchEndX;
    
    // Swipe left to close mobile menu
    if (diff > swipeThreshold) {
      const mobNav = document.getElementById('mob-nav');
      if (mobNav && mobNav.classList.contains('open')) {
        closeMob();
      }
    }
    
    // Swipe right to open mobile menu (from left edge only)
    if (diff < -swipeThreshold && touchStartX < 20) {
      const sidebar = document.getElementById('sidebar');
      if (sidebar && !sidebar.classList.contains('open') && window.innerWidth <= 900) {
        toggleSidebar();
      }
    }
  }
}



/* ============================================================
   TRANSLATIONS
   ============================================================ */
const T = {

  /* ── ENGLISH ── */
  en: {
    /* NAV */
    nav_features:   'Features',
    nav_about:      "About",
    nav_how:        'How it works',
    nav_pricing:    'Pricing',
    nav_contact:    'Contact',
    nav_admin:      'Admin Login',
    nav_start:      'Get Started',
    nav_back:       '← Back to site',
    /* HERO */
    hero_pill:      'AI-Powered University Assistant',
    hero_h1a:       'Your Campus,',
    hero_h1b:       'Smarter.',
    hero_p:         'Instant answers to any university question, a student community, smart reminders, and more — powered by RAG and LLM. Built for students, trusted by institutions.',
    hero_dl:        'Download the App',
    hero_demo:      'Learn More',
    stat_students:  'Students',
    stat_accuracy:  'Accuracy',
    stat_speed:     'Response Time',
    /* FEATURE ROWS */
    tag_chat:       'AI Chat',
    h2_chat:        'Answers in seconds, not office hours',
    p_chat:         "Students ask anything — enrollment deadlines, scholarships, campus life — and get precise, cited answers pulled directly from your university's official documents via RAG.",
    li_chat_2:      'Separate mode for prospective vs. enrolled students',
    li_chat_3:      'Available 24 / 7 with no waiting',
    tag_community:  'Community',
    h2_community:   'Students helping students',
    p_community:    'A built-in forum where students post questions, share notes, like and comment. Every post is moderated from the admin dashboard.',
    li_com_1:       'Post questions and get peer answers',
    li_com_2:       'comment and Like posts',
    li_com_3:       'Admin moderates all content centrally',
    tag_reminders:  'Reminders',
    h2_reminders:   'Never miss a deadline again',
    p_reminders:    'Students set personal reminders for assignments, exams, and events.',
    li_rem_1:       'Personal reminders synced to the calendar',
    li_rem_2:       'Color-coded by category and urgency',
    li_rem_3:       'Admin-pushed campus-wide alerts',
    tag_events: 'Events',
    h2_events:  'Events tailored to your field',
    p_events:   'Each student sees upcoming events matched to their speciality — workshops, seminars, career fairs, and campus activities relevant to what they study.',
    li_ev_1:    'Personalised feed based on your major & interests',
    li_ev_2:    'Soonest events shown first, never miss a deadline',
    
    /* FEATURES GRID */
    tag_all:        'Everything you need',
    sec_feat_h:     'Built for students.\nTrusted by institutions.',
    sec_feat_p:     'Every feature is designed to reduce friction and increase student success — prospective or enrolled.',
    f1_h: 'RAG-Powered Answers',     f1_p: 'Pulls from your university\'s official documents to give precise, cited answers — never hallucinations.',
    f2_h: 'Student Community',       f2_p: 'Built-in forum for posts, likes, and comments — fully moderated through admin panel.',
    f3_h: 'Smart Reminders',         f3_p: 'Personal and campus-wide reminders synced to each student\'s schedule.',
    f4_h: 'Dual Student Modes',      f4_p: 'Tailored information for applicants and enrolled students at every stage.',
    f5_h: 'Admin Control Center',    f5_p: 'Full visibility into users, posts, and analytics.',
    f6_h: 'Flexible Billing',        f6_p: 'Free tier for prospects, monthly plans for enrolled students. Managed from one dashboard.',

    tag_about: "About",
    h2_about:  "About TalebDZ",
    p_about:   "TalebDZ is an AI-powered student assistant designed to help university students manage their academic life. From intelligent chat assistance to event management and schedule planning, TalebDZ is your companion for academic success.",
    /* HOW IT WORKS */
    tag_how:        'How it works',
    sec_how_h:      'From question to answer in seconds',
    step1_h: 'Student asks',         step1_p: 'Typed naturally in the chat — no special commands needed.',
    step2_h: 'RAG retrieves',        step2_p: 'The system searches your vectorized database for the most relevant context.',
    step3_h: 'LLM generates',        step3_p: 'The retrieved context is sent to the LLM via API to produce a grounded, cited answer.',
    step4_h: 'Save in database',    step4_p: 'The system save the conversation in the database',
    /* PRICING */
    tag_pricing:    'Pricing',
    sec_price_h:    'Simple, fair pricing',
    sec_price_p:    'Students shouldn\'t pay a lot for help. Neither should your institution.',
    pl1_name: 'Explorer',            pl1_desc: 'For prospective students',
    pl1_amt: 'Free',                 pl1_per: 'forever',
    pl1_f1: '20 AI questions / month', pl1_f2: 'Admissions & campus info', pl1_f3: 'Public community read',
    pl1_btn: 'Get Started Free',
    pl2_badge: 'Most Popular',
    pl2_name: 'Student Pro',         pl2_desc: 'For enrolled students',
    pl2_amt: '150 DZD',                   pl2_per: '/month · billed monthly',
    pl2_f1: 'Unlimited AI questions', pl2_f2: 'Full community access', pl2_f3: 'Smart reminders & calendar', pl2_f4: 'Priority support',
    pl2_btn: 'Start Free Trial',
    pl3_name: 'Institution',         pl3_desc: 'For universities',
    pl3_amt: 'Custom',               pl3_per: 'contact us',
    pl3_f1: 'Unlimited students', pl3_f2: 'Custom RAG knowledge base', pl3_f3: 'Admin dashboard access', 
    pl3_btn: 'Contact Sales',
    /* CTA */
    cta_h: 'Ready to transform your campus?',
    cta_p: 'Join students already using TalebDZ.',
    cta_dl: 'Download Now',
    /* FOOTER */
    f_contact: 'Contact us',
    f_copy: '© 2026 TalebDZ. All rights reserved.',
    /* ADMIN NAV */
    a_crumb: 'Admin Dashboard',
    /* ADMIN SIDEBAR */
    sb_overview: 'Overview',    sb_g_overview: 'Overview',
    sb_analytics:'Analytics',
    sb_g_app:    'App Management',
    sb_users:    'Users',
    sb_faq:      'FAQ / RAG KB',
    sb_community:'Community',
    sb_announce: 'Announcements',
    sb_billing_a:'Subscriptions',
    sb_g_site:   'Website',
    sb_website:  'Edit Content',
    sb_settings: 'Settings',
    /* ADMIN OVERVIEW */
    ov_h:   'Good morning, Admin',
    ov_p:   'Here\'s what\'s happening with TalebDZ today.',
    m_users:'Total Users',         m_active:'Active Today',
    m_qs:   'Questions Asked',     m_flagged:'Flagged Posts',
    m_rev:  'Monthly Revenue',
    m_u_d:  '↑ 12 % this week',   m_a_d: '↑ 8 % vs yesterday',
    m_q_d:  '↑ 5 % this week',    m_f_d: 'Needs review',
    m_r_d:  '↑ 18 % MoM',
    ch_daily:'Daily Questions',    ch_sub: 'Last 7 days',
    ch_split:'User Split',
    enrolled:'Enrolled (70%)',     prospective:'Prospective (30%)',
    act_title:'Recent Activity',
    act1: 'registered as a new student',
    act2: 'Post flagged by 3 users — "Off-topic content"',
    act3: 'FAQ entry "Scholarship deadlines" updated in RAG',
    act4: '12 new subscriptions activated this morning',
    /* ADMIN USERS */
    usr_h: 'Users', usr_p: 'Manage enrolled and prospective students.',
    /* ADMIN FAQ */
    faq_h: 'FAQ / RAG Knowledge Base',
    faq_p: 'Edits are embedded immediately — the chatbot retrieves updates in real-time.',
    faq_add: 'Add Entry', faq_upload: 'Upload Document',
    faq_kb: '512 entries indexed · Last embedded: 2 hours ago',
    /* ADMIN COMMUNITY */
    com_h: 'Community', com_p: 'Moderate posts and comments.',
    /* ADMIN ANNOUNCE */
    ann_h: 'Announcements', ann_p: 'Push notifications to all app users instantly.',
    ann_new: 'New Announcement', ann_send: 'Send Announcement',
    ann_sent: 'Sent Announcements',
    /* ADMIN BILLING */
    bil_h: 'Subscriptions', bil_p: 'Monitor revenue and manage student plans.',
    bil_pro: 'Active Pro',  bil_mrr: 'MRR',
    bil_ch:  'Churned',     bil_free: 'Free Users',
    bil_txn: 'Recent Transactions',
    /* ADMIN WEBSITE */
    web_h: 'Edit Website Content',
    web_p: 'Changes here update the public-facing marketing site.',
    web_hero: 'Hero Section', web_pricing: 'Pricing', web_links: 'Contact & Links',
    /* ADMIN SETTINGS */
    set_h: 'Settings', set_p: 'Configure your chatbot app and website.',
    set_bot: 'Chatbot Settings', set_llm: 'LLM / API Configuration', set_acc: 'Admin Account',
    /* ADMIN ANALYTICS */
    an_h: 'Analytics', an_p: 'Deep dive into usage patterns and chatbot performance.',
    an_session: 'Avg Session',  an_topic: 'Top Topic',
    an_rag: 'RAG Hit Rate',     an_unanswered: 'Unanswered',
    an_tbl: 'Top Unanswered Questions', an_add: 'Add to FAQ',
  },

  /* ── ARABIC ── */
  ar: {
    nav_features:  'الميزات',
    nav_about: "حول",
    nav_how:       'كيف يعمل',
    nav_pricing:   'الأسعار',
    nav_contact:   'تواصل معنا',
    nav_admin:     'دخول المسؤول',
    nav_start:     'ابدأ الآن',
    nav_back:      '→ العودة للموقع',
    nav_dashboard: 'لوحة التحكم',
    hero_pill:     'مساعد جامعي بالذكاء الاصطناعي',
    hero_h1a:      'حرمك الجامعي،',
    hero_h1b:      'أذكى.',
    hero_p:        'إجابات فورية على أي سؤال جامعي، مجتمع طلابي، تذكيرات ذكية والمزيد — مدعوم بـ RAG ونماذج اللغة الكبيرة.',
    hero_dl:       'تحميل التطبيق',
    hero_demo:     'اكتشف المزيد',
    stat_students: 'طالب',
    stat_accuracy: 'دقة',
    stat_speed:    'وقت الرد',
    stat_faqs:     'سؤال مفهرس',
    tag_chat:      'المحادثة الذكية',
    h2_chat:       'إجابات في ثوانٍ، لا ساعات الدوام',
    p_chat:        'يسأل الطلاب عن أي شيء — التسجيل، المنح، الحياة الجامعية — ويحصلون على إجابات دقيقة من الوثائق الرسمية عبر RAG.',
    li_chat_1:     'استرجاع معزز من قاعدة الأسئلة الحقيقية',
    li_chat_2:     'وضع منفصل للطلاب المحتملين والمسجلين',
    li_chat_3:     'متاح 24/7 دون انتظار',
    tag_community: 'المجتمع',
    h2_community:  'الطلاب يساعدون بعضهم',
    p_community:   'منتدى مدمج حيث يطرح الطلاب الأسئلة ويتشاركون الملاحظات ويتفاعلون مع بعضهم.',
    li_com_1:      'نشر الأسئلة والحصول على إجابات من الزملاء',
    li_com_2:      'إعجاب، تعليق، وحفظ المنشورات',
    li_com_3:      'يشرف المسؤول على جميع المحتوى',
    tag_reminders: 'التذكيرات',
    h2_reminders:  'لا تفوّت موعداً نهائياً بعد الآن',
    p_reminders:   'يضع الطلاب تذكيرات شخصية، ويمكن للمسؤولين إرسال تنبيهات جامعية للجميع.',
    li_rem_1:      'تذكيرات شخصية متزامنة مع التقويم',
    li_rem_2:      'مرمزة بالألوان حسب الأولوية',
    li_rem_3:      'تنبيهات جامعية من لوحة التحكم',
    tag_events: 'الفعاليات',
    h2_events:  'فعاليات مخصصة لتخصصك',
    p_events:   'يرى كل طالب الفعاليات القادمة المناسبة لتخصصه — ورش عمل، ندوات، معارض مهنية وأنشطة جامعية.',
    li_ev_1:    'خلاصة مخصصة بناءً على تخصصك واهتماماتك',
    li_ev_2:    'أقرب الفعاليات تظهر أولاً، لا تفوّت أي موعد',
    tag_all:       'كل ما تحتاجه',
    sec_feat_h:    'مصمم للطلاب.\nموثوق به من المؤسسات.',
    sec_feat_p:    'كل ميزة مصممة لتقليل العوائق وزيادة نجاح الطلاب.',
    f1_h: 'إجابات RAG',            f1_p: 'يسحب من وثائق جامعتك لإعطاء إجابات دقيقة.',
    f2_h: 'مجتمع الطلاب',          f2_p: 'منتدى مدمج للمنشورات والإعجابات والتعليقات.',
    f3_h: 'تذكيرات ذكية',          f3_p: 'تذكيرات شخصية وجامعية متزامنة.',
    f4_h: 'وضعان للطلاب',          f4_p: 'معلومات مخصصة لكل مرحلة — مقدم طلب أو مسجل.',
    f5_h: 'مركز تحكم المسؤول',     f5_p: 'رؤية كاملة للمستخدمين والمنشورات والأسئلة والتحليلات.',
    f6_h: 'فوترة مرنة',            f6_p: 'مستوى مجاني للمحتملين، خطط شهرية للمسجلين.',
    
    tag_about: "حول",
    h2_about:  "حول TalebDZ",
    p_about:   "TalebDZ هو مساعد طلابي مدعوم بالذكاء الاصطناعي، مصمم لمساعدة طلاب الجامعة على إدارة حياتهم الأكاديمية. من المساعدة الذكية عبر الدردشة إلى إدارة الفعاليات وتخطيط الجداول الزمنية، TalebDZ  هو رفيقك نحو النجاح الأكاديمي.",
    
    tag_how:       'كيف يعمل',
    sec_how_h:     'من السؤال إلى الإجابة في ثوانٍ',
    step1_h: 'يطرح الطالب السؤال',    step1_p: 'بلغة طبيعية — لا صياغة خاصة.',
    step2_h: 'RAG يسترجع السياق',     step2_p: 'يبحث النظام في قاعدة الأسئلة المتجهة.',
    step3_h: 'نموذج اللغة يولّد الرد', step3_p: 'يُمرَّر السياق إلى النموذج عبر API لإنتاج رد دقيق.',
    step4_h: 'المسؤول يحافظ على التحديث', step4_p: 'أضف أو عدّل الأسئلة وسيتحدث المخزن فوراً.',
    tag_pricing:  'الأسعار',
    sec_price_h:  'أسعار بسيطة وعادلة',
    sec_price_p:  'لا ينبغي أن يدفع الطلاب كثيراً للحصول على المساعدة.',
    pl1_name: 'مستكشف',             pl1_desc: 'للطلاب المحتملين',
    pl1_amt: 'مجاني',               pl1_per: 'للأبد',
    pl1_f1: '٢٠ سؤالاً ذكياً شهرياً', pl1_f2: 'معلومات القبول والحرم', pl1_f3: 'قراءة المجتمع العام',
    pl1_btn: 'ابدأ مجاناً',
    pl2_badge: 'الأكثر شعبية',
    pl2_name: 'الطالب المحترف',     pl2_desc: 'للطلاب المسجلين',
    pl2_amt: 'دج 150',                   pl2_per: '/شهر · يُفوتر شهرياً',
    pl2_f1: 'أسئلة ذكية غير محدودة', pl2_f2: 'وصول كامل للمجتمع',
    pl2_f3: 'تذكيرات ذكية وتقويم',  pl2_f4: 'دعم ذو أولوية',
    pl2_btn: 'ابدأ التجربة المجانية',
    pl3_name: 'مؤسسة',              pl3_desc: 'للجامعات',
    pl3_amt: 'مخصص',                pl3_per: 'تواصل معنا',
    pl3_f1: 'طلاب غير محدودين', pl3_f2: 'قاعدة RAG مخصصة',
    pl3_f3: 'لوحة تحكم المسؤول', 
    pl3_btn: 'تواصل مع المبيعات',
    cta_h: 'جاهز لتحويل تجربة حرمك؟',
    cta_p: 'انضم إلى  الطلاب الذين يستخدمون TalebDZ.',
    cta_dl: 'تحميل الآن', 
    f_contact: 'تواصل معنا',
    f_copy: '© ٢٠٢٦ TalebDZ. جميع الحقوق محفوظة.',
    a_crumb: 'لوحة التحكم',
    sb_g_overview:'نظرة عامة',      sb_overview: 'لوحة التحكم',
    sb_analytics: 'التحليلات',      sb_g_app: 'إدارة التطبيق',
    sb_users: 'المستخدمون',         sb_faq: 'قاعدة الأسئلة',
    sb_community: 'المجتمع',        sb_announce: 'الإعلانات',
    sb_billing_a: 'الاشتراكات',     sb_g_site: 'الموقع',
    sb_website: 'تعديل المحتوى',    sb_settings: 'الإعدادات',
    ov_h: 'صباح الخير، مسؤول 👋',   ov_p: 'هذا ما يحدث في UniChat اليوم.',
    m_users:'إجمالي المستخدمين',    m_active:'نشطون اليوم',
    m_qs:'أسئلة مطروحة',            m_flagged:'منشورات مُبلّغ عنها',
    m_rev:'الإيرادات الشهرية',
    m_u_d:'↑ ١٢٪ هذا الأسبوع',     m_a_d:'↑ ٨٪ مقارنة بالأمس',
    m_q_d:'↑ ٥٪ هذا الأسبوع',      m_f_d:'يحتاج مراجعة',
    m_r_d:'↑ ١٨٪ هذا الشهر',
    ch_daily:'الأسئلة اليومية',     ch_sub:'آخر ٧ أيام',
    ch_split:'توزيع المستخدمين',
    enrolled:'مسجلون (٧٠٪)',        prospective:'محتملون (٣٠٪)',
    act_title:'النشاط الأخير',
    act1:'سجّل كطالب جديد',
    act2:'تم الإبلاغ عن منشور — "محتوى خارج الموضوع"',
    act3:'تم تحديث سؤال "مواعيد المنح" في RAG',
    act4:'تم تفعيل ١٢ اشتراكاً جديداً',
    usr_h:'المستخدمون',             usr_p:'إدارة الطلاب المسجلين والمحتملين.',
    faq_h:'قاعدة الأسئلة / RAG',   faq_p:'التعديلات تُضمَّن فوراً — يسترجعها الروبوت فورياً.',
    faq_add:'إضافة سؤال',          faq_upload:'رفع مستند',
    faq_kb:'٥١٢ سؤالاً مفهرساً · آخر تضمين: منذ ساعتين',
    com_h:'المجتمع',               com_p:'الإشراف على المنشورات والتعليقات.',
    ann_h:'الإعلانات',             ann_p:'إرسال إشعارات لجميع مستخدمي التطبيق.',
    ann_new:'إعلان جديد',          ann_send:'إرسال الإعلان',
    ann_sent:'الإعلانات المرسلة',
    bil_h:'الاشتراكات',            bil_p:'مراقبة الإيرادات وإدارة خطط الطلاب.',
    bil_pro:'مشتركون نشطون',        bil_mrr:'الإيراد الشهري',
    bil_ch:'الإلغاءات',            bil_free:'المستخدمون المجانيون',
    bil_txn:'المعاملات الأخيرة',
    web_h:'تعديل محتوى الموقع',    web_p:'التغييرات تظهر فوراً على الموقع العام.',
    web_hero:'قسم الترحيب',        web_pricing:'الأسعار', web_links:'التواصل والروابط',
    set_h:'الإعدادات',             set_p:'ضبط التطبيق والموقع.',
    set_bot:'إعدادات الروبوت',     set_llm:'إعدادات نموذج اللغة', set_acc:'حساب المسؤول',
    an_h:'التحليلات',              an_p:'تحليل عميق لأنماط الاستخدام.',
    an_session:'متوسط الجلسة',     an_topic:'أكثر موضوع',
    an_rag:'معدل استجابة RAG',      an_unanswered:'بلا إجابة',
    an_tbl:'أكثر الأسئلة بلا إجابة', an_add:'إضافة للأسئلة',
  },

  /* ── FRENCH ── */
  fr: {
    nav_features:  'Fonctionnalités',
    nav_about: "À propos",
    nav_how:       'Comment ça marche',
    nav_pricing:   'Tarifs',
    nav_contact:   'Contact',
    nav_admin:     'Connexion Admin',
    nav_start:     'Commencer',
    nav_back:      '← Retour au site',
    nav_dashboard: 'Tableau de bord',
    hero_pill:     'Assistant universitaire IA',
    hero_h1a:      'Votre campus,',
    hero_h1b:      'Plus intelligent.',
    hero_p:        'Réponses instantanées, communauté étudiante, rappels intelligents et bien plus — propulsé par RAG et des LLM.',
    hero_dl:       'Télécharger l\'app',
    hero_demo:     'En savoir plus',
    stat_students: 'Étudiants',
    stat_accuracy: 'Précision',
    stat_speed:    'Temps de réponse',
    stat_faqs:     'FAQ indexées',
    tag_chat:      'Chat IA',
    h2_chat:       'Réponses en secondes, pas pendant les permanences',
    p_chat:        'Les étudiants posent toutes leurs questions et obtiennent des réponses précises tirées des documents officiels de votre université via RAG.',
    li_chat_1:     'Génération augmentée depuis votre base FAQ réelle',
    li_chat_2:     'Mode séparé pour candidats et inscrits',
    li_chat_3:     'Disponible 24h/24 sans attente',
    tag_community: 'Communauté',
    h2_community:  'Les étudiants s\'entraident',
    p_community:   'Un forum intégré où les étudiants posent des questions, partagent des notes, likent et commentent.',
    li_com_1:      'Poster des questions et obtenir des réponses',
    li_com_2:      'Liker, commenter et sauvegarder',
    li_com_3:      'L\'admin modère tout le contenu',
    tag_reminders: 'Rappels',
    h2_reminders:  'Ne manquez plus aucune échéance',
    p_reminders:   'Les étudiants créent des rappels personnels; les admins diffusent des alertes à toute la communauté.',
    li_rem_1:      'Rappels personnels synchronisés avec l\'agenda',
    li_rem_2:      'Codés par couleur selon l\'urgence',
    tag_events: 'Événements',
    h2_events:  'Des événements adaptés à votre filière',
    p_events:   'Chaque étudiant voit les événements à venir correspondant à sa spécialité — ateliers, séminaires, forums et activités campus.',
    li_ev_1:    'Fil personnalisé selon votre filière et vos intérêts',
    li_ev_2:    'Les événements les plus proches en premier, ne ratez rien',
    tag_all:       'Tout ce dont vous avez besoin',
    sec_feat_h:    'Conçu pour les étudiants.\nFait confiance par les institutions.',
    sec_feat_p:    'Chaque fonctionnalité réduit les frictions et améliore la réussite étudiante.',
    f1_h: 'Réponses RAG',           f1_p: 'Tire des documents officiels pour des réponses précises et sourcées.',
    f2_h: 'Communauté étudiante',   f2_p: 'Forum intégré pour publications, likes et commentaires.',
    f3_h: 'Rappels intelligents',   f3_p: 'Rappels personnels et campus synchronisés.',
    f4_h: 'Deux modes étudiants',   f4_p: 'Informations adaptées selon le profil.',
    f5_h: 'Centre de contrôle',     f5_p: 'Visibilité totale sur les utilisateurs, publications et analytiques.',
    f6_h: 'Facturation flexible',   f6_p: 'Niveau gratuit et forfaits mensuels gérés depuis le tableau de bord.',

    tag_about: "À propos",
    h2_about:  "À propos de TalebDZ",
    p_about:   "TalebDZ est un assistant étudiant propulsé par l'IA, conçu pour aider les étudiants universitaires à gérer leur vie académique. De l'assistance par chat intelligent à la gestion des événements et à la planification des horaires, TalebDZ est votre compagnon pour la réussite académique.",
    
    tag_how:       'Comment ça marche',
    sec_how_h:     'De la question à la réponse en quelques secondes',
    step1_h: 'L\'étudiant pose une question',   step1_p: 'En langage naturel — aucune syntaxe spéciale.',
    step2_h: 'RAG récupère le contexte',        step2_p: 'Le système cherche dans votre base FAQ vectorisée.',
    step3_h: 'Le LLM génère la réponse',        step3_p: 'Le contexte est transmis au LLM via API.',
    step4_h: 'L\'admin maintient à jour',       step4_p: 'Ajoutez ou modifiez des FAQ et la base se met à jour.',
    tag_pricing:  'Tarifs',
    sec_price_h:  'Des tarifs simples et équitables',
    sec_price_p:  'Les étudiants ne devraient pas payer cher pour de l\'aide.',
    pl1_name: 'Explorateur',        pl1_desc: 'Pour les candidats',
    pl1_amt: 'Gratuit',             pl1_per: 'pour toujours',
    pl1_f1: '20 questions IA / mois', pl1_f2: 'Infos admissions & campus', pl1_f3: 'Lecture communauté publique',
    pl1_btn: 'Commencer gratuitement',
    pl2_badge: 'Le plus populaire',
    pl2_name: 'Étudiant Pro',       pl2_desc: 'Pour les étudiants inscrits',
    pl2_amt: '150 DZD',                 pl2_per: '/mois · facturé mensuellement',
    pl2_f1: 'Questions IA illimitées', pl2_f2: 'Accès complet à la communauté',
    pl2_f3: 'Rappels & agenda',     pl2_f4: 'Support prioritaire',
    pl2_btn: 'Commencer l\'essai gratuit',
    pl3_name: 'Institution',        pl3_desc: 'Pour les universités',
    pl3_amt: 'Sur devis',           pl3_per: 'contactez-nous',
    pl3_f1: 'Étudiants illimités', pl3_f2: 'Base RAG personnalisée',
    pl3_f3: 'Tableau de bord admin', 
    pl3_btn: 'Contacter les ventes',
    cta_h: 'Prêt à transformer votre campus ?',
    cta_p: 'Rejoignez  d\'étudiants qui utilisent déjà TalebDZ.',
    cta_dl: 'Télécharger maintenant',
    f_contact: 'Contactez-nous', 
    f_copy: '© 2026 TalebDZ. Tous droits réservés.',
    a_crumb: 'Tableau de bord',
    sb_g_overview:'Aperçu',         sb_overview:'Tableau de bord',
    sb_analytics:'Analytique',      sb_g_app:'Gestion de l\'app',
    sb_users:'Utilisateurs',        sb_faq:'FAQ / Base RAG',
    sb_community:'Communauté',      sb_announce:'Annonces',
    sb_billing_a:'Abonnements',     sb_g_site:'Site web',
    sb_website:'Modifier le contenu', sb_settings:'Paramètres',
    ov_h:'Bonjour, Admin ',       ov_p:'Voici ce qui se passe sur TalebDZ aujourd\'hui.',
    m_users:'Total utilisateurs',   m_active:'Actifs aujourd\'hui',
    m_qs:'Questions posées',        m_flagged:'Publications signalées',
    m_rev:'Revenus mensuels',
    m_u_d:'↑ 12 % cette semaine',   m_a_d:'↑ 8 % vs hier',
    m_q_d:'↑ 5 % cette semaine',    m_f_d:'À modérer',
    m_r_d:'↑ 18 % ce mois',
    ch_daily:'Questions quotidiennes', ch_sub:'7 derniers jours',
    ch_split:'Répartition',
    enrolled:'Inscrits (70%)',      prospective:'Candidats (30%)',
    act_title:'Activité récente',
    act1:'s\'est inscrit comme nouvel étudiant',
    act2:'Publication signalée — "Contenu hors sujet"',
    act3:'Entrée FAQ "Bourses" mise à jour dans RAG',
    act4:'12 nouveaux abonnements activés ce matin',
    usr_h:'Utilisateurs',           usr_p:'Gérer les étudiants inscrits et candidats.',
    faq_h:'FAQ / Base de connaissances RAG', faq_p:'Les modifications sont intégrées immédiatement.',
    faq_add:'Ajouter une entrée',   faq_upload:'Charger un document',
    faq_kb:'512 entrées indexées · Dernière intégration : il y a 2 h',
    com_h:'Communauté',             com_p:'Modérer les publications et commentaires.',
    ann_h:'Annonces',               ann_p:'Envoyer des notifications à tous les utilisateurs.',
    ann_new:'Nouvelle annonce',     ann_send:'Envoyer l\'annonce',
    ann_sent:'Annonces envoyées',
    bil_h:'Abonnements',            bil_p:'Surveiller les revenus et gérer les forfaits.',
    bil_pro:'Pro actifs',           bil_mrr:'MRR',
    bil_ch:'Résiliations',          bil_free:'Utilisateurs gratuits',
    bil_txn:'Transactions récentes',
    web_h:'Modifier le contenu du site', web_p:'Les changements s\'affichent sur le site public.',
    web_hero:'Section héro',        web_pricing:'Tarifs', web_links:'Contact & liens',
    set_h:'Paramètres',             set_p:'Configurer l\'app et le site.',
    set_bot:'Paramètres du chatbot', set_llm:'Configuration LLM / API', set_acc:'Compte admin',
    an_h:'Analytique',              an_p:'Analyse approfondie des performances.',
    an_session:'Durée moy. session', an_topic:'Sujet principal',
    an_rag:'Taux de succès RAG',    an_unanswered:'Sans réponse',
    an_tbl:'Questions sans réponse', an_add:'Ajouter à FAQ',
  }
};

/* ============================================================
   STATE
   ============================================================ */
let lang  = 'en';
const html = document.documentElement;

/* ============================================================
   TRANSLATE
   ============================================================ */
function applyLang(l) {
  const t = T[l];
  if (!t) return;
  lang = l;
  html.setAttribute('lang', l);
  html.setAttribute('dir',  l === 'ar' ? 'rtl' : 'ltr');
  document.querySelectorAll('[data-t]').forEach(el => {
    const v = t[el.dataset.t];
    if (v !== undefined) el.innerHTML = v.replace(/\n/g, '<br>');
  });
  // Live pricing labels (server-rendered from DB)
  document.querySelectorAll('.p-amt[data-price-' + l + ']').forEach(el => {
    const v = el.dataset['price' + l.charAt(0).toUpperCase() + l.slice(1)];
    if (v) el.textContent = v;
  });
  document.querySelectorAll('.p-per[data-per-' + l + ']').forEach(el => {
    const v = el.dataset['per' + l.charAt(0).toUpperCase() + l.slice(1)];
    if (v) el.textContent = v;
  });
  // mark active option
  document.querySelectorAll('.lang-opt').forEach(o =>
    o.classList.toggle('active', o.dataset.lang === l)
  );
  // update button label
  const labels = { en: '🌐 EN', ar: '🌐 AR', fr: '🌐 FR' };
  document.querySelectorAll('.lang-label').forEach(el => el.textContent = labels[l] || '🌐 EN');
  try { localStorage.setItem('uc_lang', l); } catch(e) {}
}

/* ============================================================
   LANGUAGE DROPDOWN
   ============================================================ */
function toggleLang(e) {
  e.stopPropagation();
  document.getElementById('lang-menu')?.classList.toggle('open');
}
function pickLang(l) {
  applyLang(l);
  document.getElementById('lang-menu')?.classList.remove('open');
}

/* ============================================================
   THEME
   ============================================================ */
function toggleTheme() {
  const dark = html.dataset.theme === 'dark';
  const next = dark ? 'light' : 'dark';
  html.dataset.theme = next;
  document.querySelectorAll('#theme-icon').forEach(i =>
    i.className = next === 'dark' ? 'ti ti-sun' : 'ti ti-moon'
  );
  try { localStorage.setItem('uc_theme', next); } catch(e) {}
}

/* ============================================================
   MOBILE MENU  (index.html)
   ============================================================ */
function toggleMob() { 
  const mobNav = document.getElementById('mob-nav');
  const mobOverlay = document.getElementById('mob-nav-overlay');
  const hamBtn = document.getElementById('ham-btn');
  if (!mobNav) return;
  
  const isOpen = mobNav.classList.toggle('open');
  if (mobOverlay) mobOverlay.classList.toggle('open', isOpen);
  
  // Update hamburger button aria attributes
  if (hamBtn) {
    hamBtn.setAttribute('aria-expanded', isOpen);
    hamBtn.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
  }
  
  // Prevent body scroll when menu is open
  if (isOpen) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
}

function closeMob() { 
  const mobNav = document.getElementById('mob-nav');
  const mobOverlay = document.getElementById('mob-nav-overlay');
  const hamBtn = document.getElementById('ham-btn');
  if (!mobNav) return;
  
  mobNav.classList.remove('open');
  if (mobOverlay) mobOverlay.classList.remove('open');
  document.body.style.overflow = '';
  
  if (hamBtn) {
    hamBtn.setAttribute('aria-expanded', 'false');
    hamBtn.setAttribute('aria-label', 'Open menu');
  }
}

// Close mobile menu on window resize if open
window.addEventListener('resize', () => {
  if (window.innerWidth > 980) {
    closeMob();
  }
});

/* ============================================================
   SCROLL TO SECTION  (index.html)
   ============================================================ */
function goTo(id) {
  closeMob();
  setTimeout(() => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' }), 40);
}

/* ============================================================
   ADMIN SIDEBAR  (admin.html)
   ============================================================ */
function toggleSidebar() { 
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!sidebar) return;
  
  const isOpen = sidebar.classList.toggle('open');
  
  // Show/hide overlay
  if (overlay) {
    overlay.style.display = isOpen ? 'block' : 'none';
  }
  
  // Prevent body scroll when sidebar is open on mobile
  if (window.innerWidth <= 900) {
    document.body.style.overflow = isOpen ? 'hidden' : '';
  }
}

function closeSidebar() { 
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!sidebar) return;
  
  sidebar.classList.remove('open');
  document.body.style.overflow = '';
  
  if (overlay) {
    overlay.style.display = 'none';
  }
}

// Close sidebar on window resize if needed
window.addEventListener('resize', () => {
  if (window.innerWidth > 900) {
    closeSidebar();
  }
});

/* ============================================================
   ADMIN PANEL SWITCH  (admin.html)
   ============================================================ */
function swPanel(name, el) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.getElementById('p-' + name)?.classList.add('active');
  document.querySelectorAll('.sb-item').forEach(i => i.classList.remove('active'));
  el?.classList.add('active');
  closeSidebar();
  window.scrollTo(0, 0);
}

/* ============================================================
   TOGGLE SWITCH  (admin settings)
   ============================================================ */
function tgl(el) { el.classList.toggle('on'); }

/* ============================================================
   MODAL
   ============================================================ */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

/* ============================================================
   PRICING PLANS - Dynamic Fetch (Optional Client-Side Update)
   ============================================================ */
async function fetchPlansFromAPI() {
  try {
    const response = await fetch('/api/plans.php');
    const data = await response.json();
    
    if (data.success && data.data) {
      return data.data;
    } else {
      console.error('Failed to fetch plans:', data.error);
      return null;
    }
  } catch (error) {
    console.error('Error fetching plans:', error);
    return null;
  }
}

/**
 * Update pricing section dynamically (if you want to refresh without page reload)
 * This is optional since the PHP already renders plans server-side
 */
async function updatePricingSection() {
  const plans = await fetchPlansFromAPI();
  if (!plans || plans.length === 0) return;

  const priceGrid = document.querySelector('.price-grid');
  if (!priceGrid) return;

  // Clear existing cards
  priceGrid.innerHTML = '';

  // Render each plan
  plans.forEach(plan => {
    const isFree = plan.price === 0;
    const isCustom = plan.price < 0;
    const isPopular = plan.is_popular;
    
    const priceLabel = isFree ? 'Free' : (isCustom ? 'Custom' : `${plan.price.toLocaleString()} ${plan.currency}`);
    const perLabel = isFree ? 'forever' : (isCustom ? 'contact us' : '/month · billed monthly');
    
    const card = document.createElement('div');
    card.className = 'price-card reveal' + (isPopular ? ' pop' : '');
    card.dataset.plan = plan.plan_code;
    
    let html = '';
    
    if (isPopular) {
      html += '<div class="pop-badge">Most Popular</div>';
    }
    
    html += `
      <div class="p-name">${escapeHtml(plan.name)}</div>
      <div class="p-desc">${escapeHtml(plan.description)}</div>
      <div class="p-amt${isPopular && !isFree && !isCustom ? ' grad' : ''}">${priceLabel}</div>
      <div class="p-per">${perLabel}</div>
      <ul class="p-feats">
    `;
    
    if (plan.features && Array.isArray(plan.features)) {
      plan.features.forEach(feature => {
        html += `
          <li class="p-feat">
            <i class="ti ti-check" aria-hidden="true"></i>
            <span>${escapeHtml(feature)}</span>
          </li>
        `;
      });
    }
    
    html += '</ul>';
    
    if (isFree) {
      html += '<button class="btn btn-md btn-ghost" style="width:100%" onclick="goTo(\'contact\')">Get Started Free</button>';
    } else if (isCustom) {
      html += '<button class="btn btn-md btn-ghost" style="width:100%" onclick="goTo(\'contact\')">Contact Sales</button>';
    } else {
      html += '<button class="btn btn-md btn-grad" style="width:100%" onclick="goTo(\'contact\')">Start Free Trial</button>';
    }
    
    card.innerHTML = html;
    priceGrid.appendChild(card);
  });
  
  console.log('✓ Pricing section updated with', plans.length, 'plans from API');
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  // Close lang menu when clicking anywhere outside it
  document.addEventListener('click', () =>
    document.getElementById('lang-menu')?.classList.remove('open')
  );

  // Theme
  let savedTheme = 'dark';
  try { savedTheme = localStorage.getItem('uc_theme') || 'dark'; } catch(e) {}
  html.dataset.theme = savedTheme;
  document.querySelectorAll('#theme-icon').forEach(i =>
    i.className = savedTheme === 'dark' ? 'ti ti-sun' : 'ti ti-moon'
  );

  // Language
  let savedLang = 'en';
  try { savedLang = localStorage.getItem('uc_lang') || 'en'; } catch(e) {}
  applyLang(savedLang);

  // Modal overlay click-outside
  document.querySelectorAll('.modal-overlay').forEach(ov =>
    ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('open'); })
  );
  
  // Optional: Dynamically update pricing if needed (uncomment to enable)
  // This is optional since PHP already renders plans server-side
  // updatePricingSection();
});
