<?php
$document = is_array($document ?? null) ? $document : [];
$details = is_array($details ?? null) ? $details : [];

if (!$details) {
    $details = [[]];
}

$id = (int)($document['id'] ?? 0);
$status = strtoupper((string)($document['estado_registro'] ?? 'BORRADOR'));
$locked = $status !== 'BORRADOR';
$readonly = $locked ? 'readonly' : '';

$statusClass = match ($status) {
    'PUBLICADO' => 'bg-success',
    'VALIDADO' => 'bg-primary',
    'OBSERVADO' => 'bg-warning text-dark',
    default => 'bg-secondary',
};
?>

<link
    rel="stylesheet"
    href="<?=url('/assets/css/documentos.css')?>"
>

<div class="document-form-page">

    <div class="document-form-heading">
        <div>
            <div class="documents-eyebrow">
                Gestión interna
            </div>

            <h2>Editar documento Bayer</h2>

            <p class="documents-description">
                Modifica la información del documento según su estado.
            </p>
        </div>

        <span class="badge <?=$statusClass?>">
            <?=e($status)?>
        </span>
    </div>

    <?php if ($locked): ?>
        <div class="alert alert-warning">
            Este documento no se puede editar porque no está en estado
            BORRADOR.
        </div>
    <?php endif; ?>

    <form
        method="post"
        action="<?=url('/documentos/actualizar')?>"
        data-documents-form
    >
        <?=csrf_field()?>

        <input
            type="hidden"
            name="document_id"
            value="<?=e($id)?>"
        >

        <section class="document-section">

            <div class="document-section-header">
                <h3 class="document-section-title">
                    Datos generales
                </h3>
            </div>

            <div class="row g-4">

                <div class="col-md-4">
                    <label class="form-label">
                        ID tipo de documento
                    </label>

                    <input
                        class="form-control"
                        type="number"
                        name="header[tipo_documento_id]"
                        value="<?=e($document['tipo_documento_id'] ?? '')?>"
                        <?=$readonly?>
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Número de documento
                    </label>

                    <input
                        class="form-control"
                        type="text"
                        name="header[numero]"
                        value="<?=e($document['numero'] ?? '')?>"
                        <?=$readonly?>
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Fecha
                    </label>

                    <input
                        class="form-control"
                        type="date"
                        name="header[fecha]"
                        value="<?=e($document['fecha'] ?? '')?>"
                        <?=$readonly?>
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        ID cliente
                    </label>

                    <input
                        class="form-control"
                        type="number"
                        name="header[cliente_id]"
                        value="<?=e($document['cliente_id'] ?? '')?>"
                        <?=$readonly?>
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        ID vendedor
                    </label>

                    <input
                        class="form-control"
                        type="number"
                        name="header[vendedor_id]"
                        value="<?=e($document['vendedor_id'] ?? '')?>"
                        <?=$readonly?>
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        ID sucursal
                    </label>

                    <input
                        class="form-control"
                        type="number"
                        name="header[sucursal_id]"
                        value="<?=e($document['sucursal_id'] ?? '')?>"
                        <?=$readonly?>
                    >
                </div>

            </div>

        </section>

        <section class="document-section">

            <div class="document-section-header">
                <h3 class="document-section-title">
                    Productos del documento
                </h3>

                <?php if (!$locked): ?>
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        data-add-detail
                    >
                        + Agregar producto
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-responsive">

                <table class="table document-details-table align-middle">

                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th>Cantidad</th>
                            <th>Valor unitario</th>
                            <th>Acción</th>
                        </tr>
                    </thead>

                    <tbody data-details-body">

                        <?php foreach ($details as $index => $detail): ?>
                            <tr
                                data-detail-row
                                data-detail-index="<?=$index?>"
                            >

                                <td>
                                    <input
                                        class="form-control"
                                        type="number"
                                        name="details[<?=$index?>][producto_id]"
                                        value="<?=e($detail['producto_id'] ?? '')?>"
                                        <?=$readonly?>
                                    >
                                </td>

                                <td>
                                    <input
                                        class="form-control"
                                        type="number"
                                        name="details[<?=$index?>][unidad_id]"
                                        value="<?=e($detail['unidad_id'] ?? '')?>"
                                        <?=$readonly?>
                                    >
                                </td>

                                <td>
                                    <input
                                        class="form-control"
                                        type="number"
                                        name="details[<?=$index?>][cantidad]"
                                        min="0.001"
                                        step="0.001"
                                        value="<?=e($detail['cantidad'] ?? '')?>"
                                        <?=$readonly?>
                                    >
                                </td>

                                <td>
                                    <input
                                        class="form-control"
                                        type="number"
                                        name="details[<?=$index?>][valor_unitario]"
                                        min="0"
                                        step="0.01"
                                        value="<?=e($detail['valor_unitario'] ?? '')?>"
                                        <?=$readonly?>
                                    >
                                </td>

                                <td>
                                    <?php if (!$locked): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-remove-detail
                                        >
                                            Quitar
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            Bloqueado
                                        </span>
                                    <?php endif; ?>
                                </td>

                            </tr>
                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <div class="document-form-actions">

            <a
                href="<?=url('/documentos')?>"
                class="btn btn-outline-secondary"
            >
                Cancelar
            </a>

            <?php if (!$locked): ?>
                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Guardar cambios
                </button>
            <?php endif; ?>

        </div>

    </form>

</div>

<script
    src="<?=url('/assets/js/documentos.js')?>"
    defer
></script>