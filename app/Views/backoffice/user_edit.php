<?php
$isMe=(bool)($selfEdit??false);
$id=(int)$profile['id'];
$back=$isMe?(has_role('BAYER')?'/bayer':'/dashboard'):'/seguridad';
$initial=mb_strtoupper(mb_substr((string)$profile['nombre'],0,1,'UTF-8'),'UTF-8');
$active=(int)$profile['estado']===1;
$roles=[
    'ADMIN'=>['bi-shield-lock-fill','Administrador'],
    'DIGITADOR'=>['bi-pencil-square','Digitador'],
    'SUPERVISOR'=>['bi-patch-check-fill','Supervisor'],
    'GERENCIA'=>['bi-graph-up-arrow','Gerencia'],
    'BAYER'=>['bi-box-seam','Consulta Bayer'],
];
?>
<section class="module-header has-back-navigation">
  <div>
    <div class="page-eyebrow">SEGURIDAD · GESTIÓN DE CUENTAS</div>
    <h1 class="page-title"><?=$isMe?'Mi perfil':'Editar usuario'?></h1>
    <p class="page-subtitle">
      <?=$isMe?'Actualiza tus datos, tu fotografía y tu contraseña de acceso.':
      'Administra los datos, el rol, el acceso y las credenciales de esta cuenta.'?>
    </p>
  </div>
  <a class="btn btn-app-blue btn-with-icon btn-back-list" href="<?=url($back)?>">
    <i class="bi bi-arrow-left" aria-hidden="true"></i>
    <span><?=$isMe?'Volver al panel':'Volver a Usuarios y accesos'?></span>
  </a>
