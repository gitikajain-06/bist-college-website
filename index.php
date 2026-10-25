
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home';

$president = null;
$principal = null;
$notices = [];
$programmes = [];
$events = [];
$gallery = [];

try {
    $stmt = $pdo->prepare(
        "SELECT role, name, designation, message, photo_path
         FROM site_messages
         WHERE role = ? AND is_published = 1
         LIMIT 1"
    );

    $stmt->execute(['president']);
    $president = $stmt->fetch() ?: null;

    $stmt->execute(['principal']);
    $principal = $stmt->fetch() ?: null;

    $stmt = $pdo->query(
        "SELECT id, title, slug, category, summary,
                document_path, published_at, is_urgent
         FROM notices
         WHERE status = 'published'
           AND (expires_at IS NULL OR expires_at >= NOW())
         ORDER BY is_urgent DESC,
                  display_order ASC,
                  published_at DESC
         LIMIT 6"
    );
    $notices = $stmt->fetchAll();

    $stmt = $pdo->query(
        "SELECT id, name, code, description, duration
         FROM programmes
         WHERE is_published = 1
         ORDER BY name ASC
         LIMIT 6"
    );
    $programmes = $stmt->fetchAll();

    $stmt = $pdo->query(
        "SELECT title, slug, description, event_date,
                location, image_path
         FROM events
         WHERE status = 'published'
           AND event_date >= NOW()
         ORDER BY event_date ASC
         LIMIT 3"
    );
    $events = $stmt->fetchAll();

    $stmt = $pdo->query(
        "SELECT title, image_path, alt_text
         FROM gallery_images
         WHERE is_published = 1
         ORDER BY display_order ASC, id DESC
         LIMIT 6"
    );
    $gallery = $stmt->fetchAll();

} catch (PDOException $exception) {
    error_log('BIST homepage query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The homepage could not load. Please check the database setup.');
}

require __DIR__ . '/includes/header.php';
?>

