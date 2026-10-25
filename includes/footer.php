
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand footer-brand-link"
               href="<?= e(url('index.php')) ?>">
                <span class="brand-mark">BIST</span>

                <span class="brand-copy">
                    <strong>Bengal Institute</strong>
                    <strong>of Science & Technology</strong>
                    <small>Purulia, West Bengal</small>
                </span>
            </a>

            <p>
                Institutional information, academic resources
                and announcements for the BIST community.
            </p>
        </div>

        <div class="footer-column">
            <h3>Explore</h3>
            <a href="<?= e(url('about.php')) ?>">About BIST</a>
            <a href="<?= e(url('academics.php')) ?>">Academics</a>
            <a href="<?= e(url('admissions.php')) ?>">Admissions</a>
            <a href="<?= e(url('faculty.php')) ?>">Faculty</a>
        </div>

        <div class="footer-column">
            <h3>Student Resources</h3>
            <a href="<?= e(url('student-hub.php')) ?>">Student Hub</a>
            <a href="<?= e(url('notices.php')) ?>">Notice Board</a>
            <a href="<?= e(url('events.php')) ?>">Events</a>
            <a href="<?= e(url('gallery.php')) ?>">Gallery</a>
        </div>

        <div class="footer-column">
            <h3>Contact</h3>
            <p>
                <i class="fa-solid fa-location-dot"></i>
                Dulmi, Tata Road, Purulia, West Bengal, 723102
            </p>
            <a href="<?= e(url('contact.php')) ?>">Contact the Institution</a>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <p>
                &copy; <?= date('Y') ?>
                Bengal Institute of Science & Technology.
            </p>

            <div>
                <a href="<?= e(url('privacy-policy.php')) ?>">Privacy Policy</a>
                <a href="<?= e(url('disclaimer.php')) ?>">Disclaimer</a>
            </div>
        </div>
    </div>
</footer>

<script src="<?= e(url('assets/js/main.js')) ?>"></script>
