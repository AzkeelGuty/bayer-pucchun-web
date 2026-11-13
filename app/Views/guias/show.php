<?php $h = $record['header']; ?>
<link rel="stylesheet" href="<?=asset_url('assets/css/commercial-totals.css')?>">
<div class="d-flex justify-content-between align-items-center">
    <h2>Guía <?= e($h['numero']) ?></h2>
    <?= estado_badge($h['estado_registro']) ?>
</div>
<p class="d-flex gap-2">
    <a class="btn btn-outline-primary btn-with-icon" href="<?= url('/guias') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i><span>Volver al listado</span></a>
    <?php if (has_role('ADMIN', 'DIGITADOR') && in_array($h['estado_registro'], ['BORRADOR','OBSERVADO'], true)): ?>
        <a class="btn btn-outline-primary btn-with-icon" href="<?= url('/guias/editar?id=' . $h['id']) ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i><span><?= $h['estado_registro']==='OBSERVADO' ? 'Corregir' : 'Editar' ?></span></a>
    <?php endif; ?>
</p>

<?php if(!empty($h['documento_origen_id'])): ?>
<p><a class="btn btn-outline-primary btn-with-icon" href="<?=url('/documentos/ver?id='.(int)$h['documento_origen_id'])?>"><i class="bi bi-file-earmark-text" aria-hidden="true"></i><span>Ver documento de origen</span></a></p>
<?php endif; ?>
<div class="card mb-3"><div class="card-body">
    <h6 class="card-title">Cabecera</h6>
    <div class="row">
        <div class="col-md-3"><strong>Fecha</strong><div><?= e($h['fecha']) ?></div></div>
        <div class="col-md-3"><strong>Cliente</strong><div><?= e($clientes[$h['cliente_id']]['razon_social'] ?? $h['cliente_id']) ?></div></div>
        <div class="col-md-3"><strong>Vendedor</strong><div><?= e($vendedores[$h['vendedor_id']]['nombre'] ?? $h['vendedor_id']) ?></div></div>
        <div class="col-md-3"><strong>Sucursal</strong><div><?= e($sucursales[$h['sucursal_id']]['nombre'] ?? $h['sucursal_id']) ?></div></div>
    </div>
    <div class="row mt-3"><div class="col-12">
        <strong>Destino de entrega</strong>
        <div><?= e(implode(' · ', array_filter([
            $distritos[$h['distrito_id']]['nombre'] ?? null,
            $provincias[$h['provincia_id']]['nombre'] ?? null,
            $departamentos[$h['departamento_id']]['nombre'] ?? null,
        ])) ?: 'Sin destino registrado') ?></div>
    </div></div>
    <?php if ($h['estado_registro'] === 'OBSERVADO' && $h['observation_reason']): ?>
        <div class="alert alert-warning mt-3 mb-0"><strong>Motivo de observación:</strong> <?= e($h['observation_reason']) ?></div>
    <?php endif; ?>
    <?php if ($h['estado_registro'] === 'ANULADO' && $h['cancellation_reason']): ?>
        <div class="alert alert-danger mt-3 mb-0"><strong>Motivo de anulación:</strong> <?= e($h['cancellation_reason']) ?></div>
    <?php endif; ?>
</div></div>

