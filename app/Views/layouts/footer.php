<?php if($u): ?>
        </main>
        <footer class="app-footer">
            <span><?=e(branding()['system_name'])?> · <?=e(branding()['footer_text'])?></span>
            <span><?= has_role('BAYER') ? 'Consulta de información publicada' : 'Operación, control y trazabilidad' ?> · build 2026.09.23.2</span>
        </footer>
    </section>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $jsVersion = @filemtime(base_path('public/assets/js/app.js')) ?: time(); ?>
<script src="<?=url('/assets/js/app.js?v='.$jsVersion)?>"></script>
</body>
</html>
