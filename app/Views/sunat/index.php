<section class="module-header">
    <div>
        <div class="page-eyebrow">FACTURACIÓN ELECTRÓNICA</div>
        <h1 class="page-title">Emisión SUNAT</h1>
        <p class="page-subtitle">Emisión tributaria independiente del flujo interno de aprobación.</p>
    </div>
</section>

<section class="card mb-3"><div class="card-body">
    <h2 class="h5">Configuración del emisor</h2>
    <div class="d-flex gap-2 flex-wrap mb-3">
        <span class="badge <?= $config['ambiente']==='produccion'?'bg-warning text-dark':'bg-primary' ?>">
            <?=e(strtoupper($config['ambiente']))?></span>
        <span class="badge <?= $config['habilitado']?'bg-success':'bg-secondary' ?>">
            <?=$config['habilitado']?'CONFIGURADO':'NO HABILITADO'?></span>
    </div>
    <div class="row g-3 mb-2">
        <div class="col-md-4"><strong>RUC emisor</strong><div><?=e($config['ruc']?:'Pendiente de configurar')?></div></div>
        <div class="col-md-4"><strong>Serie factura</strong><div><?=e($config['serie']?:'Pendiente de configurar')?></div></div>
        <div class="col-md-4"><strong>Certificado digital</strong><div><?=$config['certificado_instalado']?'Instalado':'Pendiente'?></div></div>
    </div>
    <?php if($config['issues']): ?>
    <div class="alert alert-warning mt-3">
        <strong>Configuración necesaria para emitir:</strong>
        <ul class="mb-0"><?php foreach($config['issues'] as $issue): ?><li><?=e($issue)?></li><?php endforeach;?></ul>
    </div>
    <?php endif; ?>
    <p class="text-muted small mb-0">Las credenciales y la clave privada se gestionan exclusivamente en el servidor. Nunca se muestran aquí.
    El ambiente BETA no genera comprobantes con validez tributaria. El número INT del ERP se mantiene separado de la serie y correlativo SUNAT.</p>
</div></section>

<section class="card mb-3"><div class="card-body">
    <h2 class="h5">Facturas publicadas listas para revisión tributaria</h2>
    <p class="text-muted small">La primera modalidad admite solamente facturas gravadas con IGV 18 %, sin descuentos, detracciones ni operaciones especiales.
    La boleta, las notas y la GRE requieren sus propios procedimientos de emisión.</p>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Documento interno</th><th>Fecha</th><th>Cliente</th><th>Estado SUNAT</th><th>Emisión</th></tr></thead>
        <tbody>
        <?php foreach($candidates as $candidate): ?>
        <tr>
            <td><a href="<?=url('/documentos/ver?id='.(int)$candidate['id'])?>"><?=e($candidate['numero'])?></a></td>
            <td><?=e($candidate['fecha'])?></td>
            <td><?=e($candidate['cliente'])?></td>
            <td><?=e($candidate['sunat_estado']?:'SIN ENVIAR')?></td>
            <td>
                <?php if($candidate['sunat_id']): ?>
                    <span class="text-muted small">Intento registrado. Consulte el resultado debajo.</span>
                <?php elseif(!$config['habilitado']): ?>
                    <span class="text-muted small">Configurar SUNAT para habilitar</span>
                <?php else: ?>
                <form method="post" data-no-soft-nav action="<?=url('/sunat/emitir')?>" class="d-flex flex-wrap gap-2 align-items-center">
                    <?=csrf_field()?>
                    <input type="hidden" name="documento_id" value="<?=e((int)$candidate['id'])?>">
                    <label class="visually-hidden" for="mode-<?=e((int)$candidate['id'])?>">Interpretación de precios</label>
                    <select id="mode-<?=e((int)$candidate['id'])?>" name="modo_precio" class="form-select form-select-sm" required style="max-width:200px">
                        <option value="">¿Precio incluye IGV?</option>
                        <option value="PRECIO_INCLUYE_IGV" <?=($candidate['precio_igv_modalidad']??'')==='INCLUIDO'?'selected':''?>>Sí, incluye IGV 18 %</option>
                        <option value="PRECIO_SIN_IGV" <?=($candidate['precio_igv_modalidad']??'')==='ADICIONAL'?'selected':''?>>No, sin IGV</option>
                    </select>
                    <label class="small"><input type="checkbox" name="confirma_gravados" value="1" required>
                        Confirmo que todas las líneas están gravadas con IGV 18 %.</label>
                    <label class="small"><input type="checkbox" name="confirma_contado" value="1" required>
                        Confirmo que esta venta es al contado.</label>
                    <?php if($config['ambiente']==='produccion'): ?>
                        <input class="form-control form-control-sm" name="confirmacion_produccion"
                            placeholder="Escribir EMITIR" autocomplete="off" required style="max-width:130px">
                    <?php endif; ?>
                    <button class="btn btn-primary btn-sm btn-with-icon" type="submit"
                            onclick="return confirm('¿Enviar esta factura al ambiente SUNAT configurado? Se reservará una serie y correlativo fiscal.');">
                        <i class="bi bi-send" aria-hidden="true"></i><span>Emitir factura</span>
                    </button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$candidates): ?><tr><td colspan="5" class="text-muted">No existen facturas publicadas y vigentes.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></section>

<section class="card"><div class="card-body">
    <h2 class="h5">Historial de emisión y respuestas</h2>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Fecha</th><th>Documento</th><th>Número fiscal</th><th>Ambiente</th><th>Resultado</th><th>CDR</th><th>Archivos</th></tr></thead>
        <tbody>
        <?php foreach($emissions as $emission): ?>
        <tr>
            <td><?=e($emission['creado_en'])?></td>
            <td><a href="<?=url('/documentos/ver?id='.(int)$emission['documento_id'])?>"><?=e($emission['referencia_interna'])?></a></td>
            <td><?=e($emission['serie'].'-'.$emission['correlativo'])?></td>
            <td><?=e($emission['ambiente'])?></td>
            <td>
                <strong><?=e($emission['estado'])?></strong>
                <?php if($emission['ambiente']==='BETA'): ?><span class="text-muted small">(prueba, no fiscal)</span><?php endif; ?>
            </td>
            <td><span><?=e($emission['codigo_cdr']??'—')?></span>
                <?php if($emission['mensaje_cdr']): ?><div class="text-muted small"><?=e($emission['mensaje_cdr'])?></div><?php endif; ?>
            </td>
            <td class="d-flex gap-2 flex-wrap">
                <?php if($emission['xml_archivo']): ?>
                  <a class="btn btn-sm btn-outline-primary" data-native-navigation href="<?=url('/sunat/descargar?id='.(int)$emission['id'].'&tipo=xml')?>">XML</a>
                <?php endif; ?>
                <?php if($emission['cdr_archivo']): ?>
                  <a class="btn btn-sm btn-outline-primary" data-native-navigation href="<?=url('/sunat/descargar?id='.(int)$emission['id'].'&tipo=cdr')?>">CDR</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$emissions): ?><tr><td colspan="7" class="text-muted">Todavía no hay envíos registrados.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></section>
