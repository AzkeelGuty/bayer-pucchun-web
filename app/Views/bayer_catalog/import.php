<section class="module-header"><div><div class="page-eyebrow">BAYER · CARGA DE DATOS</div>
  <h1 class="page-title">Importar catálogo e inventario Bayer</h1>
  <p class="page-subtitle">Sube el archivo de inventario original de Bayer, sin copiar los productos a otra plantilla.</p></div>
  <a class="btn btn-outline-primary" href="<?=url('/homologaciones?tab=productos')?>">Volver a Productos Bayer</a>
</section>
<div class="row g-3">
 <div class="col-xl-6">
  <form class="card h-100" method="post" enctype="multipart/form-data" action="<?=url('/bayer-productos/importar')?>">
   <?=csrf_field()?>
   <div class="card-body p-4">
    <h2 class="h5">Archivo original XLSX</h2>
    <p>El sistema busca automáticamente las columnas <strong>CODIGO</strong>, <strong>PRODUCTO / PRESENTACION</strong>,
    <strong>MARCA</strong> y <strong>CANTIDAD / UNIDADES</strong>, aunque estén después de la portada o en varias hojas.</p>
    <label class="form-label" for="file-bayer">Reporte Excel Bayer</label>
    <input id="file-bayer" class="form-control" type="file" name="excel" accept=".xlsx" required>
    <div class="form-text mb-3">Hasta 12 MB y 10000 filas. No cambies los nombres de las columnas del reporte Bayer.</div>
    <label class="form-label" for="bayer-periodo">Mes del inventario (solo si el archivo no lo identifica)</label>
    <input id="bayer-periodo" class="form-control" name="periodo" type="month" placeholder="AAAA-MM">
    <div class="form-text">Se usa el año/mes de cada fila o pestaña cuando estén presentes. No se inventan periodos.</div>
    <button class="btn btn-success btn-with-icon mt-3" type="submit">
      <i class="bi bi-file-earmark-arrow-up"></i> Importar reporte Bayer
    </button>
   </div>
  </form>
 </div>
 <div class="col-xl-6">
  <div class="card h-100"><div class="card-body p-4">
    <h2 class="h5">Cómo interpreta las cantidades</h2>
    <table class="table table-sm align-middle">
      <thead><tr><th>Celda</th><th>Resultado Bayer</th></tr></thead>
      <tbody><tr><td>274</td><td>Con stock (274)</td></tr>
       <tr><td>0</td><td>Sin stock (0)</td></tr>
       <tr><td>Vacío</td><td>Sin dato; se conserva vacío</td></tr>
       <tr><td>— o -</td><td>Desactivado; no se considera cero</td></tr></tbody>
    </table>
    <p class="small text-muted">El inventario Bayer y el stock físico de Pucchún se almacenan por separado.
    La importación no añade cantidades a almacenes Pucchún ni sobreescribe sus registros.</p>
    <p class="small text-muted">Los cambios quedan pendientes de publicación en API hasta su revisión por un administrador.
    No se crearán códigos Bayer inventados.</p>
    <a class="btn btn-outline-primary" data-native-navigation href="<?=url('/bayer-productos/plantilla')?>">
       <i class="bi bi-download"></i> Plantilla simple opcional (5 columnas)
    </a>
   </div></div>
 </div>
</div>
