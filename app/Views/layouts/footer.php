<?php if($u): ?>
        </main>
        <footer class="app-footer">
            <span><?=e(branding()['system_name'])?> · <?=e(branding()['footer_text'])?></span>
            <span><?= has_role('BAYER') ? 'Consulta de información publicada' : 'Operación, control y trazabilidad' ?> · build 2026.10.01.5</span>
        </footer>
    </section>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?=asset_url('assets/js/app.js')?>"></script>
<script src="<?=asset_url('assets/js/searchable-selects.js')?>"></script>
<?php if($u): ?>
<script src="<?=asset_url('assets/js/soft-page-init.js')?>"></script>
<script src="<?=asset_url('assets/js/navigation.js')?>"></script>
<?php endif; ?>
</body>
</html>
