<section class="login-shell" aria-labelledby="loginTitle">
    <div class="login-brand">
        <div class="login-brand-content">
            <div class="login-brand-mark">
                <span class="brand-mark" aria-hidden="true">BP</span>
                <div>
                    <strong>Bayer <span class="brand-separator">|</span> Pucchún</strong>
                    <div class="login-brand-subtitle">Sistema de Gestión de Información</div>
                </div>
            </div>

            <div class="login-copy">
                <span class="login-kicker">DATOS · CONTROL · TRAZABILIDAD</span>
                <h1>Información confiable para decisiones más rápidas y seguras.</h1>
                <p>Una plataforma para capturar, validar, publicar, analizar y entregar información con una sola fuente de verdad.</p>
            </div>

            <div class="login-feature-grid" aria-label="Beneficios principales">
                <div class="login-feature">
                    <span class="login-feature-icon" aria-hidden="true">01</span>
                    <span><strong>Acceso por roles</strong><small>Permisos según perfil.</small></span>
                </div>
                <div class="login-feature">
                    <span class="login-feature-icon" aria-hidden="true">02</span>
                    <span><strong>Trazabilidad</strong><small>Acciones y cambios auditables.</small></span>
                </div>
                <div class="login-feature">
                    <span class="login-feature-icon" aria-hidden="true">03</span>
                    <span><strong>Datos publicados</strong><small>Bayer consulta solo información aprobada.</small></span>
                </div>
                <div class="login-feature">
                    <span class="login-feature-icon" aria-hidden="true">04</span>
                    <span><strong>Preparado para ERP</strong><small>Arquitectura lista para integración futura.</small></span>
                </div>
            </div>
        </div>

        <div class="login-brand-footer">
            <span class="status-dot" aria-hidden="true"></span>
            <span>Información hoy, más posibilidades mañana.</span>
        </div>
    </div>

    <div class="login-form-panel">
        <div class="login-form-wrap">
            <div class="login-form-badge">ACCESO SEGURO</div>
            <h2 id="loginTitle">Bienvenido</h2>
            <p class="login-form-intro">Ingresa con tu cuenta autorizada para continuar al sistema.</p>

            <form method="post" action="<?=url('/login')?>" autocomplete="on" class="login-form">
                <?=csrf_field()?>

                <div class="mb-3">
                    <label class="form-label" for="email">Correo electrónico</label>
                    <div class="login-input-wrap">
                        <span class="login-input-icon" aria-hidden="true">@</span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            class="form-control login-input"
                            autocomplete="username"
                            inputmode="email"
                            placeholder="nombre@empresa.com"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Contraseña</label>
                    <div class="login-input-wrap">
                        <span class="login-input-icon" aria-hidden="true">●</span>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control login-input login-password"
                            autocomplete="current-password"
                            placeholder="Ingresa tu contraseña"
                            required
                        >
                        <button
                            class="password-toggle"
                            id="passwordToggle"
                            type="button"
                            aria-label="Mostrar contraseña"
                            aria-pressed="false"
                        >
                            Ver
                        </button>
                    </div>
                </div>

                <button class="btn btn-primary login-submit" type="submit">
                    <span>Ingresar al sistema</span>
                    <span aria-hidden="true">→</span>
                </button>
            </form>

            <div class="login-security">
                <span class="login-security-icon" aria-hidden="true">✓</span>
                <span>
                    <strong>Conexión protegida</strong>
                    <small>Sesiones controladas, acceso por roles y trazabilidad de acciones.</small>
                </span>
            </div>

            <div class="login-help">
                <span>Uso autorizado para Pucchún y Bayer.</span>
                <span>v1 · Data Hub</span>
            </div>
        </div>
    </div>
</section>
