<?php
$old = $_SESSION['_old'] ?? [];
$errors = $_SESSION['_errors'] ?? [];

$oldHeader = is_array($old['header'] ?? null)
    ? $old['header']
    : [];

$oldDetails = is_array($old['details'] ?? null)
    ? $old['details']
    : [[]];

if (!$oldDetails) {
    $oldDetails = [[]];
}
?>

<link rel="stylesheet" href="<?=url('/assets/css/documentos.css')?>">

<div class="document-form-page">
    <div class="document-form-heading">
        <div>
            <div class="documents-eyebrow">Gestión interna</div>
            <h2>Nuevo documento Bayer</h2>
            <p class="documents-description">
                Registra la información general y los productos relacionados.
            </p>
        </div>

        <a href="<?=url('/documentos')?>" class="btn btn-outline-secondary">
            Volver a Documentos
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            Revisa los campos obligatorios antes de guardar el documento.
        </div>
    <?php endif; ?>

    <form
        method="post"
        action="<?=url('/documentos/guardar')?>"
        data-documents-form
    >
        <?=csrf_field()?>

        <section class="document-section">
            <div class="document-section-header">
                <div>
                    <h3 class="document-section-title">Datos generales</h3>
                    <p class="document-section-description">
                        Información principal del documento.
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label" for="document-type-id">
                        ID tipo de documento
                    </label>

                    <input
                        id="document-type-id"
                        class="form-control"
                        type="number"
                        name="header[tipo_documento_id]"
                        min="1"
                        value="<?=e($oldHeader['tipo_documento_id'] ?? '')?>"
                        placeholder="Ejemplo: 1"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="document-number">
                        Número de documento
                    </label>

                    <input
                        id="document-number"
                        class="form-control"
                        type="text"
                        name="header[numero]"
                        maxlength="25"
                        value="<?=e($oldHeader['numero'] ?? '')?>"
                        placeholder="Ejemplo: F001-000123"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="document-date">
                        Fecha
                    </label>

                    <input
                        id="document-date"
                        class="form-control"
                        type="date"
                        name="header[fecha]"
                        value="<?=e($oldHeader['fecha'] ?? '')?>"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="client-id">
                        ID cliente
                    </label>

                    <input
                        id="client-id"
                        class="form-control"
                        type="number"
                        name="header[cliente_id]"
                        min="1"
                        value="<?=e($oldHeader['cliente_id'] ?? '')?>"
                        placeholder="ID del catálogo"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="seller-id">
                        ID vendedor
                    </label>

                    <input
                        id="seller-id"
                        class="form-control"
                        type="number"
                        name="header[vendedor_id]"
                        min="1"
                        value="<?=e($oldHeader['vendedor_id'] ?? '')?>"
                        placeholder="ID del catálogo"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="branch-id">
                        ID sucursal
                    </label>

                    <input
                        id="branch-id"
                        class="form-control"
                        type="number"
                        name="header[sucursal_id]"
                        min="1"
                        value="<?=e($oldHeader['sucursal_id'] ?? '')?>"
                        placeholder="ID del catálogo"
                        required
                    >
                </div>
            </div>
        </section>

        <section class="document-section">
            <div class="document-section-header">
                <div>
                    <h3 class="document-section-title">Detalle del documento</h3>
                    <p class="document-section-description">
                        Agrega uno o varios productos al documento.
                    </p>
                </div>

                <button
                    type="button"
                    class="btn btn-outline-primary"
                    data-add-detail
                >
                    + Agregar producto
                </button>
            </div>

            <div class="table-responsive">
                <table class="table document-details-table align-middle">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th>Cantidad</th>
                            <th>Valor unitario</th>
                            <th class="document-detail-actions">Acción</th>
                        </tr>
                    </thead>

                    <tbody data-details-body>
                        <?php foreach ($oldDetails as $index => $detail): ?>
                            <tr data-detail-row data-detail-index="<?=$index?>">
                                <td>
                                    <label class="visually-hidden">
                                        ID producto
                                    </label>

                                    <input
                                        class="form-control"
                                        type="number"
                                        name="details[<?=$index?>][producto_id]"
                                        min="1"
                                        value="<?=e($detail['producto_id'] ?? '')?>"
                                        placeholder="ID producto"
                                        required
                                    >
                                </td>

                                <td>
                                    <label class="visually-hidden">
                                        ID unidad
                                    </label>

                                    <input
                                        class="form-control"
                                        type="number"
                                        name="details[<?=$index?>][unidad_id]"
                                        min="1"
                                        value="<?=e($detail['unidad_id'] ?? '')?>"
                                        placeholder="ID unidad"
                                        required
                                    >
                                </td>

                                <td>
                                    <label class="visually-hidden">
                                        Cantidad
                                    </label>

                                    <input
                                        class="form-control"
                                        type="number"
                                        name="details[<?=$index?>][cantidad]"
                                        min="0.001"
                                        step="0.001"
                                        value="<?=e($detail['cantidad'] ?? '')?>"
                                        placeholder="0.000"
                                        required
                                    >
                                </td>

                                <td>
                                    <label class="visually-hidden">
                                        Valor unitario
                                    </label>

                                    <input
                                        class="form-control"
                                        type="number"
                                        name="details[<?=$index?>][valor_unitario]"
                                        min="0"
                                        step="0.01"
                                        value="<?=e($detail['valor_unitario'] ?? '')?>"
                                        placeholder="0.00"
                                        required
                                    >
                                </td>

                                <td class="document-detail-actions">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger document-remove-detail"
                                        data-remove-detail
                                    >
                                        Quitar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="document-help mt-3">
                Los productos, unidades, clientes, vendedores y sucursales
                deberán validarse posteriormente mediante los catálogos del backend.
            </div>
        </section>

        <div class="document-form-actions">
            <a href="<?=url('/documentos')?>" class="btn btn-outline-secondary">
                Cancelar
            </a>

            <button type="submit" class="btn btn-primary">
                Guardar borrador
            </button>
        </div>
    </form>
</div>

<script src="<?=url('/assets/js/documentos.js')?>" defer></script>

<?php
unset($_SESSION['_old'], $_SESSION['_errors']);
?>