<?php
$primaryLogo = branding_logo_url('logo_primary');
$partnerLogo = branding_logo_url('logo_partner');
$favicon = branding_logo_url('favicon');
?>
<section class="page-header">
    <div>
        <div class="page-eyebrow">CONFIGURACIÓN</div>
        <h1 class="page-title">Identidad visual</h1>
        <p class="page-subtitle">Personaliza nombres, textos, paleta, menú, logotipos y favicon sin tocar el código.</p>
    </div>
</section>

<form method="post" action="<?=url('/configuracion/identidad')?>" enctype="multipart/form-data" id="brandingForm">
    <?=csrf_field()?>

    <div class="row g-4 branding-layout">
        <div class="col-xxl-8">
            <div class="card branding-section-card mb-4">
                <div class="card-body p-4">
                    <div class="branding-section-head">
                        <div><span>01</span><div><h5>Identidad institucional</h5><p>Nombres que aparecen en el menú, portal y encabezados.</p></div></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="system_name">Nombre principal</label>
                            <input class="form-control brand-live-text" id="system_name" name="system_name" maxlength="80" value="<?=e($branding['system_name'])?>" data-preview="preview_system_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="system_subtitle">Subtítulo principal</label>
                            <input class="form-control brand-live-text" id="system_subtitle" name="system_subtitle" maxlength="100" value="<?=e($branding['system_subtitle'])?>" data-preview="preview_system_subtitle" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="partner_name">Aliado / partner</label>
                            <input class="form-control brand-live-text" id="partner_name" name="partner_name" maxlength="80" value="<?=e($branding['partner_name'])?>" data-preview="preview_partner_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="partner_subtitle">Subtítulo del aliado</label>
                            <input class="form-control brand-live-text" id="partner_subtitle" name="partner_subtitle" maxlength="100" value="<?=e($branding['partner_subtitle'])?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="internal_title">Título de gestión interna</label>
                            <input class="form-control brand-live-text" id="internal_title" name="internal_title" maxlength="100" value="<?=e($branding['internal_title'])?>" data-preview="preview_internal_title" required>
                            <div class="form-text">Ejemplo: Pucchún Data Hub.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="portal_title">Título del portal externo</label>
                            <input class="form-control" id="portal_title" name="portal_title" maxlength="100" value="<?=e($branding['portal_title'])?>" required>
                            <div class="form-text">Ejemplo: Portal Bayer.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="footer_text">Texto institucional del pie</label>
                            <input class="form-control" id="footer_text" name="footer_text" maxlength="140" value="<?=e($branding['footer_text'])?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card branding-section-card mb-4">
                <div class="card-body p-4">
                    <div class="branding-section-head">
                        <div><span>02</span><div><h5>Acceso al sistema</h5><p>Personaliza el mensaje principal de la pantalla de inicio de sesión.</p></div></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="login_kicker">Etiqueta superior</label>
                            <input class="form-control brand-live-text" id="login_kicker" name="login_kicker" maxlength="80" value="<?=e($branding['login_kicker'])?>" data-preview="preview_login_kicker" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="login_title">Mensaje principal</label>
                            <input class="form-control brand-live-text" id="login_title" name="login_title" maxlength="120" value="<?=e($branding['login_title'])?>" data-preview="preview_login_title" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="login_message">Descripción</label>
                            <textarea class="form-control brand-live-text" id="login_message" name="login_message" maxlength="220" rows="3" data-preview="preview_login_message" required><?=e($branding['login_message'])?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card branding-section-card mb-4">
                <div class="card-body p-4">
                    <div class="branding-section-head branding-section-head-actions">
                        <div><span>03</span><div><h5>Paleta y apariencia</h5><p>Colores generales y estilo del menú lateral.</p></div></div>
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="brandingDefaults">Restablecer sugeridos</button>
                    </div>

                    <div class="brand-presets" aria-label="Paletas rápidas">
                        <button type="button" class="brand-preset" data-primary="#075B9F" data-accent="#168C5B" data-sidebar="#0A2F55" data-background="#F4F7FB"><i style="--p:#075B9F;--a:#168C5B;--s:#0A2F55"></i><span>Corporativo</span></button>
                        <button type="button" class="brand-preset" data-primary="#005AA9" data-accent="#00A651" data-sidebar="#003A70" data-background="#F5F9FC"><i style="--p:#005AA9;--a:#00A651;--s:#003A70"></i><span>Azul / verde</span></button>
                        <button type="button" class="brand-preset" data-primary="#1D4ED8" data-accent="#0F9D75" data-sidebar="#172554" data-background="#F6F8FC"><i style="--p:#1D4ED8;--a:#0F9D75;--s:#172554"></i><span>Moderno</span></button>
                        <button type="button" class="brand-preset" data-primary="#275D38" data-accent="#C68A00" data-sidebar="#173B25" data-background="#F7F8F4"><i style="--p:#275D38;--a:#C68A00;--s:#173B25"></i><span>Campo</span></button>
                    </div>

                    <div class="row g-3 mt-1">
                        <?php foreach([
                            ['primary_color','primary_color_picker','Color principal',$branding['primary_color']],
                            ['accent_color','accent_color_picker','Color de acento',$branding['accent_color']],
                            ['sidebar_color','sidebar_color_picker','Color del menú lateral',$branding['sidebar_color']],
                            ['background_color','background_color_picker','Color de fondo',$branding['background_color']],
                        ] as [$name,$picker,$label,$value]): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="<?=$name?>"><?=e($label)?></label>
                            <div class="brand-color-input">
                                <input type="color" id="<?=$picker?>" value="<?=e($value)?>" aria-label="<?=e($label)?>">
                                <input class="form-control brand-color-code" id="<?=$name?>" name="<?=$name?>" value="<?=e($value)?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="col-md-6">
                            <label class="form-label" for="sidebar_theme">Tema del menú lateral</label>
                            <select class="form-select" id="sidebar_theme" name="sidebar_theme">
                                <option value="dark" <?=$branding['sidebar_theme']==='dark'?'selected':''?>>Oscuro</option>
                                <option value="light" <?=$branding['sidebar_theme']==='light'?'selected':''?>>Claro</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card branding-section-card">
                <div class="card-body p-4">
                    <div class="branding-section-head">
                        <div><span>04</span><div><h5>Logotipos y favicon</h5><p>Admite SVG, PNG, JPG y WEBP. El SVG se valida antes de publicarse.</p></div></div>
                    </div>

                    <div class="row g-3">
                        <?php foreach([
                            ['logo_primary','Logo principal',$primaryLogo],
                            ['logo_partner','Logo del aliado',$partnerLogo],
                            ['favicon','Favicon / ícono del navegador',$favicon],
                        ] as [$field,$label,$current]): ?>
                        <div class="<?=$field==='favicon'?'col-md-12':'col-md-6'?>">
                            <div class="brand-upload-box">
                                <div class="brand-upload-head">
                                    <div>
                                        <label class="form-label mb-1" for="<?=$field?>"><?=e($label)?></label>
                                        <small>SVG, PNG, JPG o WEBP · máximo 2 MB</small>
                                    </div>
                                    <?php if($current): ?><img src="<?=e($current)?>" alt="" class="<?=$field==='favicon'?'brand-current-favicon':'brand-current-logo'?>"><?php endif; ?>
                                </div>
                                <input class="form-control" id="<?=$field?>" name="<?=$field?>" type="file" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp">
                                <?php if($current): ?>
                                    <label class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_<?=$field?>" value="1">
                                        <span class="form-check-label">Quitar archivo actual</span>
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="branding-savebar">
                <div>
                    <strong>Los cambios se aplican a todo el sistema.</strong>
                    <small>Incluye login, barra superior, menú, pie y portal Bayer.</small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-primary" href="<?=url('/dashboard')?>">Volver</a>
                    <button class="btn btn-primary" type="submit">Guardar identidad</button>
                </div>
            </div>
        </div>

        <div class="col-xxl-4">
            <div class="branding-preview-sticky">
                <div class="card brand-preview-card mb-4">
                    <div class="card-body p-3">
                        <div class="page-eyebrow mb-3">VISTA PREVIA · APLICACIÓN</div>
                        <div class="brand-ui-preview sidebar-<?=e($branding['sidebar_theme'])?>" id="brandingPreview"
                             style="--preview-primary:<?=e($branding['primary_color'])?>;--preview-accent:<?=e($branding['accent_color'])?>;--preview-sidebar:<?=e($branding['sidebar_color'])?>;--preview-bg:<?=e($branding['background_color'])?>">
                            <aside class="brand-ui-sidebar">
                                <div class="brand-ui-brand">
                                    <?php if($primaryLogo): ?><img src="<?=e($primaryLogo)?>" alt=""><?php else: ?><b>P</b><?php endif; ?>
                                    <div><strong id="preview_system_name"><?=e($branding['system_name'])?></strong><small id="preview_system_subtitle"><?=e($branding['system_subtitle'])?></small></div>
                                </div>
                                <span class="brand-ui-label">ANALÍTICA</span>
                                <span class="brand-ui-link active"><i>DB</i> Dashboard</span>
                                <span class="brand-ui-label">OPERACIÓN</span>
                                <span class="brand-ui-link"><i>DC</i> Documentos</span>
                                <span class="brand-ui-link"><i>ST</i> Stock</span>
                            </aside>
                            <div class="brand-ui-workspace">
                                <div class="brand-ui-topbar"><small>GESTIÓN INTERNA</small><strong id="preview_internal_title"><?=e($branding['internal_title'])?></strong></div>
                                <div class="brand-ui-content">
                                    <span>INFORMACIÓN</span>
                                    <h4>Dashboard</h4>
                                    <div class="brand-ui-cards"><i></i><i></i><i></i></div>
                                    <div class="brand-ui-chart"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card brand-preview-card">
                    <div class="card-body p-3">
                        <div class="page-eyebrow mb-3">VISTA PREVIA · LOGIN</div>
                        <div class="brand-login-preview" id="brandingLoginPreview" style="--preview-primary:<?=e($branding['primary_color'])?>;--preview-accent:<?=e($branding['accent_color'])?>;--preview-sidebar:<?=e($branding['sidebar_color'])?>">
                            <div class="brand-login-logos">
                                <?php if($primaryLogo): ?><img src="<?=e($primaryLogo)?>" alt=""><?php endif; ?>
                                <?php if($partnerLogo): ?><img src="<?=e($partnerLogo)?>" alt=""><?php endif; ?>
                            </div>
                            <span id="preview_login_kicker"><?=e($branding['login_kicker'])?></span>
                            <h4 id="preview_login_title"><?=e($branding['login_title'])?></h4>
                            <p id="preview_login_message"><?=e($branding['login_message'])?></p>
                            <small id="preview_partner_name"><?=e($branding['partner_name'])?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
