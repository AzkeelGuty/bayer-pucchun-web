<section class="page-header security-page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-shield-check"></i> SEGURIDAD Y ACCESO</div>
        <h1 class="page-title">Usuarios, roles y sesiones</h1>
        <p class="page-subtitle">Administración de cuentas internas y acceso externo controlado para Bayer.</p>
    </div>
    <button class="btn btn-primary btn-with-icon" type="button" data-bs-toggle="collapse" data-bs-target="#newUserForm" aria-expanded="false" aria-controls="newUserForm">
        <i class="bi bi-person-plus-fill" aria-hidden="true"></i><span>Nuevo usuario</span>
    </button>
</section>

<div class="collapse mb-4" id="newUserForm">
    <div class="card security-create-card">
        <div class="card-body p-4">
            <div class="security-card-heading">
                <div>
                    <span class="security-heading-icon"><i class="bi bi-person-plus"></i></span>
                    <div><h5>Crear usuario</h5><p>Registra una cuenta y asigna el rol inicial.</p></div>
                </div>
                <button class="btn btn-sm btn-light icon-action" type="button" data-bs-toggle="collapse" data-bs-target="#newUserForm" aria-label="Cerrar formulario" title="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form method="post" action="<?=url('/seguridad/usuarios/guardar')?>" class="row g-3">
                <?=csrf_field()?>
                <div class="col-md-6">
                    <label class="form-label" for="new_user_name"><i class="bi bi-person"></i> Nombre completo</label>
                    <input class="form-control" id="new_user_name" name="nombre" maxlength="120" autocomplete="name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="new_user_email"><i class="bi bi-envelope"></i> Correo</label>
                    <input class="form-control" id="new_user_email" name="email" type="email" maxlength="120" autocomplete="email" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="new_user_role"><i class="bi bi-person-badge"></i> Rol</label>
                    <select class="form-select" id="new_user_role" name="role" required>
                        <?php foreach(['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'] as $role): ?><option><?=e($role)?></option><?php endforeach;?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="new_user_password"><i class="bi bi-key"></i> Contraseña temporal</label>
                    <input class="form-control" id="new_user_password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                    <div class="form-text">Mínimo 10 caracteres. Entrégala de forma segura al usuario.</div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary btn-with-icon" type="submit">
                        <i class="bi bi-person-check-fill" aria-hidden="true"></i><span>Crear cuenta</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-with-icon" type="button" data-bs-toggle="collapse" data-bs-target="#newUserForm">
                        <i class="bi bi-x-circle" aria-hidden="true"></i><span>Cerrar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header bg-white security-table-title"><i class="bi bi-people-fill"></i><strong>Usuarios</strong></div>
            <div class="table-responsive">
                <table class="table app-table mb-0 security-users-table">
                    <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach($users as $row): ?><tr>
                        <td>
                            <div class="user-table-cell">
                                <span class="user-table-avatar"><?=e(strtoupper(substr((string)$row['nombre'],0,1)))?></span>
                                <div><strong><?=e($row['nombre'])?></strong><small><?=e($row['email'])?></small></div>
                            </div>
                        </td>
                        <td>
                            <form method="post" action="<?=url('/seguridad/usuarios/rol')?>" class="inline-admin-form">
                                <?=csrf_field()?>
                                <input type="hidden" name="id" value="<?=e($row['id'])?>">
                                <select class="form-select form-select-sm" name="role" aria-label="Rol de <?=e($row['nombre'])?>">
                                    <?php foreach(['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'] as $role): ?><option <?=$row['roles']===$role?'selected':''?>><?=e($role)?></option><?php endforeach;?>
                                </select>
                                <button class="btn btn-sm btn-outline-primary icon-action" title="Guardar rol" aria-label="Guardar rol">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </form>
                        </td>
                        <td><span class="badge-status <?=$row['estado']==='ACTIVO'?'status-publicado':'status-anulado'?>"><i class="bi <?=$row['estado']==='ACTIVO'?'bi-check-circle-fill':'bi-pause-circle-fill'?>"></i><?=e($row['estado'])?></span></td>
                        <td>
                            <form method="post" action="<?=url('/seguridad/usuarios/estado')?>" class="m-0">
                                <?=csrf_field()?>
                                <input type="hidden" name="id" value="<?=e($row['id'])?>">
                                <input type="hidden" name="estado" value="<?=$row['estado']==='ACTIVO'?0:1?>">
                                <button class="btn btn-sm <?=$row['estado']==='ACTIVO'?'btn-outline-secondary':'btn-outline-success'?> btn-with-icon" <?=$row['id']===(auth_user()['id']??0)&&$row['estado']==='ACTIVO'?'disabled':''?>>
                                    <i class="bi <?=$row['estado']==='ACTIVO'?'bi-person-slash':'bi-person-check'?>"></i>
                                    <span><?=$row['estado']==='ACTIVO'?'Desactivar':'Activar'?></span>
                                </button>
                            </form>
                        </td>
                    </tr><?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header bg-white security-table-title"><i class="bi bi-person-gear"></i><strong>Roles del sistema</strong></div>
            <div class="card-body p-0">
                <div class="role-list">
                    <?php foreach($roles as $r): ?>
                        <div class="role-row">
                            <div><strong><?=e($r['nombre'])?></strong><small><?=e($r['descripcion']??'')?></small></div>
                            <span><i class="bi bi-people"></i> <?=e($r['usuarios'])?> usuarios</span>
                        </div>
                    <?php endforeach;?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4 access-activity-card">
    <div class="card-header bg-white security-table-title security-table-title-between">
        <div><i class="bi bi-clock-history"></i><strong>Actividad de acceso</strong></div>
        <small>Últimos <?=e(count($sessions))?> eventos registrados</small>
    </div>
    <div class="table-responsive">
        <table class="table app-table mb-0 access-table">
            <thead>
                <tr>
                    <th><i class="bi bi-calendar3"></i> Fecha y hora</th>
                    <th><i class="bi bi-person"></i> Usuario</th>
                    <th><i class="bi bi-globe2"></i> Dirección IP</th>
                    <th><i class="bi bi-device-ssd"></i> Dispositivo</th>
                    <th><i class="bi bi-activity"></i> Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($sessions as $s):
                $device=access_device_info($s['user_agent']??null);
                $action=access_action_info((string)($s['accion']??''));
            ?>
                <tr>
                    <td><span class="access-date"><?=e($s['fecha_hora'])?></span></td>
                    <td><strong><?=e($s['usuario']??'Cuenta no identificada')?></strong></td>
                    <td><span class="access-ip"><i class="bi bi-router"></i><?=e(access_ip_label($s['ip']??null))?></span></td>
                    <td>
                        <span class="access-device" title="<?=e($s['user_agent']??'')?>"><i class="bi <?=e($device['icon'])?>"></i><?=e($device['label'])?></span>
                    </td>
                    <td><span class="access-action <?=e($action['class'])?>"><i class="bi <?=e($action['icon'])?>"></i><?=e($action['label'])?></span></td>
                </tr>
            <?php endforeach;?>
            <?php if(!$sessions): ?>
                <tr><td colspan="5"><div class="empty-inline"><i class="bi bi-shield-check"></i>No hay actividad registrada todavía.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>