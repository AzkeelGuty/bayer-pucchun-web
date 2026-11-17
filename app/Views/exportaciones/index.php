<h2>Exportaciones</h2>
<p class="text-muted">Descarga datasets <strong>PUBLICADO</strong> de Documentos, Guías o Stock en el formato requerido por Bayer.</p>

<div class="card"><div class="card-body">
    <form method="get" action="<?= url('/export') ?>" id="export-form" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Conjunto de datos</label>
            <select name="type" class="form-select" required>
                <option value="documents">Documentos</option>
                <option value="guides">Guías</option>
                <option value="stock">Stock</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Formato</label>
            <select name="format" class="form-select" required>
                <option value="xlsx">XLSX</option>
                <option value="pdf">PDF</option>
                <option value="json">JSON</option>
                <option value="txt">TXT</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Desde</label>
            <input type="date" name="from" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Hasta</label>
            <input type="date" name="to" class="form-control">
        </div>
        <div class="col-md-1">
            <button type="submit" id="export-submit-btn" class="btn btn-primary w-100">Exportar</button>
        </div>
    </form>

    <div class="d-flex align-items-center gap-2 mt-3">
        <button type="button" id="export-count-btn" class="btn btn-sm btn-outline-secondary">Consultar registros</button>
        <span id="export-feedback" class="small text-muted"></span>
    </div>

    <p class="text-muted small mt-3 mb-0">La descarga solo incluye registros en estado PUBLICADO; el rango de fechas es opcional.</p>
</div></div>
<script src="<?= url('/assets/js/exports.js') ?>"></script>