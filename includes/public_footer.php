<?php
$footerYear = date('Y');
?>
<footer class="site-footer">
    <div class="container">
        <div class="row g-4 footer-main">
            <div class="col-lg-4">
                <a class="brand-lockup brand-lockup-light" href="<?= e(url('index.php')) ?>">
                    <img src="<?= e(asset('images/logo.svg')) ?>" alt="" width="40" height="40">
                    <span><strong>Civic<span>Watch</span></strong><small>Community action, tracked.</small></span>
                </a>
                <p class="mt-3 footer-copy">A transparent platform that connects residents with the departments responsible for keeping <?= e(COMMUNITY_NAME) ?> safe, clean and well maintained.</p>
                <div class="footer-social">
                    <a href="mailto:<?= e(CONTACT_EMAIL) ?>" aria-label="Email CivicWatch"><i class="fa-solid fa-envelope"></i></a>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', CONTACT_PHONE)) ?>" aria-label="Call CivicWatch"><i class="fa-solid fa-phone"></i></a>
                    <a href="<?= e(url('index.php#contact')) ?>" aria-label="Contact and location"><i class="fa-solid fa-location-dot"></i></a>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Platform</h6>
                <ul class="footer-links">
                    <li><a href="<?= e(url('index.php')) ?>">Home</a></li>
                    <li><a href="<?= e(url('index.php#how-it-works')) ?>">How it works</a></li>
                    <li><a href="<?= e(url('index.php#categories')) ?>">Categories</a></li>
                    <li><a href="<?= e(url('index.php#recent-reports')) ?>">Recent reports</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Account</h6>
                <ul class="footer-links">
                    <li><a href="<?= e(url('login.php')) ?>">Sign in</a></li>
                    <li><a href="<?= e(url('register.php')) ?>">Create account</a></li>
                    <li><a href="<?= e(url('login.php?role=staff')) ?>">Staff portal</a></li>
                    <li><a href="<?= e(url('login.php?role=admin')) ?>">Admin portal</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6>Get in touch</h6>
                <ul class="footer-contact">
                    <li><i class="fa-solid fa-location-dot"></i><span><?= e(CONTACT_ADDRESS) ?></span></li>
                    <li><i class="fa-solid fa-envelope"></i><a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a></li>
                    <li><i class="fa-solid fa-phone"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', CONTACT_PHONE)) ?>"><?= e(CONTACT_PHONE) ?></a></li>
                    <li><i class="fa-solid fa-clock"></i><span>Mon–Fri, 8:00 AM – 5:00 PM</span></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= e($footerYear) ?> <?= e(APP_NAME) ?>. Built for stronger communities.</span>
            <span>Developed by <a href="mailto:<?= e(DEVELOPER_EMAIL) ?>"><?= e(DEVELOPER_NAME) ?></a> &middot; <?= e(DEVELOPER_TITLE) ?></span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
