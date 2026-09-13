<?php if($u): ?>
        </main>
        <footer class="app-footer">
            <span><?=e(branding()['system_name'])?> · Sistema de Gestión de Información</span>
            <span><?= has_role('BAYER') ? 'Consulta de información publicada' : 'Operación, control y trazabilidad' ?></span>
        </footer>
    </section>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?=url('/assets/js/app.js')?>"></script>
</body>
</html>
