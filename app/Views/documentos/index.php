<?php
$rows = $rows ?? [];

$cols = [
    'numero' => 'Número',
    'fecha' => 'Fecha',
    'cliente' => 'Cliente',
    'vendedor' => 'Vendedor',
    'sucursal' => 'Sucursal',
    'cantidad' => 'Cantidad',
];
?>

<link rel="stylesheet" href="<?=url('/assets/css/documentos.css')?>">

<div class="documents-page" data-documents-page>

    <div class="documents-heading">
        <div>
            <div class="documents-eyebrow">Gestión interna</div>

            <h2>Documentos Bayer</h2>

            <p class="documents-description">
                Administra documentos, detalles y estados del flujo de validación.
            </p>
        </div>

        <a
            class="btn btn-primary"
            href="<?=url('/documentos/nuevo')?>"
        >
            Nuevo documento
        </a>
    </div>

    <div class="documents-filters">

        <div>
            <label for="document-search">
                Buscar documento
            </label>

            <input
                id="document-search"
                type="search"
                class="form-control"
                placeholder="Número, cliente, vendedor o sucursal"
                data-document-search
            >
        </div>

        <div>
            <label for="document-status">
                Estado
            </label>

            <select
                id="document-status"
                class="form-select"
                data-document-status
            >
                <option value="">Todos</option>
                <option value="BORRADOR">Borrador</option>
                <option value="VALIDADO">Validado</option>
                <option value="OBSERVADO">Observado</option>
                <option value="PUBLICADO">Publicado</option>
                <option value="ANULADO">Anulado</option>
            </select>
        </div>

        <button
            type="button"
            class="btn btn-outline-secondary"
            data-clear-documents
        >
            Limpiar
        </button>

    </div>

    <div class="documents-table-card">

        <div class="table-responsive">

            <table class="table documents-table align-middle">

                <thead>
                    <tr>
                        <?php foreach ($cols as $label): ?>
                            <th><?=e($label)?></th>
                        <?php endforeach; ?>

                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!$rows): ?>

                        <tr data-empty-row>
                            <td
                                colspan="<?=count($cols) + 2?>"
                                class="documents-empty"
                            >
                                <strong>
                                    No hay documentos registrados
                                </strong>

                                Todavía no existen documentos para mostrar.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($rows as $r): ?>

                            <?php
                            $status = strtoupper(
                                (string)($r['estado_registro'] ?? '')
                            );

                            $statusLabel = $status !== ''
                                ? $status
                                : 'SIN ESTADO';

                            $searchText = strtolower(implode(' ', [
                                $r['numero'] ?? '',
                                $r['cliente'] ?? '',
                                $r['vendedor'] ?? '',
                                $r['sucursal'] ?? '',
                            ]));
                            ?>

                            <tr
                                data-document-row
                                data-search="<?=e($searchText)?>"
                                data-status="<?=e($status)?>"
                            >

                                <?php foreach (array_keys($cols) as $column): ?>
                                    <td>
                                        <?=e($r[$column] ?? '')?>
                                    </td>
                                <?php endforeach; ?>

                                <td>
                                    <span
                                        class="documents-status documents-status--<?=e(strtolower($status ?: 'sin-estado'))?>"
                                    >
                                        <?=e($statusLabel)?>
                                    </span>
                                </td>

                                <td class="documents-actions">

                                    <div class="d-flex flex-wrap gap-2 align-items-center">

                                        <a
                                            href="<?=url('/documentos/ver?id='.(int)($r['id'] ?? 0))?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Ver
                                        </a>

                                        <?php if (has_role('ADMIN', 'SUPERVISOR')): ?>

                                            <form
                                                method="post"
                                                action="<?=url('/documentos/estado')?>"
                                                class="d-flex gap-1"
                                            >
                                                <?=csrf_field()?>

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?=e($r['id'] ?? '')?>"
                                                >

                                                <select
                                                    name="status"
                                                    class="form-select form-select-sm"
                                                >
                                                    <?php foreach (
                                                        ['BORRADOR', 'VALIDADO', 'PUBLICADO']
                                                        as $option
                                                    ): ?>

                                                        <option
                                                            value="<?=$option?>"
                                                            <?=$status === $option
                                                                ? 'selected'
                                                                : ''?>
                                                        >
                                                            <?=$option?>
                                                        </option>

                                                    <?php endforeach; ?>
                                                </select>

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-success"
                                                >
                                                    Aplicar
                                                </button>
                                            </form>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                Solo lectura
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <tr
                            data-empty-row
                            class="d-none"
                        >
                            <td
                                colspan="<?=count($cols) + 2?>"
                                class="documents-empty"
                            >
                                <strong>
                                    No se encontraron resultados
                                </strong>

                                Prueba con otro texto o estado.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script
    src="<?=url('/assets/js/documentos.js')?>"
    defer
></script>