<div class="card mb-3"><div class="card-body">
    <h6 class="card-title">Detalle de productos · <?=count($record['details'])?> líneas</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Producto</th><th>Unidad</th><th class="text-end">Cantidad</th>
                <?php if($totals['valorizado']): ?><th class="text-end">Valor unitario</th><th class="text-end">Importe</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($record['details'] as $i=>$line): ?>
                <tr>
                    <td><?= e($productos[$line['producto_id']]['nombre'] ?? $line['producto_id']) ?></td>
                    <td><?= e($unidades[$line['unidad_id']]['nombre'] ?? $line['unidad_id']) ?></td>
                    <td class="text-end"><?= e(format_quantity($line['cantidad'])) ?></td>
                    <?php if($totals['valorizado']): ?>
                    <td class="text-end"><?=e(\App\Services\CommercialTotalsService::money(
                        \App\Services\CommercialTotalsService::priceCents((string)$line['valor_unitario'])))?></td>
                    <td class="text-end fw-semibold"><?=e(\App\Services\CommercialTotalsService::money(
                        $totals['lineas'][$i]['importe']))?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <section class="commercial-summary mt-3" aria-label="Resumen de la guía de remisión">
        <div class="commercial-summary-heading"><i class="bi bi-box-seam" aria-hidden="true"></i>
            <strong>Resumen de la guía</strong></div>
        <div class="commercial-summary-grid">
            <div class="commercial-summary-note">
                <span><?=count($record['details'])?> <?=count($record['details'])===1?'producto':'productos'?></span>
                <div><?php foreach($totals['cantidades'] as $unit=>$quantity): ?>
                    <span class="commercial-unit-total"><?=e((string)$quantity)?> <?=e($unit)?></span>
                <?php endforeach; ?></div>
                <small class="text-muted">Cantidades agrupadas por unidad de medida.</small>
            </div>
            <?php if($totals['valorizado']): ?>
            <dl class="commercial-summary-values">
                <?php if($totals['subtotal']!==null): ?>
                    <div><dt>Subtotal sin IGV</dt><dd><?=e(\App\Services\CommercialTotalsService::money($totals['subtotal']))?></dd></div>
                    <div><dt>IGV e IPM (18 %)</dt><dd><?=e(\App\Services\CommercialTotalsService::money($totals['igv']))?></dd></div>
                <?php endif; ?>
                <div class="commercial-grand-total">
                    <dt><?=$totals['subtotal']!==null?'Valor comercial referencial':'Valor comercial sin desglose de IGV'?></dt>
                    <dd><?=e(\App\Services\CommercialTotalsService::money($totals['total']))?></dd>
                </div>
            </dl>
            <?php endif; ?>
        </div>
        <?php if($totals['valorizado']): ?>
        <p class="commercial-summary-help">Importes referenciales para control comercial; la guía sustenta el traslado, no reemplaza el comprobante de pago.</p>
        <?php endif; ?>
    </section>
</div></div>

<?php if (has_role('ADMIN', 'SUPERVISOR')): ?>
<div class="card"><div class="card-body">
    <h6 class="card-title">Flujo de aprobación</h6>
    <p class="text-muted small">Las acciones disponibles dependen del estado actual de la guía y de los permisos del usuario.</p>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($h['estado_registro'] === 'BORRADOR' && can_workflow_permission('validation.review')): ?>
            <form method="post" data-no-soft-nav action="<?= url('/guias/estado') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $h['id'] ?>"><input type="hidden" name="version" value="<?= e($h['version']) ?>"><input type="hidden" name="status" value="VALIDADO"><button class="btn btn-app-action btn-app-blue btn-with-icon" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Validar</span></button></form>
        <?php endif; ?>
        <?php if ($h['estado_registro'] === 'VALIDADO'): ?>
            <?php if (can_workflow_permission('publications.publish')): ?><form method="post" data-no-soft-nav action="<?= url('/guias/estado') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $h['id'] ?>"><input type="hidden" name="version" value="<?= e($h['version']) ?>"><input type="hidden" name="status" value="PUBLICADO"><button class="btn btn-app-action btn-app-green btn-with-icon" type="submit"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i><span>Publicar</span></button></form><?php endif; ?>
            <?php if (can_workflow_permission('validation.review')): ?><button type="button" class="btn btn-app-action btn-app-amber btn-with-icon" data-bs-toggle="modal" data-bs-target="#observarModal"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>Observar</span></button><?php endif; ?>
        <?php endif; ?>
        <?php if ($h['estado_registro'] === 'PUBLICADO' && can_workflow_permission('publications.publish')): ?>
            <button type="button" class="btn btn-app-action btn-app-red btn-with-icon" data-bs-toggle="modal" data-bs-target="#anularModal"><i class="bi bi-x-octagon" aria-hidden="true"></i><span>Anular</span></button>
        <?php endif; ?>
    </div>
</div></div>

<div class="modal fade" id="observarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" data-no-soft-nav action="<?= url('/guias/estado') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $h['id'] ?>"><input type="hidden" name="version" value="<?= e($h['version']) ?>">
            <input type="hidden" name="status" value="OBSERVADO">
            <div class="modal-header"><h5 class="modal-title">Observar guía</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Motivo de la observación</label>
                <textarea name="reason" class="form-control" rows="3" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-app-action btn-app-amber btn-with-icon" type="submit"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>Observar</span></button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="anularModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" data-no-soft-nav action="<?= url('/guias/estado') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $h['id'] ?>"><input type="hidden" name="version" value="<?= e($h['version']) ?>">
            <input type="hidden" name="status" value="ANULADO">
            <div class="modal-header"><h5 class="modal-title">Anular guía</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Motivo de la anulación</label>
                <textarea name="reason" class="form-control" rows="3" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-app-action btn-app-red btn-with-icon" type="submit"><i class="bi bi-x-octagon" aria-hidden="true"></i><span>Anular</span></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