<main>
    <!-- HERO -->
    <section class="hero">
        <div class="hero-overlay"></div>

        <div class="container hero-content">
            <span class="eyebrow hero-eyebrow">
                LEARN • GROW • ACHIEVE
            </span>

            <h1>
                Building Knowledge.<br>
                Inspiring <span>Futures.</span>
            </h1>

            <p>
                Welcome to Bengal Institute of Science & Technology,
                Purulia — a place to learn, develop skills and prepare
                for the opportunities of tomorrow.
            </p>

            <div class="hero-actions">
                <a class="btn btn-gold"
                   href="<?= e(url('academics.php')) ?>">
                    Explore Academics
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

                <a class="btn btn-outline-light"
                   href="<?= e(url('student-hub.php')) ?>">
                    <i class="fa-solid fa-book-open"></i>
                    Student Hub
                </a>
            </div>

            <div class="hero-location">
                <i class="fa-solid fa-location-dot"></i>
                Dulmi, Tata Road, Purulia, West Bengal
            </div>
        </div>

        <div class="hero-bottom-line"></div>
    </section>

    <!-- QUICK LINKS -->
    <section class="quick-links-section">
        <div class="container quick-links">
            <a class="quick-link" href="<?= e(url('notices.php')) ?>">
                <span class="quick-icon urgent-icon">
                    <i class="fa-solid fa-bullhorn"></i>
                </span>
                <span>
                    <strong>Notice Board</strong>
                    <small>Latest announcements</small>
                </span>
                <i class="fa-solid fa-arrow-up-right-from-square quick-arrow"></i>
            </a>

            <a class="quick-link" href="<?= e(url('student-hub.php')) ?>">
                <span class="quick-icon">
                    <i class="fa-solid fa-book-open"></i>
                </span>
                <span>
                    <strong>Student Hub</strong>
                    <small>Notes, syllabus and PYQs</small>
                </span>
                <i class="fa-solid fa-arrow-up-right-from-square quick-arrow"></i>
            </a>

            <a class="quick-link" href="<?= e(url('admissions.php')) ?>">
                <span class="quick-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </span>
                <span>
                    <strong>Admissions</strong>
                    <small>Programme information</small>
                </span>
                <i class="fa-solid fa-arrow-up-right-from-square quick-arrow"></i>
            </a>

            <a class="quick-link" href="<?= e(url('contact.php')) ?>">
                <span class="quick-icon">
                    <i class="fa-solid fa-headset"></i>
                </span>
                <span>
                    <strong>Get Help</strong>
                    <small>Contact the institution</small>
                </span>
                <i class="fa-solid fa-arrow-up-right-from-square quick-arrow"></i>
            </a>
        </div>
    </section>

    <!-- IMPORTANT NOTICES -->
    <section class="section notice-section" id="notices">
        <div class="container">
            <div class="section-heading heading-with-link">
                <div>
                    <span class="eyebrow">STAY INFORMED</span>
                    <h2>Important Notices</h2>
                    <p>
                        Official announcements and important academic updates.
                    </p>
                </div>

                <a class="text-link" href="<?= e(url('notices.php')) ?>">
                    View All Notices
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <?php if ($notices): ?>
                <div class="notice-list">
                    <?php foreach ($notices as $notice): ?>
                        <article class="notice-card">
                            <div class="notice-symbol">
                                <i class="fa-solid fa-file-lines"></i>
                            </div>

                            <div class="notice-content">
                                <div class="notice-meta">
                                    <?php if ((int) $notice['is_urgent'] === 1): ?>
                                        <span class="urgent-label">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                            Urgent
                                        </span>
                                    <?php endif; ?>

                                    <span class="notice-category">
                                        <?= e($notice['category']) ?>
                                    </span>

                                    <?php if ($notice['published_at']): ?>
                                        <span>
                                            <?= e(date(
                                                'd M Y',
                                                strtotime($notice['published_at'])
                                            )) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h3><?= e($notice['title']) ?></h3>

                                <?php if (!empty($notice['summary'])): ?>
                                    <p><?= e($notice['summary']) ?></p>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($notice['document_path'])): ?>
                                <a class="notice-download"
                                   href="<?= e(imageUrl($notice['document_path'])) ?>"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   aria-label="Open notice document">
                                    <i class="fa-solid fa-download"></i>
                                    <span>View</span>
                                </a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-regular fa-bell"></i>
                    <h3>No current notices</h3>
                    <p>Official announcements will appear here when published.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- LEADERSHIP MESSAGES -->
    <section class="section leadership-section">
        <div class="container">
            <div class="section-heading centered-heading">
                <span class="eyebrow">LEADERSHIP</span>
                <h2>A Message from Our Leadership</h2>
                <p>
                    A shared commitment to education, development and
                    the future of our students.
                </p>
            </div>

            <div class="leadership-grid">
                <?php
                $leaders = [
                    [
                        'label' => 'MESSAGE FROM THE PRESIDENT',
                        'data' => $president,
                        'fallback' => 'The President’s message will be published here after official approval.',
                        'class' => 'president-card'
                    ],
                    [
                        'label' => 'MESSAGE FROM THE PRINCIPAL',
                        'data' => $principal,
                        'fallback' => 'The Principal’s message will be published here after official approval.',
                        'class' => 'principal-card'
                    ]
                ];
                ?>

                <?php foreach ($leaders as $leader): ?>
                    <?php $person = $leader['data']; ?>

                    <article class="leadership-card <?= e($leader['class']) ?>">
                        <div class="leadership-label">
                            <i class="fa-solid fa-quote-left"></i>
                            <?= e($leader['label']) ?>
                        </div>

                        <div class="leader-profile">
                            <?php if ($person && !empty($person['photo_path'])): ?>
                                <img
                                    class="leader-photo"
                                    src="<?= e(imageUrl($person['photo_path'])) ?>"
                                    alt="<?= e($person['name']) ?>"
                                    loading="lazy">
                            <?php else: ?>
                                <div class="leader-photo leader-placeholder"
                                     aria-hidden="true">
                                    <i class="fa-solid fa-user-tie"></i>
                                </div>
                            <?php endif; ?>

                            <div>
                                <h3>
                                    <?= $person
                                        ? e($person['name'])
                                        : ($leader['class'] === 'president-card'
                                            ? 'President'
                                            : 'Principal') ?>
                                </h3>

                                <p>
                                    <?= $person
                                        ? e($person['designation'])
                                        : 'Bengal Institute of Science & Technology' ?>
                                </p>
                            </div>
                        </div>

                        <blockquote>
                            <?= e(
                                $person
                                    ? $person['message']
                                    : $leader['fallback']
                            ) ?>
                        </blockquote>

                        <?php if ($person): ?>
                            <a class="text-link"
                               href="<?= e(url('about.php')) ?>#leadership">
                                Read More
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ABOUT BIST -->
    <section class="section about-section">
        <div class="container about-grid">
            <div class="about-visual">
                <div class="about-image"></div>
                <div class="about-accent"></div>

                <div class="about-image-caption">
                    <i class="fa-solid fa-location-dot"></i>
                    <span>
                        <strong>BIST</strong>
                        <small>Purulia, West Bengal</small>
                    </span>
                </div>
            </div>

            <div class="about-copy">
                <span class="eyebrow">ABOUT THE INSTITUTION</span>
                <h2>Education with Purpose. Learning for Life.</h2>

                <p>
                    Bengal Institute of Science & Technology is located
                    at Dulmi, Tata Road, Purulia, West Bengal. Explore
                    the institution, its academic programmes and the
                    resources available to students.
                </p>

                <p>
                    Our website brings together institutional
                    information, academic updates, notices and learning
                    resources in one accessible place.
                </p>

                <a class="btn btn-primary" href="<?= e(url('about.php')) ?>">
                    Discover BIST
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- ACADEMICS -->
    <section class="section academics-section">
        <div class="container">
            <div class="section-heading heading-with-link">
                <div>
                    <span class="eyebrow">ACADEMIC OPPORTUNITIES</span>
                    <h2>Explore Our Programmes</h2>
                    <p>
                        Find programme information and explore your
                        academic options.
                    </p>
                </div>

                <a class="text-link" href="<?= e(url('academics.php')) ?>">
                    All Programmes
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <?php if ($programmes): ?>
                <div class="programme-grid">
                    <?php foreach ($programmes as $programme): ?>
                        <article class="programme-card">
                            <div class="programme-icon">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>

                            <span class="programme-code">
                                <?= e($programme['code']) ?>
                            </span>

                            <h3><?= e($programme['name']) ?></h3>

                            <?php if (!empty($programme['description'])): ?>
                                <p><?= e($programme['description']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($programme['duration'])): ?>
                                <div class="programme-duration">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= e($programme['duration']) ?>
                                </div>
                            <?php endif; ?>

                            <a class="text-link"
                               href="<?= e(url('academics.php')) ?>">
                                Explore Programme
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <h3>Programme information</h3>
                    <p>
                        Approved programme details will appear here
                        when they are published by the institution.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- STUDENT HUB -->
    <section class="section student-hub-section">
        <div class="container">
            <div class="student-hub-panel">
                <div class="student-hub-copy">
                    <span class="eyebrow eyebrow-light">YOUR ACADEMIC COMPANION</span>
                    <h2>Everything You Need for Your Studies.</h2>
                    <p>
                        Find study materials, syllabus documents,
                        previous-year questions, practical resources
                        and academic announcements in one place.
                    </p>

                    <a class="btn btn-gold"
                       href="<?= e(url('student-hub.php')) ?>">
                        Explore Student Hub
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="hub-resources">
                    <a href="<?= e(url('student-hub.php')) ?>">
                        <i class="fa-solid fa-book"></i>
                        <span>Syllabus Library</span>
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>

                    <a href="<?= e(url('student-hub.php')) ?>">
                        <i class="fa-solid fa-file-circle-question"></i>
                        <span>Previous-Year Papers</span>
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>

                    <a href="<?= e(url('student-hub.php')) ?>">
                        <i class="fa-solid fa-laptop-code"></i>
                        <span>Practicals and Viva</span>
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>

                    <a href="<?= e(url('student-hub.php')) ?>">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Academic Calendar</span>
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- UPCOMING EVENTS -->
    <section class="section events-section">
        <div class="container">
            <div class="section-heading heading-with-link">
                <div>
                    <span class="eyebrow">CAMPUS LIFE</span>
                    <h2>Upcoming Events</h2>
                    <p>
                        Discover upcoming academic and campus activities.
                    </p>
                </div>

                <a class="text-link" href="<?= e(url('events.php')) ?>">
                    View All Events
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <?php if ($events): ?>
                <div class="events-grid">
                    <?php foreach ($events as $event): ?>
                        <article class="event-card">
                            <?php if (!empty($event['image_path'])): ?>
                                <img
                                    class="event-image"
                                    src="<?= e(imageUrl($event['image_path'])) ?>"
                                    alt="<?= e($event['title']) ?>"
                                    loading="lazy">
                            <?php else: ?>
                                <div class="event-image event-image-placeholder">
                                    <i class="fa-solid fa-calendar-days"></i>
                                </div>
                            <?php endif; ?>

                            <div class="event-content">
                                <span class="event-date">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= e(date(
                                        'd M Y, h:i A',
                                        strtotime($event['event_date'])
                                    )) ?>
                                </span>

                                <h3><?= e($event['title']) ?></h3>

                                <?php if (!empty($event['description'])): ?>
                                    <p><?= e($event['description']) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($event['location'])): ?>
                                    <span class="event-location">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <?= e($event['location']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-regular fa-calendar"></i>
                    <h3>No upcoming events announced</h3>
                    <p>Published campus events will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CAMPUS GALLERY -->
    <section class="section gallery-section">
        <div class="container">
            <div class="section-heading heading-with-link">
                <div>
                    <span class="eyebrow">A GLIMPSE OF BIST</span>
                    <h2>Campus Gallery</h2>
                    <p>
                        Explore campus spaces and institutional activities.
                    </p>
                </div>

                <a class="text-link" href="<?= e(url('gallery.php')) ?>">
                    Explore Gallery
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <?php if ($gallery): ?>
                <div class="gallery-grid">
                    <?php foreach ($gallery as $photo): ?>
                        <a class="gallery-item"
                           href="<?= e(imageUrl($photo['image_path'])) ?>"
                           target="_blank"
                           rel="noopener noreferrer">
                            <img
                                src="<?= e(imageUrl($photo['image_path'])) ?>"
                                alt="<?= e($photo['alt_text']) ?>"
                                loading="lazy">
                            <span class="gallery-caption">
                                <?= e($photo['title']) ?>
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="gallery-empty">
                    Approved campus photographs will appear here.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ADMISSIONS CTA -->
    <section class="admissions-cta">
        <div class="container admissions-cta-inner">
            <div>
                <span class="eyebrow eyebrow-light">YOUR NEXT CHAPTER</span>
                <h2>Ready to Explore Your Future?</h2>
                <p>
                    Review the available programmes and official
                    admission information before applying.
                </p>
            </div>

            <a class="btn btn-gold" href="<?= e(url('admissions.php')) ?>">
                Admission Information
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
