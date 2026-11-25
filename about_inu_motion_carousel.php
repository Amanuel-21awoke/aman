<?php
/**
 * Full Animated Marquee Carousel under "About Injibara University"
 * - Full-bleed website width (.inu-about-motion-fullbleed-wrap)
 * - Continuous multi-directional marquee + zoom motion inside clipped frame
 * - Multilingual (EN / AM) headline with color cycle, text stroke, and multi-directional non-clipping motion
 */
$about_motion_slides = [
    [
        'src' => 'assets/slide_01_campus.jpg',
        'alt_en' => 'Injibara University Green Campus & Creative Potential',
        'alt_am' => 'የእንጅባራ ዩኒቨርሲቲ አረንጓዴ ካምፓስ እና የፈጠራ አቅም',
        'cap_en' => 'Injibara Campus — Green Environment & Creative Potential',
        'cap_am' => 'የእንጅባራ ካምፓስ — አረንጓዴ አካባቢ እና የፈጠራ አቅም'
    ],
    [
        'src' => 'assets/slide_02_campus.jpg',
        'alt_en' => 'Resilient Green Economy & Awi Highlands',
        'alt_am' => 'የአረንጓዴ ኢኮኖሚ እና የአዊ ደጋ ተፈጥሮ ሀብት',
        'cap_en' => 'Green Economy, Renewable Energy & Ecological Stewardship',
        'cap_am' => 'አረንጓዴ ኢኮኖሚ፣ ታዳሽ ኢነርጂ እና የተፈጥሮ ሀብት ጥበቃ'
    ],
    [
        'src' => 'assets/slide_03_campus.jpg',
        'alt_en' => 'Undergraduate and Postgraduate Academic Expansion',
        'alt_am' => 'የመጀመሪያ እና የድህረ-ምረቃ አካዳሚክ ፕሮግራሞች መስፋፋት',
        'cap_en' => '54 Undergraduate & 43 Postgraduate Programs + Short-Term Hub',
        'cap_am' => '54 የመጀመሪያ ዲግሪ እና 43 የድህረ-ምረቃ ፕሮግራሞች + የስልጠና ማዕከል'
    ],
    [
        'src' => 'assets/slide_04_campus.jpg',
        'alt_en' => 'AI, Cybersecurity & Digital Economy Research',
        'alt_am' => 'አርቲፊሻል ኢንተለጀንስ፣ ሳይበር ደህንነት እና ዲጂታል ኢኮኖሚ',
        'cap_en' => 'Innovation Labs, Applied Research & Digital Transformation',
        'cap_am' => 'የፈጠራ ላቦራቶሪዎች፣ ተግባራዊ ምርምር እና ዲጂታል ለውጥ'
    ],
    [
        'src' => 'assets/slide_05_campus.jpg',
        'alt_en' => 'Community Engagement & Institutional Partnerships',
        'alt_am' => 'የማህበረሰብ አገልግሎት እና የተቋማት አጋርነት',
        'cap_en' => 'Demand-Driven Community Service Across Awi Zone & Amhara',
        'cap_am' => 'በአዊ ዞን እና በአማራ ክልል ፍላጎትን መሰረት ያደረገ የማህበረሰብ አገልግሎት'
    ],
    [
        'src' => 'assets/slide_06_campus.jpg',
        'alt_en' => 'Competent and Entrepreneurial Graduates',
        'alt_am' => 'ብቁ እና ሥራ ፈጣሪ ተመራቂዎች',
        'cap_en' => 'Shaping Competent, Entrepreneurial & Responsible Graduates',
        'cap_am' => 'ብቁ፣ ሥራ ፈጣሪ እና ኃላፊነት የሚሰማቸው ተመራቂዎችን ማፍራት'
    ],
];
$is_am_motion = (($_SESSION['lang'] ?? 'en') === 'am');
?>
<div class="inu-about-motion-fullbleed-wrap">
    <div class="inu-about-motion-box" id="aboutInuMotionCarousel" aria-roledescription="marquee">
        <div class="inu-about-motion-track">
            <div class="inu-marquee-strip">
                <?php foreach ($about_motion_slides as $idx => $s): ?>
                    <div class="inu-marquee-item" data-slide-index="<?php echo $idx; ?>">
                        <img
                            src="<?php echo htmlspecialchars($s['src']); ?>"
                            alt="<?php echo htmlspecialchars($is_am_motion ? $s['alt_am'] : $s['alt_en']); ?>"
                            class="inu-about-motion-img"
                        >
                        <div class="inu-about-motion-caption">
                            <span><?php echo htmlspecialchars($is_am_motion ? $s['cap_am'] : $s['cap_en']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Seamless loop duplicate -->
                <?php foreach ($about_motion_slides as $idx => $s): ?>
                    <div class="inu-marquee-item" aria-hidden="true">
                        <img
                            src="<?php echo htmlspecialchars($s['src']); ?>"
                            alt=""
                            class="inu-about-motion-img"
                        >
                        <div class="inu-about-motion-caption">
                            <span><?php echo htmlspecialchars($is_am_motion ? $s['cap_am'] : $s['cap_en']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Multi-directional animated headline layer inside clipped box -->
        <div class="inu-motion-top-overlay" aria-hidden="false">
            <h4 class="inu-motion-headline">
                <?php if ($is_am_motion): ?>
                    እንኳን ወደ እንጅባራ ዩኒቨርሲቲ <br>የአጭር ጊዜ ስልጠና ማዕከል በደህና መጡ
                <?php else: ?>
                    WELCOME TO INJIBARA UNIVERSITY<br>SHORT TERM TRAINING HUB
                <?php endif; ?>
            </h4>
        </div>
    </div>
</div>

<style>
.inu-about-motion-fullbleed-wrap {
    width: 100vw;
    max-width: 100%;
    position: relative;
    left: 50%;
    right: 50%;
    margin-left: -50vw;
    margin-right: -50vw;
    margin-top: 0;
    overflow: hidden;
}
.inu-about-motion-box {
    position: relative;
    width: 100%;
    border-radius: 0;
    overflow: hidden;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.18);
    border-top: 2px solid rgba(251, 191, 36, 0.55);
    border-bottom: 2px solid rgba(251, 191, 36, 0.55);
    background: #0f172a;
}
.inu-about-motion-track {
    position: relative;
    width: 100%;
    height: 520px;
    overflow: hidden;
}
.inu-marquee-strip {
    display: flex;
    width: max-content;
    height: 100%;
    animation: inuMarqueeMultiDir 28s linear infinite;
}
.inu-about-motion-box:hover .inu-marquee-strip,
.inu-about-motion-box:hover .inu-motion-headline {
    animation-play-state: paused;
}

@keyframes inuMarqueeMultiDir {
    0%   { transform: translate3d(0, 0, 0); }
    25%  { transform: translate3d(-12.5%, -10px, 0); }
    50%  { transform: translate3d(-25%, 0, 0); }
    75%  { transform: translate3d(-37.5%, 10px, 0); }
    100% { transform: translate3d(-50%, 0, 0); }
}

.inu-marquee-item {
    position: relative;
    width: 600px;
    flex: 0 0 600px;
    height: 100%;
    overflow: hidden;
    border-right: 1px solid rgba(255, 255, 255, 0.12);
}
.inu-about-motion-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    animation: inuPhotoAllDirections 6.5s ease-in-out infinite alternate;
}

