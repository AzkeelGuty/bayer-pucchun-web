<section class="page-header">
<div><div class="page-eyebrow">SEGURIDAD Y ACCESO</div><h1 class="page-title">Usuarios, roles y sesiones</h1><p class="page-subtitle">Administración de cuentas internas y acceso externo controlado para Bayer.</p></div>
<button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#newUserForm" aria-expanded="false">Nuevo usuario</button>
</section>

<div class="collapse mb-4" id="newUserForm">
<div class="card"><div class="card-body p-4"><h5 class="mb-3">Crear usuario</h5>
<form method="post" action="<?=url('/seguridad/usuarios/guardar')?>" class="row g-3">
<?=csrf_field()?>
<div class="col-md-6"><label class="form-label">Nombre completo</label><input class="form-control" name="nombre" maxlength="120" required></div>
<div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" name="email" type="email" maxlength="120" required></div>
<div class="col-md-6"><label class="form-label">Rol</label><select class="form-select" name="role" required><?php foreach(['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'] as $role): ?><option><?=e($role)?></option><?php endforeach;?></select></div>
<div class="col-md-6"><label class="form-label">Contraseña temporal</label><input class="form-control" name="password" type="password" minlength="10" autocomplete="new-password" required><div class="form-text">Mínimo 10 caracteres. No se muestra nuevamente.</div></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Crear cuenta</button></div>
</form></div></div>
</div>

<div class="row g-4">
<div class="col-xl-8"><div class="card h-100"><div class="card-header bg-white"><strong>Usuarios</strong></div><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($users as $row): ?><tr>
<td><strong><?=e($row['nombre'])?></strong><small class="d-block text-muted"><?=e($row['email'])?></small></td>
<td><form method="post" action="<?=url('/seguridad/usuarios/rol')?>" class="inline-admin-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($row['id'])?>"><select class="form-select form-select-sm" name="role"><?php foreach(['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'] as $role): ?><option <?=$row['roles']===$role?'selected':''?>><?=e($role)?></option><?php endforeach;?></select><button class="btn btn-sm btn-outline-primary">Guardar</button></form></td>
<td><span class="badge-status <?=$row['estado']==='ACTIVO'?'status-publicado':'status-anulado'?>"><?=e($row['estado'])?></span></td>
<td><form method="post" action="<?=url('/seguridad/usuarios/estado')?>" class="m-0"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($row['id'])?>"><input type="hidden" name="estado" value="<?=$row['estado']==='ACTIVO'?0:1?>"><button class="btn btn-sm btn-outline-secondary" <?=$row['id']===(auth_user()['id']??0)&&$row['estado']==='ACTIVO'?'disabled':''?>><?=$row['estado']==='ACTIVO'?'Desactivar':'Activar'?></button></form></td>
</tr><?php endforeach;?>
</tbody></table></div></div></div>
<div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white"><strong>Roles del sistema</strong></div><div class="card-body p-0"><div class="role-list"><?php foreach($roles as $r): ?><div class="role-row"><div><strong><?=e($r['nombre'])?></strong><small><?=e($r['descripcion']??'')?></small></div><span><?=e($r['usuarios'])?> usuarios</span></div><?php endforeach;?></div></div></div></div>
</div>

<div class="card mt-4"><div class="card-header bg-white"><strong>Actividad de acceso</strong></div><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Fecha</th><th>Usuario</th><th>IP</th><th>Acción</th></tr></thead><tbody><?php foreach($sessions as $s): ?><tr><td><?=e($s['fecha_hora'])?></td><td><?=e($s['usuario']??'Cuenta no identificada')?></td><td><?=e($s['ip']??'—')?></td><td><?=e($s['accion'])?></td></tr><?php endforeach;?></tbody></table></div></div>