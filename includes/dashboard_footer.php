        </main>
        <footer class="app-footer">
            <span>&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?> · <?= e(APP_TAGLINE) ?></span>
            <span>Developed by <a href="mailto:<?= e(DEVELOPER_EMAIL) ?>"><?= e(DEVELOPER_NAME) ?></a> · <?= e(DEVELOPER_TITLE) ?> · v<?= e(APP_VERSION) ?></span>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
