<?php
$brand = branding();
$primaryLogo = branding_logo_url('logo_primary');
$partnerLogo = branding_logo_url('logo_partner');
?>
<section class="login-shell login-clean" aria-labelledby="loginTitle">
    <div class="login-brand">
        <div class="login-brand-top">
            <div class="login-logo-row">
                <?php if($primaryLogo): ?>
                    <img class="login-logo" src="<?=e($primaryLogo)?>" alt="<?=e($brand['system_name'])?>">
                <?php else: ?>
                    <span class="login-logo-fallback"><?=e(strtoupper(substr($brand['system_name'],0,1)))?></span>
                <?php endif; ?>
                <?php if($partnerLogo): ?>
                    <span class="login-logo-separator"></span>
                    <img class="login-logo partner" src="<?=e($partnerLogo)?>" alt="<?=e($brand['partner_name'])?>">
                <?php endif; ?>
            </div>
            <div class="login-brand-name"><?=e($brand['system_name'])?></div>
        </div>

        <div class="login-brand-message">
            <span class="login-kicker"><?=e($brand['login_kicker'])?></span>
            <h1><?=e($brand['login_title'])?></h1>
            <p><?=e($brand['login_message'])?></p>
        </div>

        <div class="login-brand-line"></div>
    </div>

    <div class="login-form-panel">
        <div class="login-form-wrap">
            <div class="login-form-badge">ACCESO SEGURO</div>
            <h2 id="loginTitle">Iniciar sesión</h2>
            <p class="login-form-intro">Ingresa con tu cuenta asignada.</p>

            <form method="post" action="<?=url('/login')?>" autocomplete="on" class="login-form">
                <?=csrf_field()?>
                <div class="mb-3">
                    <label class="form-label" for="email"><i class="bi bi-envelope"></i> Correo</label>
                    <input id="email" name="email" type="email" class="form-control login-input" autocomplete="username" inputmode="email" placeholder="correo@empresa.com" required autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password"><i class="bi bi-key"></i> Contraseña</label>
                    <div class="login-password-wrap">
                        <input id="password" name="password" type="password" class="form-control login-input login-password" autocomplete="current-password" placeholder="••••••••" required>
                        <button class="password-toggle" id="passwordToggle" type="button" aria-label="Mostrar contraseña" aria-pressed="false">Ver</button>
                    </div>
                </div>

                <button class="btn btn-primary login-submit btn-with-icon" type="submit"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><span>Ingresar</span></button>
            </form>

            <div class="login-security-compact">
                <span aria-hidden="true">✓</span>
                <span>Sesión protegida y acceso según rol.</span>
            </div>

            <?php if(is_local_env()): ?>
                <details class="demo-access">
                    <summary><i class="bi bi-person-badge"></i> Accesos de prueba</summary>
                    <div class="demo-access-grid">
                        <div><b>Administrador</b><span>admin@pucchun.pe</span><code>Admin123*</code></div>
                        <div><b>Digitador</b><span>digitacion@pucchun.pe</span><code>Digitador123*</code></div>
                        <div><b>Supervisor</b><span>supervision@pucchun.pe</span><code>Supervisor123*</code></div>
                        <div><b>Gerencia</b><span>gerencia@pucchun.pe</span><code>Gerencia123*</code></div>
                        <div><b>Bayer</b><span>consulta@bayer.pe</span><code>Bayer123*</code></div>
                    </div>
                    <small>Solo desarrollo local. Importar una vez database/seeders/002_usuarios_prueba.sql.</small>
                </details>
            <?php endif; ?>
        </div>
    </div>
</section>
