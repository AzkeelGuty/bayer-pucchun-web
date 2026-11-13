<?php $back='/homologaciones?tab='.rawurlencode($tab); ?>
<section class="module-header"><div>
  <div class="page-eyebrow">HOMOLOGACIONES BAYER</div>
  <h1 class="page-title">Importación masiva · <?=e($meta['title'])?></h1>
  <p class="page-subtitle">Carga o actualiza homologaciones por código de partner y código interno en un único Excel.</p>
</div><a class="btn btn-outline-primary" href="<?=url($back)?>">Volver al listado</a></section>
<div class="row g-4">
  <div class="col-xl-5"><div class="card h-100"><div class="card-body p-4">
    <h2 class="h5">1. Descargar plantilla</h2>
    <p>Utilice los encabezados originales de la plantilla; una fila representa una homologación.</p>
    <a class="btn btn-outline-primary w-100 mb-4" href="<?=url('/homologaciones/plantilla?tab='.rawurlencode($tab))?>" data-native-navigation>Descargar plantilla XLSX</a>
    <h2 class="h5">2. Completar los datos</h2>
    <p>El código del partner y el código interno deben existir en el sistema. Las combinaciones existentes se actualizarán sin duplicarse.</p>
    <h2 class="h5">3. Importar a la base de datos</h2>
    <?php if($tab==='productos'): ?>
    <div class="alert alert-info mb-3" role="note">
      <strong>¿La importación indica que el código interno no existe?</strong>
      Puede registrar los productos ausentes al mismo tiempo que carga las homologaciones. Se crearán inactivos para que revise la unidad base y los datos del producto antes de utilizarlos.
    </div>
    <?php endif; ?>
    <form action="<?=url('/homologaciones/importar')?>" method="post" enctype="multipart/form-data">
      <?=csrf_field()?>
      <input type="hidden" name="tab" value="<?=e($tab)?>">
      <label class="form-label" for="homologation-xlsx">Archivo Excel (.xlsx)</label>
      <input id="homologation-xlsx" class="form-control" type="file" name="excel" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
      <div class="form-text">Máximo 8 MB y 2000 registros. Si alguna fila falla, no se guarda ninguna.</div>
      <?php if($tab==='productos'): ?>
      <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" id="registrar-productos-faltantes"
            name="registrar_productos_faltantes" value="1">
        <label class="form-check-label fw-semibold" for="registrar-productos-faltantes">
          Registrar automáticamente productos con código interno faltante
        </label>
        <div class="form-text">
          Se utilizarán <code>codigo_interno</code> y <code>nombre_bayer</code> del Excel.
          Los productos se crean inactivos, sin inventar unidades ni marcas.
          Si <code>unidad_codigo</code> contiene un código válido, se asigna como unidad inicial; de lo contrario, se revisa posteriormente en Catálogos maestros.
          Esta opción no modifica productos que ya existen.
        </div>
      </div>
      <?php endif; ?>
      <button class="btn btn-success w-100 mt-3" type="submit">Importar homologaciones</button>
    </form>
  </div></div></div>
  <div class="col-xl-7"><div class="card h-100"><div class="card-body p-4">
    <h2 class="h5">Columnas de la plantilla</h2>
    <div class="table-responsive"><table class="table app-table align-middle">
      <thead><tr><th>Campo</th><th>Obligatorio</th><th>Descripción</th><th>Ejemplo</th></tr></thead>
      <tbody>
      <?php foreach($meta['import'] as $key=>$field): ?>
        <tr><td><code><?=e($key)?></code></td><td><?=!empty($field['required'])?'Sí':'No'?></td><td><?=e($field['description'])?></td><td><?=e($field['example'])?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="form-text">Los identificadores externos se conservan tal como aparecen en el Excel. No se crean partners ni clientes. En Productos Bayer solo se crean registros maestros faltantes si se activa expresamente la opción de registro, y quedan inactivos hasta su revisión.</p>
  </div></div></div>
</div>
