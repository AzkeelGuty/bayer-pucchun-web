<section class="module-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-file-earmark-spreadsheet"></i> CATÁLOGOS MAESTROS</div>
        <h1 class="page-title">Importar <?=e(mb_strtolower((string)$meta['title']))?> desde Excel</h1>
        <p class="page-subtitle">Carga varios registros de una sola vez usando la plantilla oficial del sistema.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?=url('/maestros?tab='.rawurlencode($tab))?>"><i class="bi bi-arrow-left"></i> Volver al catálogo</a>
</section>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="import-step"><span>1</span><div><strong>Descarga la plantilla</strong><p>No cambies los encabezados de la fila 1.</p></div></div>
                <a class="btn btn-outline-primary w-100 mb-4" href="<?=url('/maestros/plantilla?tab='.rawurlencode($tab))?>" data-native-navigation>
                    <i class="bi bi-download"></i> Descargar plantilla XLSX
                </a>

                <div class="import-step"><span>2</span><div><strong>Completa tus registros</strong><p>Una fila equivale a un registro. No agregues fórmulas ni combines celdas.</p></div></div>
                <div class="import-step"><span>3</span><div><strong>Sube el Excel</strong><p>Si un código único ya existe, el sistema actualizará ese registro en vez de duplicarlo.</p></div></div>

                <form method="post" action="<?=url('/maestros/importar')?>" enctype="multipart/form-data" class="mt-3">
                    <?=csrf_field()?>
                    <input type="hidden" name="tab" value="<?=e($tab)?>">
                    <label class="form-label" for="master-excel">Archivo Excel (.xlsx)</label>
                    <input class="form-control" id="master-excel" name="excel" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    <div class="form-text">Máximo 8 MB. La importación se valida antes de confirmar los cambios.</div>
                    <button class="btn btn-success w-100 mt-3 btn-with-icon" type="submit"><i class="bi bi-cloud-arrow-up"></i><span>Importar a la base de datos</span></button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-body p-4">
                <h2 class="h5">Estructura esperada</h2>
                <p class="text-muted">Usa exactamente estos nombres de columna. Los campos obligatorios no pueden quedar vacíos.</p>
                <div class="table-responsive">
                    <table class="table app-table align-middle mb-0">
                        <thead><tr><th>Columna</th><th>Obligatorio</th><th>Qué contiene</th><th>Ejemplo</th></tr></thead>
                        <tbody>
                        <?php foreach($meta['import'] as $name=>$column): ?>
                            <tr>
                                <td><code><?=e($name)?></code></td>
                                <td><?=!empty($column['required'])?'<span class="badge text-bg-danger">Sí</span>':'<span class="badge text-bg-secondary">No</span>'?></td>
                                <td><?=e($column['description']??'')?></td>
                                <td><?=e($column['example']??'')?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <strong>Importante:</strong> para clientes y sucursales, si completas ubicación debes indicar departamento, provincia y distrito juntos. Para productos, el código de unidad debe existir previamente en Unidades de medida.
                </div>
            </div>
        </div>
    </div>
</div>
