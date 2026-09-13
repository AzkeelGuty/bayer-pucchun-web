<section class="login-shell">
    <div class="login-brand">
        <div>
            <div class="login-brand-mark">
                <span class="brand-mark">BP</span>
                <div>
                    <strong>Bayer - Pucchún</strong>
                    <div class="small opacity-75">Sistema de Gestión de Información</div>
                </div>
            </div>
            <h1>Datos confiables para decisiones más rápidas y seguras.</h1>
            <p>Captura, validación, publicación, analítica y entrega de información en una sola plataforma.</p>
            <div class="login-pill-list">
                <span class="login-pill">Acceso por roles</span>
                <span class="login-pill">Trazabilidad</span>
                <span class="login-pill">Datos publicados</span>
                <span class="login-pill">Preparado para ERP</span>
            </div>
        </div>
        <small>Información hoy, más posibilidades mañana.</small>
    </div>
    <div class="login-form-panel">
        <div class="page-eyebrow">ACCESO SEGURO</div>
        <h2>Bienvenido</h2>
        <p class="text-muted mb-4">Ingresa con tu cuenta autorizada para continuar.</p>
        <form method="post" action="<?=url('/login')?>" autocomplete="on">
            <?=csrf_field()?>
            <div class="mb-3">
                <label class="form-label" for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" class="form-control" autocomplete="username" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Contraseña</label>
                <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary w-100 py-2" type="submit">Ingresar al sistema</button>
        </form>
        <div class="login-security">Sesión protegida, control de accesos y trazabilidad de acciones.</div>
    </div>
</section>
