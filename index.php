<?php
require_once 'includes/functions.php';
require_once 'backend/db_connect.php';
include 'includes/header.php';
?><?php include __DIR__ . '/about_inu_motion_carousel.php'; ?>
<?php
$is_am = (($_SESSION['lang'] ?? 'en') === 'am');
$programs = $conn->query("
    SELECT p.*, u.name AS trainer_name
    FROM training_programs p
    LEFT JOIN users u ON p.trainer_id = u.id
    ORDER BY p.id ASC LIMIT 4
");
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3");
?>
<section class="hero">
    <div class="hero-glass-box anim-hero-box">
        <h1><?php echo htmlspecialchars($lang['hero_title_1']); ?></h1>
        <p><?php echo htmlspecialchars($lang['hero_subtitle_1']); ?></p>
        <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center;">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="register.php" class="btn btn-primary" style="padding: 0.85rem 1.8rem; font-size: 1rem;">
                    <i class="bi bi-person-plus-fill"></i> <?php echo htmlspecialchars($lang['join_us']); ?>
                </a>
                <a href="login.php" class="btn btn-outline" style="background: rgba(255,255,255,0.12); color: #fff; border-color: #fff; padding: 0.85rem 1.8rem;">
                    <i class="bi bi-box-arrow-in-right"></i> <?php echo htmlspecialchars($lang['login']); ?>
                </a>
            <?php else: ?>
                <a href="<?php echo get_dashboard_url($_SESSION['role']); ?>" class="btn btn-primary" style="padding: 0.85rem 1.8rem; font-size: 1rem;">
                    <i class="bi bi-speedometer2"></i> <?php echo htmlspecialchars($lang['go_dashboard']); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section" id="programs">
    <div class="section-title anim-fade-up">
        <h2><?php echo htmlspecialchars($lang['featured_programs']); ?></h2>
        <p><?php echo htmlspecialchars($lang['featured_programs_sub']); ?></p>
    </div>

    <div class="cards-grid">
        <?php if ($programs && $programs->num_rows > 0): ?>
            <?php while ($p = $programs->fetch_assoc()):
                $title = $is_am ? ($p['title_am'] ?: $p['title_en']) : $p['title_en'];
                $desc  = $is_am ? ($p['description_am'] ?: $p['description_en']) : $p['description_en'];
            ?>
                <div class="program-card anim-card-hover">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; margin-bottom:0.6rem;">
                            <span style="font-size:0.78rem; font-weight:700; color:var(--secondary-color);"><?php echo htmlspecialchars($p['code']); ?></span>
                            <?php echo status_badge($p['status']); ?>
                        </div>
                        <h3 style="color:var(--primary-color); font-size:1.15rem; margin-bottom:0.5rem;"><?php echo htmlspecialchars($title); ?></h3>
                        <p style="color:#475569; font-size:0.9rem; margin-bottom:0.9rem;"><?php echo htmlspecialchars($desc); ?></p>
                        <div class="program-meta">
                            <span><i class="bi bi-clock"></i> <?php echo (int)$p['duration_days']; ?> <?php echo $is_am ? 'ቀናት' : 'Days'; ?></span>
                            <span><i class="bi bi-people"></i> <?php echo (int)$p['capacity']; ?> <?php echo $is_am ? 'ቦታ' : 'Seats'; ?></span>
                            <span><i class="bi bi-cash-coin"></i> <?php echo number_format((float)$p['fee_birr'], 2); ?> ETB</span>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard/trainee/programs.php' : 'login.php'; ?>" class="btn btn-primary btn-sm" style="width:100%;">
                            <?php echo htmlspecialchars($lang['enroll_now']); ?>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</section>

<section class="section" id="announcements" style="padding-top:0;">
    <div class="section-title anim-fade-up">
        <h2><?php echo htmlspecialchars($lang['latest_announcements']); ?></h2>
        <p><?php echo htmlspecialchars($lang['announcements_sub']); ?></p>
    </div>
    <div class="cards-grid">
        <?php if ($announcements && $announcements->num_rows > 0): ?>
            <?php while ($a = $announcements->fetch_assoc()):
                $a_title = $is_am ? ($a['title_am'] ?: $a['title_en']) : $a['title_en'];
                $a_body  = $is_am ? ($a['body_am'] ?: $a['body_en']) : $a['body_en'];
            ?>
                <div class="card-box anim-card-hover">
                    <div style="font-size:0.78rem; color:#64748b; margin-bottom:0.4rem;">
                        <i class="bi bi-calendar-event"></i> <?php echo htmlspecialchars(date('M d, Y', strtotime($a['created_at']))); ?>
                    </div>
                    <h3 style="color:var(--primary-color); font-size:1.1rem; margin-bottom:0.5rem;"><?php echo htmlspecialchars($a_title); ?></h3>
                    <p style="color:#475569; font-size:0.9rem;"><?php echo htmlspecialchars($a_body); ?></p>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</section>

<section class="section" id="about-inu" style="padding-top:1.5rem;">
    <div class="section-title anim-fade-up">
        
        <h2><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ስለ እንጅባራ ዩኒቨርሲቲ' : 'About Injibara University'; ?></h2>
        <p><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ኦፊሴላዊ መነሻ፣ መገኛ፣ የአረንጓዴ ኢኮኖሚ ራዕይ እና የተቋሙ አጠቃላይ መግለጫ' : 'Official background, location, green economy vision, and institutional profile'; ?></p>
    </div>
    <div class="card-box anim-card-hover" style="line-height:1.75; color:#1e293b; font-size:0.95rem;">
        <p style="margin-bottom:1rem;">
            <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ኢትዮጵያ ትምህርት ድህነትን ለማጥፋት መሳሪያ ነው በሚል መሠረታዊ እሳቤ ከፍተኛ ትምህርትን ለማስፋፋት ወስናለች። ይህንን መሠረታዊ መርህ በመገንዘብ በሁለተኛው የዕድገትና ትራንስፎርሜሽን ዕቅድ (GTP II) 11 አዳዲስ ዩኒቨርሲቲዎች የተቋቋሙ ሲሆን፣ እንጅባራ ዩኒቨርሲቲም ከእነዚህ ዩኒቨርሲቲዎች አንዱ ሆኖ ተመሥርቷል። የዩኒቨርሲቲው የመሠረት ድንጋይ የካቲት 8 ቀን 2015 በኢትዮጵያ ፌዴራላዊ ዲሞክራሲያዊ ሪፐብሊክ ምክትል ጠቅላይ ሚኒስትር ክቡር አቶ ደመቀ መኮንን በተገኙበት ተቀምጧል። በመቀጠል ህዳር 4 ቀን 2015 በሚኒስትሮች ምክር ቤት በይፋ ተቋቁሟል።' : 'Ethiopia opted to expand higher education, on the premise that education is a weapon to eradicate poverty. In recognition to this fundamental principle, 11 new universities have been established in the second Growth and Transformation Plan (GTP II). Injibara University was established as one of these universities. The cornerstone of the university was laid down on the 8th February 2015 in the presence of His Excellency Demeke Mekonnen, Deputy Prime Minister of the Federal Democratic Republic of Ethiopia. It then officially established by the Council of Ministers on 4th November 2015.'; ?>
        </p>
        <p style="margin-bottom:1rem;">
            <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'እንጅባራ ዩኒቨርሲቲ በአማራ ብሔራዊ ክልላዊ መንግሥት ካሉት 10 አስተዳደራዊ ዞኖች አንዱ በሆነው በአዊ ብሔረሰብ አስተዳደር ዞን ይገኛል። ዞኑ በምዕራብ በኩል በመተከል ዞን፣ በሰሜን በኩል በሰሜን ጎንደር ዞን እና በምሥራቅ በኩል በምዕራብ ጎጃም ዞን ይዋሰናል። የዩኒቨርሲቲው ስም የተወሰደው ከአዊ ዞን አስተዳደራዊ ማዕከል ከተማ ስም ነው። የአዊ ሕዝብ ከአካባቢው ሥነ-ምህዳር ጋር የተጣጣመ ባህላዊ የመሬት አያያዝ ሥርዓትን በመከተል የአፈር ለምነትን እንዲጠብቅ እና የአፈር መሸርሸርን እንዲቀንስ አስችሎታል። አካባቢው በክልሉ ካሉ እጅግ ለም እና አምራች አካባቢዎች አንዱ ሆኖ ይታወቃል።' : 'Injibara University is situated in Awi Zone which is one of the 10 administrative zones of Amhara National Regional State. The zone is bordered on the west by Metekel Zone, on the north by North Gondar Zone and on the east by West Gojam. The name of the university has been picked from the name of the administrative town center of Awi Zone. The people of Awi have traditionally practiced a land-management system which is well adapted to the local ecology that enables them to sustain fertility and minimize erosion of the soil. The area is recognized as one of the most productive areas in the region.'; ?>
        </p>
        <p style="margin-bottom:1rem;">
            <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'እንጅባራ ዩኒቨርሲቲ የኢትዮጵያ መንግሥትን እና ከዚያም ያለፉ የአየር ንብረት ለውጥን የሚቋቋሙ የአረንጓዴ ኢኮኖሚ ተነሳሽነቶችን የሚመልሱ ብቁ ተመራቂዎችን ማፍራትን ዓላማው አድርጓል። አረንጓዴ ካምፓስ፣ ኢነርጂ እና ሥርዓተ-ትምህርት የዩኒቨርሲቲው መለያ ይሆናሉ። ሥርዓተ-ትምህርቶቹ የአረንጓዴ ኢኮኖሚ ተነሳሽነቶችን እና የሥራ ፈጠራ ልማትን ለመደገፍ የተቃኙ ይሆናሉ። ዩኒቨርሲቲው ከስድስት ወራት በላይ የሚቆይ ከፍተኛ ዝናብ፣ ለም አፈር፣ ቀጣይነት ያለው የውሃ አቅርቦት እና አረንጓዴ ተራራዎች ባሉት አካባቢ በጥሩ ሁኔታ የተቀመጠ ነው። ስለዚህ የመማር-ማስተማር፣ የምርምር እና የማህበረሰብ አገልግሎት ሥራዎችን ከተፈጥሮ ሀብት ጥበቃ እና ከአረንጓዴ ተነሳሽነቶች ማስፋፋት ጋር ማቀናጀት ከፍተኛ ጠቀሜታ አለው።' : 'Injibara University intends to produce competent graduates which can respond to resilient green economy initiatives of the Government of Ethiopia and beyond. The green campus, energy and curriculum will be the brand of the university. The curriculums shall be oriented to support the green economy initiatives and the entrepreneurial development. The university is well situated and endowed with high rain fall that extends for more than six months, fertile soil, continuous water supply and green mountains. Hence, integrating the learning-teaching, research and community service activities with the natural resource conservation and advancement of green initiatives is paramount importance.'; ?>
        </p>
        <p style="margin-bottom:0;">
            <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ዩኒቨርሲቲው በአምስት ዓመት ጊዜ ውስጥ በአካዳሚክ ፕሮግራሞች መስፋፋት፣ በምርምር፣ በማህበረሰብ ተሳትፎ፣ በአስተዳደር ጉዳዮች፣ በአጋርነት እና በአረንጓዴ ልማት ረገድ ከፍተኛ ስኬቶችን አስመዝግቧል። በ2021/22 የትምህርት ዘመን ዩኒቨርሲቲው በስድስት ኮሌጆች እና በአንድ ትምህርት ቤት ውስጥ በግምት 15,000 ተማሪዎችን እና 460 የአካዳሚክ ሰራተኞችን በመያዝ የመማር ማስተማር ሂደቱን ሲያከናውን ቆይቷል። በአሁኑ ወቅት ዩኒቨርሲቲው 54 የመጀመሪያ ዲግሪ እና 43 የድህረ-ምረቃ ፕሮግራሞች አሉት።' : 'The university has, with its five years period, realized tremendous achievements in academic programs expansion, research, community engagement, administrative issues, partnership and greenery. In the 2021/22 academic year, the university has been running the teaching and learning process having about 15000 students and 460 academic staff members with in six colleges and one school. Currently, the university has 54 undergraduate and 43 postgraduate programs.'; ?>
        </p>
    </div>
</section>

<section class="section" id="inu-portal-info" style="padding-top:1.5rem;">
    <div class="section-title anim-fade-up">
        <h2><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'እንጅባራ ዩኒቨርሲቲ — ኦፊሴላዊ መረጃ እና የምስል ማዕከል' : 'Injibara University — Official Information &amp; Photo Gallery'; ?></h2>
        <p><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ከሚከተለው ጋር የተጣጣሙ ዝርዝር መረጃዎች፣ የተቋሙ መሰረቶች እና ምስሎች፦' : 'Comprehensive details, institutional pillars, and visual highlights aligned with'; ?></p>
    </div>

    <div class="cards-grid" style="margin-bottom:2rem;">
        <div class="card-box anim-card-hover" style="padding:0; overflow:hidden;">
            <img src="assets/slide_05_campus.jpg" alt="Injibara University Campus" style="width:100%; height:210px; object-fit:cover; display:block;">
            <div style="padding:1.15rem;">
                <h3 style="color:var(--primary-color); font-size:1.05rem; margin-bottom:0.4rem;"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የእንጅባራ ዩኒቨርሲቲ ዋና ካምፓስ' : 'Injibara University Main Campus'; ?></h3>
                <p style="color:#475569; font-size:0.88rem;">
                    <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'በአማራ ክልል በአዊ ብሔረሰብ አስተዳደር እንጅባራ ከተማ የሚገኝ ሲሆን ለከፍተኛ ትምህርት፣ ለምርምር እና ለማህበረሰብ ልማት የተቋቋመ ነው።' : 'Located in Injibara, Awi Nationality Zone, Amhara Region, Ethiopia — dedicated to higher learning, research, and community development'; ?>
                </p>
            </div>
        </div>

        <div class="card-box anim-card-hover" style="padding:0; overflow:hidden;">
            <img src="assets/inu_labs.jpg" alt="ICT and Training Laboratories" style="width:100%; height:210px; object-fit:cover; display:block;">
            <div style="padding:1.15rem;">
                <h3 style="color:var(--primary-color); font-size:1.05rem; margin-bottom:0.4rem;"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ተግባራዊ ላቦራቶሪዎች፣ ICT እና ምርምር' : 'Practical Labs, ICT & Research'; ?></h3>
                <p style="color:#475569; font-size:0.88rem;">
                    <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ለባለሙያዎች፣ ለተማሪዎች እና ለሰራተኞች የተዘጋጀ ተግባራዊ የዳታ ትንተና፣ ዲጂታል ትምህርት፣ የICT መሳሪያዎች እና የተግባር ምርምር ስልጠና።' : 'Hands-on training in data analytics, digital education, software/ICT tools, and applied research tailored for professionals, students, and staff.'; ?>
                </p>
            </div>
        </div>

        <div class="card-box anim-card-hover" style="padding:0; overflow:hidden;">
            <img src="assets/inu_community.jpg" alt="Community Engagement and Partnerships" style="width:100%; height:210px; object-fit:cover; display:block;">
            <div style="padding:1.15rem;">
                <h3 style="color:var(--primary-color); font-size:1.05rem; margin-bottom:0.4rem;"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የማህበረሰብ አገልግሎት እና የተቋማት ትስስር' : 'Community Service & Institutional Links'; ?></h3>
                <p style="color:#475569; font-size:0.88rem;">
                    <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተረጋገጡ የአጭር ጊዜ የክህሎት ስልጠናዎችን እና የማህበረሰብ አገልግሎትን ለማድረስ ከዞን/ወረዳ ጽ/ቤቶች፣ ከTVET ተቋማት፣ ከትምህርት ቤቶች እና ከኢንተርፕራይዞች ጋር በቅንጅት ይሰራል።' : 'Partnering with zonal/woreda offices, TVET institutions, schools, and enterprises to deliver certified short-term skill programs and public outreach.'; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="cards-grid">
        <div class="card-box anim-card-hover">
            <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
                <img src="assets/inu_logo.jpg" alt="INU Logo" style="width:44px; height:44px; border-radius:50%;">
                <h3 style="color:var(--primary-color); font-size:1.08rem; margin:0;"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የዩኒቨርሲቲው ተልዕኮ እና ራዕይ' : 'University  Mission &amp; Vision'; ?></h3>
            </div>

            <div style="background:#f8fafc; border-left:3px solid var(--primary-color); padding:0.65rem 0.8rem; border-radius:6px; margin-bottom:0.65rem;">
                <div style="font-weight:700; color:var(--primary-color); font-size:0.92rem; margin-bottom:0.2rem;">
                    <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? '<h1>ተልዕኮ (Mission)</h1>' : '<h1>Mission</h1>'; ?>
                </div>
                <p style="color:#334155; font-size:0.86rem; line-height:1.55; margin:0;">
                    <?php echo (($_SESSION['lang'] ?? 'en') === 'am')
                        ? 'ጥራት ያለው፣ አግባብነት ያለው እና ፍትሃዊ ትምህርትና ስልጠና በመስጠት ብቁ፣ ሥራ ፈጣሪ እና ኃላፊነት የሚሰማቸው ተመራቂዎችን ማፍራት፤ ችግር ፈቺ ጥናትና ምርምሮችን ማካሄድ፣ እንዲሁም በፍላጎት ላይ የተመሠረቱ የማህበረሰብ አገልግሎቶችን መስጠት።'
                        : 'Producing competent, entrepreneurial and responsible graduates by providing quality, relevant and equitable education and training; conducting problem-solving researches, and providing demand-driven community services.'; ?>
                </p>
            </div>

            <div style="background:#f0fdf4; border-left:3px solid #16a34a; padding:0.65rem 0.8rem; border-radius:6px; margin-bottom:0.7rem;">
                <div style="font-weight:700; color:#166534; font-size:0.92rem; margin-bottom:0.2rem;">
                    <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? '<h1>ራዕይ (Vision)</h1>' : '<h1>Vision</h1>'; ?>
                </div>
                <p style="color:#1e293b; font-size:0.86rem; font-weight:600; line-height:1.5; margin:0;">
                    <?php echo (($_SESSION['lang'] ?? 'en') === 'am')
                        ? 'በ2030 በኢትዮጵያ ካሉት ሦስት ምርጥ አጠቃላይ ዩኒቨርሲቲዎች አንዱ መሆን!'
                        : 'To be one of the three best comprehensive Universities in Ethiopia in 2030!'; ?>
                </p>
            </div>

            <p style="color:#475569; font-size:0.86rem; margin-bottom:0.5rem;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአጭር ጊዜ ስልጠና ማዕከሉም የዩኒቨርሲቲውን መምህራን እና ላቦራቶሪዎች ከተግባራዊ የሰው ኃይል አቅም ግንባታ ጋር ያገናኛል።' : 'The Short-Term Training Hub connects university faculty and labs with practical workforce capacity building.'; ?>
            </p>
        
        </div>

        <div class="card-box anim-card-hover">
            <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
                <img src="assets/inu_logo.jpg" alt="INU Logo" style="width:44px; height:44px; border-radius:50%;">
            <h3 style="color:var(--primary-color); font-size:1.08rem; margin-bottom:0.65rem;">
                <!-- <i class="bi bi-award"></i> -->
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'እሴቶች እና መፈክር' : 'Values &amp; Motto'; ?>
            </h3>
            </div>

            <div style="background:#f8fafc; border-left:3px solid #0d9488; padding:0.65rem 0.8rem; border-radius:6px; margin-bottom:0.7rem;">
                <div style="font-weight:700; color:var(--primary-color); font-size:0.88rem; margin-bottom:0.35rem;">
                    <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? '<h1>እሴቶች (Values)</h1>' : '<h1>Values</h1>'; ?>
                </div>
                <ul style="margin:0; padding-left:1.1rem; font-size:0.84rem; color:#334155; line-height:1.5;">
                    <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ተገልጋይን ማስቀደም' : 'Customer First'; ?></li>
                    <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአካዳሚክ ነጻነት' : 'Academic Freedom'; ?></li>
                    <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ለሀገር በቀል ዕውቀት ዋጋ መስጠት' : 'Value for Indigenous Knowledge'; ?></li>
                    <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ሥራ ፈጠራን ማጎልበት' : 'Empowering Entrepreneurship'; ?></li>
                    <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ብዝሃነት እና መቻቻል' : 'Diversity and Tolerance'; ?></li>
                    <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የቡድን ሥራ እና ትስስር' : 'Team work and Networking'; ?></li>
                    <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የሥነ-ምግባር ቁርጠኝነት' : 'Ethical Commitment'; ?></li>
                    <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ዘላቂነት' : 'Sustainability'; ?></li>
                    <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'በሁሉም ዘርፍ ጥራት' : 'Quality in All'; ?></li>
                </ul>
            </div>

            <p style="font-size:0.86rem; color:#0f172a; font-weight:700; margin:0;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am')
                    ? '<h1> መፈክር </h1><h4> የፈጠራ አቅምዎን ይመርምሩ! </h4>'
                    : '<h1> Motto </h1> <h4> Explore Your Creative Potentials! </h4>'; ?>
            </p>
        </div>
<div class="card-box anim-card-hover">
    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
                <img src="assets/inu_logo.jpg" alt="INU Logo" style="width:44px; height:44px; border-radius:50%;">
    <h3 style="color:var(--primary-color); font-size:1.08rem; margin-bottom:0.65rem; display:flex; align-items:center; gap:0.45rem;">
        <!-- <i class="bi bi-award"></i> -->
        <span><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የልህቀት ማዕከላት' : 'Centers of Excellence'; ?></span>
    </h3>
            </div>

    <div style="background:#f8fafc; border-left:3px solid var(--primary-color); padding:0.75rem 0.9rem; border-radius:6px;">
        <h4 style="font-weight:700; color:var(--primary-color); font-size:0.92rem; margin:0 0 0.45rem 0;">
            <?php echo (($_SESSION['lang'] ?? 'en') === 'am')
                ? 'የእንጅባራ ዩኒቨርሲቲ የልህቀት ማዕከላት (Center of Excellence)'
                : 'Injibara University Center of Excellence'; ?>
        </h4>

        <ul style="margin:0; padding-left:1.2rem; font-size:0.88rem; color:#334155; line-height:1.6;">
            <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? '1. ግብርና እና አረንጓዴ ልማት' : '1. Agriculture and Green Development'; ?></li>
            <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? '2. የህብረተሰብ ጤና' : '2. Public Health'; ?></li>
            <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? '3. የአዊ (አገው) ቋንቋ፣ ባህል እና ታሪክ' : '3. Agaw Language, Culture and History'; ?></li>
            <li><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? '4. ቱሪዝም እና የሥራ ፈጠራ ልማት' : '4. Tourism and Entrepreneurship Development'; ?></li>
        </ul>
    </div>
</div>
</section>

<section class="section" id="leadership" style="padding-top:1.5rem;">
    <style>
        #leadership .leader-card {
            padding: 0 0 1.15rem 0 !important;
            overflow: hidden !important;
            border-radius: 14px !important;
        }
        #leadership .leader-card img {
            width: 100% !important;
            max-width: 100% !important;
            height: 270px !important;
            object-fit: cover !important;
            object-position: top center !important;
            border-radius: 10px !important;
            display: block !important;
            margin: 0 0 0.85rem 0 !important;
        }
        #leadership .leader-card h3,
        #leadership .leader-card p {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
    </style>

    <div class="section-title anim-fade-up">
        <h2><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የእንጅባራ ዩኒቨርሲቲ አመራር' : 'Injibara University Leadership'; ?></h2>
        <p><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአካዳሚክ ልህቀትን፣ ስልጠናን እና የማህበረሰብ ተሳትፎን የሚደግፉ ዋና ዋና የአመራር ቢሮዎች' : 'Key executive offices supporting academic excellence, training, and community engagement'; ?></p>
    </div>

    <div class="leadership-grid">
        <div class="leader-card anim-card-hover">
            <img src="assets/leaders/gaedachew.jpg" alt="University President">
            <h3 style="color:var(--primary-color); font-size:1.05rem; margin-bottom:0.25rem;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ጋርዳቸው ወርቁ (ዶ/ር)' : 'Gardachew Worku (PhD)'; ?>
            </h3>
            <p style="font-size:0.86rem; color:#475569; margin:0.25rem 0;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአካውንቲንግ እና ፋይናንስ ተባባሪ ፕሮፌሰር' : 'Associate Professor of Accounting and Finance'; ?>
            </p>
            <p style="font-size:0.83rem; color:#0d9488; font-weight:600; margin:0.25rem 0 0 0;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የእንጅባራ ዩኒቨርሲቲ ፕሬዚዳንት' : 'President, Injibara University'; ?>
            </p>
        </div>

        <div class="leader-card anim-card-hover">
            <img src="assets/leaders/aemiro.jpg" alt="Academic Vice President">
            <h3 style="color:var(--primary-color); font-size:1.05rem; margin-bottom:0.25rem;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አእምሮ ታደሰ (ዶ/ር)' : 'Aemiro Tadesse (PhD)'; ?>
            </h3>
            <p style="font-size:0.86rem; color:#475569; margin:0.25rem 0;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የሳይኮሎጂ ረዳት ፕሮፌሰር' : 'Assistant Professor of Psychology'; ?>
            </p>
            <p style="font-size:0.83rem; color:#0d9488; font-weight:600; margin:0.25rem 0 0 0;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአካዳሚክ ጉዳዮች ምክትል ፕሬዚዳንት' : 'Academic Affairs Vice President'; ?>
            </p>
        </div>

        <div class="leader-card anim-card-hover">
            <img src="assets/leaders/wohabie.jpg" alt="Administration and Development VP">
            <h3 style="color:var(--primary-color); font-size:1.05rem; margin-bottom:0.25rem;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ውሃቤ ብርሃን (ዶ/ር)' : 'Wohabe Birhan (PhD)'; ?>
            </h3>
            <p style="font-size:0.86rem; color:#475569; margin:0.25rem 0;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተግባራዊ የዕድገት ሳይኮሎጂ ተባባሪ ፕሮፌሰር' : 'Associate Professor of Applied Developmental Psychology'; ?>
            </p>
            <p style="font-size:0.83rem; color:#0d9488; font-weight:600; margin:0.25rem 0 0 0;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የአስተዳደር እና ልማት ምክትል ፕሬዚዳንት' : 'Administrative and Development Vice President'; ?>
            </p>
        </div>

        <div class="leader-card anim-card-hover">
            <img src="assets/leaders/kindie.jpg" alt="Research and Community Engagement VP">
            <h3 style="color:var(--primary-color); font-size:1.05rem; margin-bottom:0.25rem;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ክንዴ ብርሃን (ዶ/ር)' : 'Kindie Birhan (PhD)'; ?>
            </h3>
            <p style="font-size:0.86rem; color:#475569; margin:0.25rem 0;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የትምህርት ረዳት ፕሮፌሰር' : 'Assistant Professor of Education'; ?>
            </p>
            <p style="font-size:0.83rem; color:#0d9488; font-weight:600; margin:0.25rem 0 0 0;">
                <?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የምርምር እና የማህበረሰብ አገልግሎት ምክትል ፕሬዚዳንት' : 'Research and Community Service Vice President'; ?>
            </p>
        </div>
    </div>
