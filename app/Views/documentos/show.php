<?php
$document = is_array($document ?? null) ? $document : [];
$details = is_array($details ?? null) ? $details : [];

$id = (int)($document['id'] ?? 0);
$status = strtoupper((string)($document['estado_registro'] ?? 'BORRADOR'));

$statusClass = match ($status) {
    'PUBLICADO' => 'bg-success',
    'VALIDADO' => 'bg-primary',
    'OBSERVADO' => 'bg-warning text-dark',
    'ANULADO' => 'bg-dark',
    default => 'bg-light text-dark border',
};
?>

<link
    rel="stylesheet"
    href="<?=url('/assets/css/documentos.css')?>"
>

<div class="document-detail-page">

    <div class="document-form-heading">
        <div>
            <div class="documents-eyebrow">
                Gestión interna
            </div>

            <h2>Detalle del documento</h2>

            <p class="documents-description">
                Consulta la información registrada y sus productos.
            </p>
        </div>

        <a
            href="<?=url('/documentos')?>"
            class="btn btn-outline-secondary"
        >
            Volver a Documentos
        </a>
    </div>

    <section class="document-section">

        <div class="document-section-header">
            <h3 class="document-section-title">
                Información general
            </h3>

            <span class="badge <?=$statusClass?>">
                <?=e($status)?>
            </span>
        </div>

        <div class="row g-4">

            <div class="col-md-4">
                <strong>Número de documento</strong>
                <div><?=e($document['numero'] ?? '-')?></div>
            </div>

            <div class="col-md-4">
                <strong>Fecha</strong>
                <div><?=e($document['fecha'] ?? '-')?></div>
            </div>

            <div class="col-md-4">
                <strong>ID tipo de documento</strong>
                <div><?=e($document['tipo_documento_id'] ?? '-')?></div>
            </div>

            <div class="col-md-4">
                <strong>Cliente</strong>
                <div>
                    <?=e($document['cliente'] ?? $document['cliente_id'] ?? '-')?>
                </div>
            </div>

            <div class="col-md-4">
                <strong>Vendedor</strong>
                <div>
                    <?=e($document['vendedor'] ?? $document['vendedor_id'] ?? '-')?>
                </div>
            </div>

            <div class="col-md-4">
                <strong>Sucursal</strong>
                <div>
                    <?=e($document['sucursal'] ?? $document['sucursal_id'] ?? '-')?>
                </div>
            </div>

        </div>

    </section>

    <section class="document-section">

        <div class="document-section-header">
            <h3 class="document-section-title">
                Productos registrados
            </h3>
        </div>

        <?php if (!$details): ?>

            <div class="alert alert-secondary">
                Este documento no tiene productos registrados.
            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table align-middle document-details-table">

                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th>Cantidad</th>
                            <th>Valor unitario</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($details as $detail): ?>
                            <tr>
                                <td>
                                    <?=e($detail['producto'] ?? $detail['producto_id'] ?? '-')?>
                                </td>

                                <td>
                                    <?=e($detail['unidad'] ?? $detail['unidad_id'] ?? '-')?>
                                </td>

                                <td>
                                    <?=e($detail['cantidad'] ?? '-')?>
                                </td>

                                <td>
                                    <?=e($detail['valor_unitario'] ?? '-')?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <section
        class="document-section document-workflow-section"
        data-document-id="<?=e($id)?>"
        data-document-status="<?=e($status)?>"
    >

        <div class="document-section-header">
            <div>
                <h3 class="document-section-title">
                    Acciones del flujo
                </h3>

                <p class="document-section-description">
                    Acciones disponibles según el estado del documento.
                </p>
            </div>
        </div>

        <?php if (has_role('ADMIN', 'SUPERVISOR')): ?>

            <div class="d-flex flex-wrap gap-2">

                <?php if ($status === 'BORRADOR'): ?>
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-document-action="validar"
                    >
                        Validar
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline-warning"
                        data-document-action="observar"
                    >
                        Observar
                    </button>
                <?php endif; ?>

                <?php if ($status === 'VALIDADO'): ?>
                    <button
                        type="button"
                        class="btn btn-outline-warning"
                        data-document-action="observar"
                    >
                        Observar
                    </button>

                    <button
                        type="button"
                        class="btn btn-success"
                        data-document-action="publicar"
                    >
                        Publicar
                    </button>
                <?php endif; ?>

                <?php if ($status === 'OBSERVADO'): ?>
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        data-document-action="devolver"
                    >
                        Devolver a borrador
                    </button>
                <?php endif; ?>

                <?php if ($status === 'PUBLICADO'): ?>
                    <span class="text-success">
                        Este documento ya fue publicado y no tiene acciones disponibles.
                    </span>
                <?php endif; ?>

            </div>

        <?php else: ?>

            <div class="alert alert-secondary mb-0">
                No tienes permisos para modificar el estado de este documento.
            </div>

        <?php endif; ?>

    </section>

    <div class="document-form-actions">

        <a
            href="<?=url('/documentos')?>"
            class="btn btn-outline-secondary"
        >
            Regresar
        </a>

        <?php if ($status === 'BORRADOR' && $id > 0): ?>
            <a
                href="<?=url('/documentos/editar?id='.$id)?>"
                class="btn btn-primary"
            >
                Editar borrador
            </a>
        <?php endif; ?>

    </div>

</div>