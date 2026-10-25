
<header class="site-header">
    <div class="topbar">
        <div class="container topbar-inner">
            <span>
                <i class="fa-solid fa-location-dot"></i>
                Dulmi, Tata Road, Purulia, West Bengal
            </span>

            <span class="topbar-note">
                Welcome to Bengal Institute of Science & Technology
            </span>
        </div>
    </div>

    <div class="container navbar">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark">BIST</span>

            <span class="brand-copy">
                <strong>Bengal Institute</strong>
                <strong>of Science & Technology</strong>
                <small>Purulia, West Bengal</small>
            </span>
        </a>

        <button class="menu-toggle"
                type="button"
                aria-label="Open navigation menu"
                aria-expanded="false"
                aria-controls="main-navigation">
            <i class="fa-solid fa-bars"></i>
        </button>

        <nav class="main-navigation" id="main-navigation">
            <a href="<?= e(url('index.php')) ?>">Home</a>
            <a href="<?= e(url('about.php')) ?>">About</a>
            <a href="<?= e(url('academics.php')) ?>">Academics</a>
            <a href="<?= e(url('admissions.php')) ?>">Admissions</a>
            <a href="<?= e(url('student-hub.php')) ?>">Student Hub</a>
            <a href="<?= e(url('notices.php')) ?>">Notices</a>
            <a href="<?= e(url('contact.php')) ?>">Contact</a>

            <a class="nav-apply" href="<?= e(url('admissions.php')) ?>">
                Apply Now <i class="fa-solid fa-arrow-right"></i>
            </a>
        </nav>
    </div>
</header>