</section>
    <style>
        #leadership .leader-card {
            padding: 0 0 1.15rem 0 !important;
            overflow: hidden !important;
            border-radius: 14px !important;
        }
        #leadership .leader-card img {
            width: 100% !important;
            max-width: 100% !important;
            height: 270px !important;
            object-fit: cover !important;
            object-position: top center !important;
            border-radius: 1px !important;
            display: block !important;
            margin: 0 0 0.85rem 0 !important;
        }
        #leadership .leader-card h3,
        #leadership .leader-card p {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
    </style>
</section>

<section class="section" id="inu-animated-10" style="padding-top:1.5rem;">
    <style>
        .inu-anim10-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.15rem;
        }
        .inu-anim10-card {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
            transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
        }
        .inu-anim10-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 32px rgba(23, 102, 63, 0.18);
            border-color: #17663f;
        }
        .inu-anim10-svg {
            width: 100%;
            height: 150px;
            display: block;
        }
        @keyframes inuPulseSlow {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.08); opacity: 1; }
        }
        @keyframes inuFloatY {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        @keyframes inuRotateSlow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .svg-pulse { transform-box: fill-box; transform-origin: center; animation: inuPulseSlow 3.2s ease-in-out infinite; }
        .svg-float { transform-box: fill-box; transform-origin: center; animation: inuFloatY 3.6s ease-in-out infinite; }
        .svg-spin  { transform-box: fill-box; transform-origin: center; animation: inuRotateSlow 14s linear infinite; }
    </style>

    <div class="section-title anim-fade-up">
        <h2><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የማዕከሉ 10 ቁልፍ የስልጠና እና የልህቀት ዘርፎች' : '10 Core Training &amp; Excellence Pillars'; ?></h2>
        <p><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'በእንጅባራ ዩኒቨርሲቲ የአጭር ጊዜ ስልጠና ማዕከል የሚሰጡ ዋና ዋና ዘርፎች' : 'Interactive animated highlights of Injibara University Short-Term Training Hub programs'; ?></p>
    </div>

    <?php
    $is_am_flag = (($_SESSION['lang'] ?? 'en') === 'am');
    $anim_items = [
        ['c1' => '#0b3b24', 'c2' => '#17663f', 'en' => '1. ICT & Digital Skills', 'am' => '1. ICT እና ዲጂታል ክህሎት', 'sub_en' => 'Software, Networking & Cloud Labs', 'sub_am' => 'ሶፍትዌር፣ ኔትወርኪንግ እና ዲጂታል ላብ'],
        ['c1' => '#1e3a8a', 'c2' => '#0284c7', 'en' => '2. Data Science & Analytics', 'am' => '2. የዳታ ሳይንስ እና ትንተና', 'sub_en' => 'Statistical Modeling & BI Dashboards', 'sub_am' => 'የመረጃ ትንተና እና ዳሽቦርድ'],
        ['c1' => '#065f46', 'c2' => '#10b981', 'en' => '3. Green Economy & Forestry', 'am' => '3. አረንጓዴ ኢኮኖሚ እና ደን ልማት', 'sub_en' => 'Climate Resilience & Soil Conservation', 'sub_am' => 'የአየር ንብረት እና የተፈጥሮ ሀብት ጥበቃ'],
        ['c1' => '#7c2d12', 'c2' => '#d97706', 'en' => '4. Entrepreneurship & SME', 'am' => '4. ሥራ ፈጠራ እና ቢዝነስ', 'sub_en' => 'Business Incubation & Startup Strategy', 'sub_am' => 'የንግድ ዕቅድ እና የሥራ ፈጠራ ክህሎት'],
        ['c1' => '#312e81', 'c2' => '#4f46e5', 'en' => '5. Pedagogical Leadership', 'am' => '5. የመምህራን እና አመራር ስልጠና', 'sub_en' => 'Modern Teaching & Curriculum Design', 'sub_am' => 'ዘመናዊ የማስተማር ዘዴ እና አመራር'],
        ['c1' => '#134e4a', 'c2' => '#0d9488', 'en' => '6. Public Health & Safety', 'am' => '6. የህብረተሰብ ጤና እና ደህንነት', 'sub_en' => 'Community Health & Emergency Care', 'sub_am' => 'የማህበረሰብ ጤና አጠባበቅ ስልጠና'],
        ['c1' => '#14532d', 'c2' => '#65a30d', 'en' => '7. Modern Agribusiness', 'am' => '7. ዘመናዊ ግብርና እና አግሪቢዝነስ', 'sub_en' => 'Awi Highland Productivity & Value Chain', 'sub_am' => 'የግብርና ምርታማነት ማሳደጊያ'],
        ['c1' => '#1e293b', 'c2' => '#334155', 'en' => '8. Financial Management', 'am' => '8. የፋይናንስ እና ኦዲት አስተዳደር', 'sub_en' => 'Public Finance, Accounting & Auditing', 'sub_am' => 'የፋይናንስ ቁጥጥር እና ሂሳብ አያያዝ'],
        ['c1' => '#581c87', 'c2' => '#7e22ce', 'en' => '9. Applied Research & Grants', 'am' => '9. የተግባር ምርምር እና ፕሮጀክት', 'sub_en' => 'Proposal Writing & Impact Assessment', 'sub_am' => 'የምርምር ፕሮፖዛል እና ግምገማ'],
        ['c1' => '#831843', 'c2' => '#be185d', 'en' => '10. Certified E-Learning (LMS)', 'am' => '10. ዲጂታል ትምህርት እና ሰርተፍኬት', 'sub_en' => 'Online Exams & Verified Certificates', 'sub_am' => 'ኦንላይን ፈተና እና የተረጋገጠ ሰርተፍኬት'],
    ];
    ?>

    <div class="inu-anim10-grid">
        <?php foreach ($anim_items as $idx => $item): ?>
            <div class="inu-anim10-card">
                <svg class="inu-anim10-svg" viewBox="0 0 320 150" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?php echo htmlspecialchars($item['en']); ?>">
                    <defs>
                        <linearGradient id="animGrad<?php echo $idx; ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="<?php echo $item['c1']; ?>"/>
                            <stop offset="100%" stop-color="<?php echo $item['c2']; ?>"/>
                        </linearGradient>
                    </defs>
                    <rect width="320" height="150" fill="url(#animGrad<?php echo $idx; ?>)"/>
                    <circle cx="265" cy="35" r="36" fill="none" stroke="rgba(255,255,255,0.22)" stroke-width="2" stroke-dasharray="6 6" class="svg-spin"/>
                    <circle cx="52" cy="122" r="28" fill="rgba(251,191,36,0.18)" class="svg-pulse"/>
                    <g class="svg-float">
                        <rect x="24" y="26" width="54" height="54" rx="14" fill="rgba(255,255,255,0.16)" stroke="rgba(255,255,255,0.4)" stroke-width="1.5"/>
                        <text x="51" y="60" text-anchor="middle" fill="#fbbf24" font-family="Poppins, sans-serif" font-size="20" font-weight="800"><?php echo $idx + 1; ?></text>
                        <rect x="92" y="34" width="165" height="10" rx="5" fill="rgba(255,255,255,0.85)"/>
                        <rect x="92" y="54" width="120" height="8" rx="4" fill="rgba(251,191,36,0.85)"/>
                        <rect x="92" y="72" width="140" height="7" rx="3.5" fill="rgba(255,255,255,0.45)"/>
                    </g>
                    <path d="M0,128 Q160,104 320,128 L320,150 L0,150 Z" fill="rgba(0,0,0,0.22)"/>
                </svg>
                <div style="padding:0.95rem 1rem;">
                    <h3 style="color:var(--primary-color); font-size:0.98rem; margin-bottom:0.3rem;">
                        <?php echo htmlspecialchars($is_am_flag ? $item['am'] : $item['en']); ?>
                    </h3>
                    <p style="color:#475569; font-size:0.83rem; margin:0;">
                        <?php echo htmlspecialchars($is_am_flag ? $item['sub_am'] : $item['sub_en']); ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>