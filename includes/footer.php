<?php
if (!isset($lang)) {
    $lang_code = $_SESSION['lang'] ?? 'en';
    $lang_path = __DIR__ . "/lang_" . $lang_code . ".php";
    $lang = file_exists($lang_path) ? include($lang_path) : include(__DIR__ . "/lang_en.php");
}
$f_base = (strpos($_SERVER['PHP_SELF'], '/dashboard/') !== false) ? '../../' : '';
?>
</div><!-- /.hub-layout-row -->
<footer class="hub-footer" id="contact">
    <div class="footer-grid">
        <div class="footer-col">
            <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.65rem;">
                <img src="<?php echo htmlspecialchars($f_base . 'assets/inu_logo.jpg'); ?>" alt="Injibara University Logo" style="width:46px; height:46px; border-radius:50%;">
                <div>
                    <h3 style="color:#fbbf24; font-size:1.1rem; font-weight:700; margin:0;">
                        <?php echo htmlspecialchars($lang['hub_full_name']); ?>
                    </h3>
                    <p style="color:#cbd5e1; font-size:0.8rem; margin:0;"><?php echo htmlspecialchars($lang['footer_tagline']); ?></p>
                </div>
            </div>
    
        </div>

        <div class="footer-col">
            <h4 style="color:#fbbf24; font-size:0.95rem; margin-bottom:0.6rem;"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'አድራሻ እና ካምፓስ' : 'Contact & Campus'; ?></h4>
            <ul class="footer-links">
        
                <li><i class="bi bi-envelope-fill"></i> <a href="mailto:info@inu.edu.et">info@inu.edu.et</a> / <a href="mailto:training@inu.edu.et">training@inu.edu.et</a></li>
                <li><i class="bi bi-telephone-fill"></i> <a href="tel:0919968062">0919968062</a></li>
                <li><i class="bi bi-geo-alt-fill"></i> Injibara, Amhara Region, Ethiopia</li>
            </ul>
        </div>

        <div class="footer-col">
            <h4 style="color:#fbbf24; font-size:0.95rem; margin-bottom:0.6rem;"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'ፈጣን አገናኞች' : 'Quick Hub Links'; ?></h4>
            <ul class="footer-links">
                <li><i class="bi bi-chevron-right"></i> <a href="<?php echo $f_base; ?>index.php#programs"><?php echo htmlspecialchars($lang['programs']); ?></a></li>
                <li><i class="bi bi-chevron-right"></i> <a href="<?php echo $f_base; ?>verify_certificate.php"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የሰርተፍኬት ማረጋገጫ' : 'Certificate Verification'; ?></a></li>
                <li><i class="bi bi-chevron-right"></i> <a href="<?php echo $f_base; ?>sponsor_request.php"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የተቋማት የቡድን ስልጠና ጥያቄ' : 'Institutional Group Request'; ?></a></li>
                
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p style="font-size:0.86rem;">&copy; <?php echo date('Y'); ?> Injibara University • <?php echo htmlspecialchars($lang['rights_reserved']); ?> •           SYSTEM ADMIN: AMANUEL AWOKE </p>
        <p style="color:#94a3b8; font-size:0.78rem;"><?php echo (($_SESSION['lang'] ?? 'en') === 'am') ? 'የእንጅባራ ዩኒቨርሲቲ የአጭር ጊዜ ስልጠና ማዕከል (INU-STTH)' : 'Injibara University Short-Term Training Hub (INU-STTH)'; ?></p>
    </div>
</footer>
</body>
</html>
