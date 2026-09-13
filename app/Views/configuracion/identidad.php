<?php
$primaryLogo = branding_logo_url('logo_primary');
$partnerLogo = branding_logo_url('logo_partner');
?>
<section class="page-header">
    <div>
        <div class="page-eyebrow">CONFIGURACIÓN</div>
        <h1 class="page-title">Identidad visual</h1>
        <p class="page-subtitle">Logotipos y colores institucionales utilizados en el acceso y la navegación del sistema.</p>
    </div>
</section>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-body p-4">
                <form method="post" action="<?=url('/configuracion/identidad')?>" enctype="multipart/form-data">
                    <?=csrf_field()?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="system_name">Nombre principal</label>
                            <input class="form-control" id="system_name" name="system_name" maxlength="80" value="<?=e($branding['system_name'])?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="partner_name">Aliado / partner</label>
                            <input class="form-control" id="partner_name" name="partner_name" maxlength="80" value="<?=e($branding['partner_name'])?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="primary_color">Color principal</label>
                            <div class="brand-color-input">
                                <input type="color" id="primary_color_picker" value="<?=e($branding['primary_color'])?>" aria-label="Seleccionar color principal">
                                <input class="form-control" id="primary_color" name="primary_color" value="<?=e($branding['primary_color'])?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="accent_color">Color de acento</label>
                            <div class="brand-color-input">
                                <input type="color" id="accent_color_picker" value="<?=e($branding['accent_color'])?>" aria-label="Seleccionar color de acento">
                                <input class="form-control" id="accent_color" name="accent_color" value="<?=e($branding['accent_color'])?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="logo_primary">Logo principal</label>
                            <input class="form-control" id="logo_primary" name="logo_primary" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                            <div class="form-text">PNG, JPG o WEBP. Máximo 2 MB.</div>
                            <?php if($primaryLogo): ?>
                                <label class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_logo_primary" value="1">
                                    <span class="form-check-label">Quitar logo actual</span>
                                </label>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="logo_partner">Logo del aliado</label>
                            <input class="form-control" id="logo_partner" name="logo_partner" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                            <div class="form-text">PNG, JPG o WEBP. Máximo 2 MB.</div>
                            <?php if($partnerLogo): ?>
                                <label class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_logo_partner" value="1">
                                    <span class="form-check-label">Quitar logo actual</span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">Guardar identidad</button>
                        <a class="btn btn-outline-primary" href="<?=url('/dashboard')?>">Volver</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card brand-preview-card">
            <div class="card-body p-4">
                <div class="page-eyebrow">VISTA PREVIA</div>
                <div class="brand-preview">
                    <div class="brand-preview-logos">
                        <?php if($primaryLogo): ?>
                            <img src="<?=e($primaryLogo)?>" alt="Logo principal">
                        <?php else: ?>
                            <span class="brand-preview-fallback">P</span>
                        <?php endif; ?>
                        <span class="brand-preview-divider"></span>
                        <?php if($partnerLogo): ?>
                            <img src="<?=e($partnerLogo)?>" alt="Logo aliado">
                        <?php else: ?>
                            <span class="brand-preview-fallback partner">B</span>
                        <?php endif; ?>
                    </div>
                    <strong><?=e($branding['system_name'])?></strong>
                    <small><?=e($branding['partner_name'])?></small>
                </div>
                <div class="brand-swatch-row">
                    <span><i style="background:<?=e($branding['primary_color'])?>"></i> Principal</span>
                    <span><i style="background:<?=e($branding['accent_color'])?>"></i> Acento</span>
                </div>
            </div>
        </div>
    </div>
</div>