@keyframes inuPhotoAllDirections {
    0%   { transform: scale(1.02) translate3d(0, 0, 0); }
    50%  { transform: scale(1.08) translate3d(-12px, -8px, 0); }
    100% { transform: scale(1.11) translate3d(12px, 8px, 0); }
}

.inu-motion-top-overlay {
    position: absolute;
    inset: 0;
    z-index: 4;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 3.5rem;
    text-align: center;
    pointer-events: none;
    overflow: hidden;
    background: radial-gradient(circle at center, rgba(15, 23, 42, 0.42) 0%, rgba(15, 23, 42, 0.12) 70%, transparent 100%);
}

.inu-motion-headline {
    margin: 0;
    color: #ffffff;
    font-size: clamp(1.75rem, 3.6vw, 3.1rem);
    font-weight: 800;
    letter-spacing: 0.01em;
    -webkit-text-stroke: 2px #0f172a;
    paint-order: stroke fill;
    text-shadow: 0 4px 18px rgba(0, 0, 0, 0.55);
    will-change: transform, color;
    animation:
        inuHeadlineColorCycle 6s ease-in-out infinite,
        inuHeadlineAllDirections 7.5s ease-in-out infinite;
}

@keyframes inuHeadlineAllDirections {
    0%   { transform: translate3d(0, 0, 0) scale(1); }
    16%  { transform: translate3d(26px, 0, 0) scale(1.02); }
    33%  { transform: translate3d(18px, -16px, 0) scale(1.03); }
    50%  { transform: translate3d(-26px, -10px, 0) scale(1.02); }
    66%  { transform: translate3d(-24px, 16px, 0) scale(1.03); }
    83%  { transform: translate3d(20px, 16px, 0) scale(1.02); }
    100% { transform: translate3d(0, 0, 0) scale(1); }
}

@keyframes inuHeadlineColorCycle {
    0%   { color: #ffffff; text-shadow: 0 4px 18px rgba(0, 0, 0, 0.65); }
    25%  { color: #fbbf24; text-shadow: 0 4px 22px rgba(251, 191, 36, 0.45); }
    50%  { color: #6ee7b7; text-shadow: 0 4px 22px rgba(110, 231, 183, 0.45); }
    75%  { color: #7dd3fc; text-shadow: 0 4px 22px rgba(125, 211, 252, 0.45); }
    100% { color: #ffffff; text-shadow: 0 4px 18px rgba(0, 0, 0, 0.65); }
}

.inu-about-motion-caption {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 0.85rem 1.25rem;
    background: linear-gradient(transparent, rgba(15, 23, 42, 0.86));
    color: #ffffff;
    font-size: 0.92rem;
    font-weight: 600;
    text-align: center;
}

@media (max-width: 900px) {
    .inu-about-motion-track { height: 390px; }
    .inu-marquee-item { width: 420px; flex: 0 0 420px; }
}

@media (max-width: 600px) {
    .inu-about-motion-track { height: 300px; }
    .inu-marquee-item { width: 320px; flex: 0 0 320px; }
    .inu-about-motion-caption { font-size: 0.82rem; }
    .inu-motion-top-overlay { padding: 1rem 1.5rem; }
    @keyframes inuHeadlineAllDirections {
        0%   { transform: translate3d(0, 0, 0) scale(1); }
        25%  { transform: translate3d(12px, -8px, 0) scale(1.01); }
        50%  { transform: translate3d(-12px, -8px, 0) scale(1.01); }
        75%  { transform: translate3d(0, 8px, 0) scale(1.01); }
        100% { transform: translate3d(0, 0, 0) scale(1); }
    }
}
</style>