</section>
<div class="user-profile-banner mb-3">
  <div class="user-profile-photo-large">
    <?php if(!empty($profile['foto_perfil'])): ?>
      <img src="<?=url('/avatar-usuario?id='.$id)?>" alt="Fotografía de <?=e($profile['nombre'])?>" loading="lazy">
    <?php else: ?>
      <span><?=e($initial)?></span>
    <?php endif; ?>
  </div>
  <div class="user-profile-summary">
    <h2><?=e($profile['nombre'])?></h2>
    <p><i class="bi bi-envelope" aria-hidden="true"></i> <?=e($profile['email'])?></p>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="user-role-pill">
        <i class="bi <?=e($roles[$profile['role']??'ADMIN'][0])?>" aria-hidden="true"></i>
        <?=e($roles[$profile['role']??'ADMIN'][1])?>
      </span>
      <span class="badge-status <?=$active?'status-publicado':'status-anulado'?>">
        <i class="bi <?=$active?'bi-check-circle-fill':'bi-pause-circle-fill'?>" aria-hidden="true"></i>
        <?=$active?'ACTIVO':'INACTIVO'?>
      </span>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <div class="card h-100">
      <div class="card-body p-4">
        <div class="security-card-heading">
          <div><span class="security-heading-icon"><i class="bi bi-person-vcard"></i></span>
            <div><h2 class="h5">Datos de la cuenta</h2>
              <p>Información personal, contacto y fotografía.</p></div>
          </div>
        </div>
        <form method="post" enctype="multipart/form-data"
          action="<?=url($isMe?'/mi-perfil/actualizar':'/seguridad/usuarios/actualizar')?>"
          class="row g-3">
          <?=csrf_field()?>
          <?php if(!$isMe): ?><input type="hidden" name="id" value="<?=$id?>"><?php endif; ?>
          <div class="col-md-6">
            <label class="form-label" for="account-nombre"><i class="bi bi-person"></i> Nombre completo *</label>
            <input id="account-nombre" class="form-control" name="nombre" maxlength="120"
              value="<?=e($profile['nombre'])?>" autocomplete="name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="account-email"><i class="bi bi-envelope"></i> Correo electrónico *</label>
            <input id="account-email" class="form-control" name="email" type="email" maxlength="120"
              value="<?=e($profile['email'])?>" autocomplete="email" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="account-phone"><i class="bi bi-telephone"></i> Teléfono (opcional)</label>
            <input id="account-phone" class="form-control" name="telefono" type="tel" maxlength="25"
              value="<?=e((string)($profile['telefono']??''))?>" autocomplete="tel"
              placeholder="Ejemplo: +51 999 999 999">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="account-cargo"><i class="bi bi-briefcase"></i> Cargo</label>
            <input id="account-cargo" class="form-control" name="cargo" maxlength="100"
              value="<?=e((string)($profile['cargo']??''))?>"
              placeholder="Ejemplo: Supervisor de operaciones"
              <?=$isMe?'readonly':''?>>
            <?php if($isMe): ?><div class="form-text">El cargo lo administra el responsable del sistema.</div><?php endif; ?>
          </div>
          <?php if(!$isMe): ?>
          <div class="col-md-6">
            <label class="form-label" for="account-role"><i class="bi bi-shield-lock"></i> Rol de acceso *</label>
            <select class="form-select" id="account-role" name="role" required>
              <?php foreach($roles as $name=>$data): ?>
                <option value="<?=e($name)?>" <?=$name===$profile['role']?'selected':''?>>
                  <?=e($name)?> · <?=e($data[1])?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="account-estado"><i class="bi bi-toggle-on"></i> Estado de acceso</label>
            <select class="form-select" id="account-estado" name="estado">
              <option value="1" <?=$active?'selected':''?>>Activo</option>
              <option value="0" <?=!$active?'selected':''?> <?=(int)(auth_user()['id']??0)===$id?'disabled':''?>>Desactivado</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-12">
            <label class="form-label" for="account-photo"><i class="bi bi-camera"></i> Fotografía de perfil (opcional)</label>
            <input class="form-control" id="account-photo" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
            <div class="form-text">JPG, PNG o WEBP · hasta 2 MB. Si no seleccionas una imagen se conserva la actual.</div>
          </div>
          <?php if(!empty($profile['foto_perfil'])): ?>
          <div class="col-12">
            <label class="d-inline-flex align-items-center gap-2">
              <input type="checkbox" class="form-check-input mt-0" name="eliminar_foto" value="1">
              <span>Quitar la fotografía actual y mostrar la inicial del nombre</span>
            </label>
          </div>
          <?php endif; ?>
          <div class="col-12 d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-primary btn-with-icon" type="submit">
              <i class="bi bi-floppy" aria-hidden="true"></i><span>Guardar cambios</span>
            </button>
            <a class="btn btn-outline-primary btn-with-icon" href="<?=url($back)?>">
              <i class="bi bi-x-circle" aria-hidden="true"></i><span>Cancelar</span>
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-body p-4">
        <div class="security-card-heading">
          <div><span class="security-heading-icon"><i class="bi bi-key-fill"></i></span>
            <div><h2 class="h5"><?=$isMe?'Cambiar contraseña':'Restablecer contraseña'?></h2>
              <p><?=$isMe?'Verifica tu contraseña actual para elegir otra.':
                'Establece una contraseña temporal para esta cuenta.'?></p></div>
          </div>
        </div>
        <form method="post"
          action="<?=url($isMe?'/mi-perfil/password':'/seguridad/usuarios/password')?>"
          class="row g-3">
          <?=csrf_field()?>
          <?php if(!$isMe): ?><input type="hidden" name="id" value="<?=$id?>"><?php endif; ?>
          <?php if($isMe): ?>
          <div class="col-12">
            <label class="form-label" for="account-current-password"><i class="bi bi-lock"></i> Contraseña actual *</label>
            <input class="form-control" id="account-current-password" type="password"
              name="password_actual" autocomplete="current-password" required>
          </div>
          <?php endif; ?>
          <div class="col-12">
            <label class="form-label" for="account-new-password">
              <i class="bi bi-shield-lock"></i> Nueva contraseña *
            </label>
            <input class="form-control" id="account-new-password" name="password_nueva"
              type="password" minlength="10" maxlength="256" autocomplete="new-password" required>
          </div>
          <div class="col-12">
            <label class="form-label" for="account-confirm-password">
              <i class="bi bi-shield-check"></i> Confirmar contraseña *
            </label>
            <input class="form-control" id="account-confirm-password" name="password_confirmar"
              type="password" minlength="10" maxlength="256" autocomplete="new-password" required>
          </div>
          <p class="form-text mb-0">Mínimo 10 caracteres. La contraseña se almacena cifrada mediante hash seguro y nunca se muestra.</p>
          <div class="col-12">
            <button type="submit" class="btn btn-app-blue btn-with-icon">
              <i class="bi bi-key" aria-hidden="true"></i><span><?=$isMe?'Cambiar contraseña':'Restablecer contraseña'?></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
