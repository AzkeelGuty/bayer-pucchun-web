-- DATOS DE EJEMPLO PARA PROBAR LOS MODULOS DEL SISTEMA.
-- Usar únicamente en un entorno de pruebas. No ejecutar en producción.
-- Requiere haber importado antes:
--   database/schemas/002_schema_v2.sql
--   database/seeders/001_seed.sql
--   database/seeders/002_usuarios_prueba.sql

START TRANSACTION;

SET @admin_id := (SELECT id FROM usuarios WHERE email='admin@pucchun.pe' LIMIT 1);
SET @digitador_id := (SELECT id FROM usuarios WHERE email='digitacion@pucchun.pe' LIMIT 1);
SET @supervisor_id := (SELECT id FROM usuarios WHERE email='supervision@pucchun.pe' LIMIT 1);
SET @gerencia_id := (SELECT id FROM usuarios WHERE email='gerencia@pucchun.pe' LIMIT 1);
SET @bayer_id := (SELECT id FROM usuarios WHERE email='consulta@bayer.pe' LIMIT 1);

-- 1. UBIGEO Y EMPRESA
INSERT INTO departamentos(nombre)
SELECT 'Ica' WHERE NOT EXISTS (SELECT 1 FROM departamentos WHERE nombre='Ica');
SET @dep_ica := (SELECT id FROM departamentos WHERE nombre='Ica' LIMIT 1);

INSERT INTO provincias(departamento_id,nombre)
SELECT @dep_ica,'Chincha'
WHERE NOT EXISTS (SELECT 1 FROM provincias WHERE departamento_id=@dep_ica AND nombre='Chincha');
SET @prov_chincha := (SELECT id FROM provincias WHERE departamento_id=@dep_ica AND nombre='Chincha' LIMIT 1);

INSERT INTO distritos(provincia_id,nombre)
SELECT @prov_chincha,'Chincha Alta'
WHERE NOT EXISTS (SELECT 1 FROM distritos WHERE provincia_id=@prov_chincha AND nombre='Chincha Alta');
INSERT INTO distritos(provincia_id,nombre)
SELECT @prov_chincha,'Grocio Prado'
WHERE NOT EXISTS (SELECT 1 FROM distritos WHERE provincia_id=@prov_chincha AND nombre='Grocio Prado');

SET @dist_chincha := (SELECT id FROM distritos WHERE provincia_id=@prov_chincha AND nombre='Chincha Alta' LIMIT 1);
SET @dist_grocio := (SELECT id FROM distritos WHERE provincia_id=@prov_chincha AND nombre='Grocio Prado' LIMIT 1);

INSERT INTO empresas(ruc,razon_social,nombre_comercial,estado)
VALUES('20609990001','Pucchún Agro S.A.C.','Pucchún',1)
ON DUPLICATE KEY UPDATE razon_social=VALUES(razon_social),nombre_comercial=VALUES(nombre_comercial),estado=1;
SET @empresa_id := (SELECT id FROM empresas WHERE ruc='20609990001' LIMIT 1);

INSERT INTO sucursales(empresa_id,codigo,nombre,direccion,distrito_id,estado)
VALUES
(@empresa_id,'PUC-CHI','Sede Chincha','Av. Principal 520 - Chincha Alta',@dist_chincha,1),
(@empresa_id,'PUC-GRO','Centro Operativo Grocio Prado','Carretera Panamericana Sur km 198',@dist_grocio,1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),direccion=VALUES(direccion),distrito_id=VALUES(distrito_id),estado=1;

SET @suc_chincha := (SELECT id FROM sucursales WHERE codigo='PUC-CHI' LIMIT 1);
SET @suc_grocio := (SELECT id FROM sucursales WHERE codigo='PUC-GRO' LIMIT 1);

INSERT INTO almacenes(sucursal_id,codigo,nombre,tipo,estado)
VALUES
(@suc_chincha,'ALM-CHI-01','Almacén Principal Chincha','PRINCIPAL',1),
(@suc_grocio,'ALM-GRO-01','Almacén Grocio Prado','OPERATIVO',1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),tipo=VALUES(tipo),estado=1;

SET @alm_chincha := (SELECT id FROM almacenes WHERE codigo='ALM-CHI-01' LIMIT 1);
SET @alm_grocio := (SELECT id FROM almacenes WHERE codigo='ALM-GRO-01' LIMIT 1);

-- 2. MAESTROS
INSERT INTO unidades_medida(codigo,nombre,abreviatura,factor_base) VALUES
('UND','Unidad','und',1.0000),
('CAJ','Caja','caj',1.0000),
('LIT','Litro','L',1.0000),
('KGM','Kilogramo','kg',1.0000)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),abreviatura=VALUES(abreviatura),factor_base=VALUES(factor_base);

SET @und := (SELECT id FROM unidades_medida WHERE codigo='UND' LIMIT 1);
SET @caj := (SELECT id FROM unidades_medida WHERE codigo='CAJ' LIMIT 1);
SET @lit := (SELECT id FROM unidades_medida WHERE codigo='LIT' LIMIT 1);
SET @kg := (SELECT id FROM unidades_medida WHERE codigo='KGM' LIMIT 1);

INSERT INTO categorias_producto(nombre,descripcion)
SELECT 'Nutrición vegetal','Productos para nutrición y desarrollo del cultivo'
WHERE NOT EXISTS (SELECT 1 FROM categorias_producto WHERE nombre='Nutrición vegetal');
INSERT INTO categorias_producto(nombre,descripcion)
SELECT 'Semillas','Semillas y material de siembra'
WHERE NOT EXISTS (SELECT 1 FROM categorias_producto WHERE nombre='Semillas');
INSERT INTO categorias_producto(nombre,descripcion)
SELECT 'Protección de cultivos','Productos para manejo y protección agrícola'
WHERE NOT EXISTS (SELECT 1 FROM categorias_producto WHERE nombre='Protección de cultivos');

SET @cat_nut := (SELECT id FROM categorias_producto WHERE nombre='Nutrición vegetal' ORDER BY id LIMIT 1);
SET @cat_sem := (SELECT id FROM categorias_producto WHERE nombre='Semillas' ORDER BY id LIMIT 1);
SET @cat_pro := (SELECT id FROM categorias_producto WHERE nombre='Protección de cultivos' ORDER BY id LIMIT 1);

INSERT INTO marcas(nombre,fabricante)
SELECT 'Pucchún Selección','Pucchún Agro'
WHERE NOT EXISTS (SELECT 1 FROM marcas WHERE nombre='Pucchún Selección');
INSERT INTO marcas(nombre,fabricante)
SELECT 'Bayer','Bayer'
WHERE NOT EXISTS (SELECT 1 FROM marcas WHERE nombre='Bayer');

SET @marca_puc := (SELECT id FROM marcas WHERE nombre='Pucchún Selección' ORDER BY id LIMIT 1);
SET @marca_bayer := (SELECT id FROM marcas WHERE nombre='Bayer' ORDER BY id LIMIT 1);

INSERT INTO productos(codigo,nombre,categoria_id,marca_id,unidad_base_id,estado) VALUES
('PRD-001','Bioestimulante foliar 1 L',@cat_nut,@marca_puc,@lit,1),
('PRD-002','Semilla de maíz híbrido 20 kg',@cat_sem,@marca_bayer,@kg,1),
('PRD-003','Fertilizante soluble 5 kg',@cat_nut,@marca_puc,@kg,1),
('PRD-004','Protector agrícola 1 L',@cat_pro,@marca_bayer,@lit,1),
('PRD-005','Adyuvante agrícola 500 ml',@cat_pro,@marca_puc,@und,1)
ON DUPLICATE KEY UPDATE
nombre=VALUES(nombre),categoria_id=VALUES(categoria_id),marca_id=VALUES(marca_id),unidad_base_id=VALUES(unidad_base_id),estado=1;

SET @p1 := (SELECT id FROM productos WHERE codigo='PRD-001' LIMIT 1);
SET @p2 := (SELECT id FROM productos WHERE codigo='PRD-002' LIMIT 1);
SET @p3 := (SELECT id FROM productos WHERE codigo='PRD-003' LIMIT 1);
SET @p4 := (SELECT id FROM productos WHERE codigo='PRD-004' LIMIT 1);
SET @p5 := (SELECT id FROM productos WHERE codigo='PRD-005' LIMIT 1);

INSERT INTO clientes(codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id) VALUES
('CLI-001','06','20609990011','Agrícola Valle Verde S.A.C.',@dep_ica,@prov_chincha,@dist_chincha),
('CLI-002','06','20609990022','Campos del Sur S.A.C.',@dep_ica,@prov_chincha,@dist_grocio),
('CLI-003','01','48751236','José Mendoza Castro',@dep_ica,@prov_chincha,@dist_chincha)
ON DUPLICATE KEY UPDATE
codigo=VALUES(codigo),razon_social=VALUES(razon_social),departamento_id=VALUES(departamento_id),provincia_id=VALUES(provincia_id),distrito_id=VALUES(distrito_id);

SET @cli1 := (SELECT id FROM clientes WHERE nro_doc='20609990011' LIMIT 1);
SET @cli2 := (SELECT id FROM clientes WHERE nro_doc='20609990022' LIMIT 1);
SET @cli3 := (SELECT id FROM clientes WHERE nro_doc='48751236' LIMIT 1);

INSERT INTO vendedores(codigo,nombres,apellidos,email,estado) VALUES
('VEN-001','Luis Alberto','Ramírez Soto','lramirez@pucchun.pe',1),
('VEN-002','Rosa Elena','Campos Díaz','rcampos@pucchun.pe',1)
ON DUPLICATE KEY UPDATE nombres=VALUES(nombres),apellidos=VALUES(apellidos),email=VALUES(email),estado=1;

SET @ven1 := (SELECT id FROM vendedores WHERE codigo='VEN-001' LIMIT 1);
SET @ven2 := (SELECT id FROM vendedores WHERE codigo='VEN-002' LIMIT 1);

INSERT INTO tipos_documento(codigo,nombre,sunat_code) VALUES
('FAC','Factura','01'),
('BOL','Boleta','03'),
('PED','Pedido interno',NULL)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),sunat_code=VALUES(sunat_code);

SET @fac := (SELECT id FROM tipos_documento WHERE codigo='FAC' LIMIT 1);
SET @bol := (SELECT id FROM tipos_documento WHERE codigo='BOL' LIMIT 1);

INSERT INTO partners(codigo,nombre,tipo)
VALUES('BAYER','Bayer','FABRICANTE')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),tipo=VALUES(tipo);
SET @partner_bayer := (SELECT id FROM partners WHERE codigo='BAYER' LIMIT 1);

-- 3. HOMOLOGACIONES BAYER
INSERT INTO homologacion_productos_bayer(partner_id,producto_id,material_id,material_name,unidad_bayer_id,estado,valid_from,created_by)
VALUES
(@partner_bayer,@p1,'BAY-MAT-1001','Bioestimulante foliar 1 L',@lit,1,'2026-01-01',@admin_id),
(@partner_bayer,@p2,'BAY-MAT-1002','Semilla maíz híbrido 20 kg',@kg,1,'2026-01-01',@admin_id),
(@partner_bayer,@p4,'BAY-MAT-1004','Protector agrícola 1 L',@lit,1,'2026-01-01',@admin_id)
ON DUPLICATE KEY UPDATE material_id=VALUES(material_id),material_name=VALUES(material_name),unidad_bayer_id=VALUES(unidad_bayer_id),estado=1,valid_from=VALUES(valid_from);

INSERT INTO homologacion_clientes(partner_id,cliente_id,customer_id,customer_name,estado,valid_from,created_by)
VALUES
(@partner_bayer,@cli1,'BAY-CUS-2001','Agrícola Valle Verde',1,'2026-01-01',@admin_id),
(@partner_bayer,@cli2,'BAY-CUS-2002','Campos del Sur',1,'2026-01-01',@admin_id)
ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id),customer_name=VALUES(customer_name),estado=1,valid_from=VALUES(valid_from);

INSERT INTO homologacion_unidades(partner_id,unidad_id,external_unit_code,external_unit_name,estado,valid_from,created_by)
VALUES
(@partner_bayer,@und,'EA','Each',1,'2026-01-01',@admin_id),
(@partner_bayer,@lit,'L','Liter',1,'2026-01-01',@admin_id),
(@partner_bayer,@kg,'KG','Kilogram',1,'2026-01-01',@admin_id)
ON DUPLICATE KEY UPDATE external_unit_code=VALUES(external_unit_code),external_unit_name=VALUES(external_unit_name),estado=1,valid_from=VALUES(valid_from);

INSERT INTO homologacion_sucursales(partner_id,sucursal_id,branch_id,branch_name,estado,valid_from,created_by)
VALUES
(@partner_bayer,@suc_chincha,'BR-CHI','Pucchún Chincha',1,'2026-01-01',@admin_id),
(@partner_bayer,@suc_grocio,'BR-GRO','Pucchún Grocio Prado',1,'2026-01-01',@admin_id)
ON DUPLICATE KEY UPDATE branch_id=VALUES(branch_id),branch_name=VALUES(branch_name),estado=1,valid_from=VALUES(valid_from);

INSERT INTO homologacion_almacenes(partner_id,almacen_id,warehouse_id,warehouse_name,estado,valid_from,created_by)
VALUES
(@partner_bayer,@alm_chincha,'WH-CHI-01','Warehouse Chincha',1,'2026-01-01',@admin_id),
(@partner_bayer,@alm_grocio,'WH-GRO-01','Warehouse Grocio Prado',1,'2026-01-01',@admin_id)
ON DUPLICATE KEY UPDATE warehouse_id=VALUES(warehouse_id),warehouse_name=VALUES(warehouse_name),estado=1,valid_from=VALUES(valid_from);

INSERT INTO homologacion_vendedores(partner_id,vendedor_id,sales_id,sales_name,estado,valid_from,created_by)
VALUES
(@partner_bayer,@ven1,'SAL-001','Luis Ramírez',1,'2026-01-01',@admin_id),
(@partner_bayer,@ven2,'SAL-002','Rosa Campos',1,'2026-01-01',@admin_id)
ON DUPLICATE KEY UPDATE sales_id=VALUES(sales_id),sales_name=VALUES(sales_name),estado=1,valid_from=VALUES(valid_from);

-- 4. LOTES
INSERT INTO lotes(producto_id,codigo_lote,fecha_vencimiento,estado) VALUES
(@p1,'L-260901-A','2027-03-31',1),
(@p2,'L-260815-M','2027-08-31',1),
(@p3,'L-260920-F','2027-06-30',1),
(@p4,'L-260905-P','2027-02-28',1)
ON DUPLICATE KEY UPDATE fecha_vencimiento=VALUES(fecha_vencimiento),estado=1;

SET @l1 := (SELECT id FROM lotes WHERE producto_id=@p1 AND codigo_lote='L-260901-A' LIMIT 1);
SET @l2 := (SELECT id FROM lotes WHERE producto_id=@p2 AND codigo_lote='L-260815-M' LIMIT 1);
SET @l3 := (SELECT id FROM lotes WHERE producto_id=@p3 AND codigo_lote='L-260920-F' LIMIT 1);
SET @l4 := (SELECT id FROM lotes WHERE producto_id=@p4 AND codigo_lote='L-260905-P' LIMIT 1);

-- 5. DOCUMENTOS: PUBLICADO, VALIDADO, BORRADOR Y OBSERVADO
INSERT INTO documentos_cabecera(tipo_documento_id,numero,fecha,cliente_id,vendedor_id,sucursal_id,estado_registro,created_by,created_at,published_at,validated_at,validated_by,published_by,observation_reason)
VALUES
(@fac,'F001-000125','2026-09-05',@cli1,@ven1,@suc_chincha,'PUBLICADO',@digitador_id,'2026-09-05 09:15:00','2026-09-05 10:20:00','2026-09-05 10:05:00',@supervisor_id,@supervisor_id,NULL),
(@fac,'F001-000126','2026-09-08',@cli2,@ven2,@suc_chincha,'VALIDADO',@digitador_id,'2026-09-08 11:10:00',NULL,'2026-09-08 12:00:00',@supervisor_id,NULL,NULL),
(@bol,'B001-000321','2026-09-10',@cli3,@ven1,@suc_grocio,'BORRADOR',@digitador_id,'2026-09-10 15:30:00',NULL,NULL,NULL,NULL,NULL),
(@fac,'F001-000127','2026-09-11',@cli1,@ven2,@suc_chincha,'OBSERVADO',@digitador_id,'2026-09-11 08:25:00',NULL,NULL,NULL,NULL,'Revisar unidad y cantidad del segundo ítem.')
ON DUPLICATE KEY UPDATE
fecha=VALUES(fecha),cliente_id=VALUES(cliente_id),vendedor_id=VALUES(vendedor_id),sucursal_id=VALUES(sucursal_id),estado_registro=VALUES(estado_registro),
published_at=VALUES(published_at),validated_at=VALUES(validated_at),validated_by=VALUES(validated_by),published_by=VALUES(published_by),observation_reason=VALUES(observation_reason);

SET @doc_pub := (SELECT id FROM documentos_cabecera WHERE tipo_documento_id=@fac AND numero='F001-000125' LIMIT 1);
SET @doc_val := (SELECT id FROM documentos_cabecera WHERE tipo_documento_id=@fac AND numero='F001-000126' LIMIT 1);
SET @doc_bor := (SELECT id FROM documentos_cabecera WHERE tipo_documento_id=@bol AND numero='B001-000321' LIMIT 1);
SET @doc_obs := (SELECT id FROM documentos_cabecera WHERE tipo_documento_id=@fac AND numero='F001-000127' LIMIT 1);

DELETE FROM documentos_detalle WHERE documento_id IN (@doc_pub,@doc_val,@doc_bor,@doc_obs);
INSERT INTO documentos_detalle(documento_id,producto_id,unidad_id,cantidad,valor_unitario) VALUES
(@doc_pub,@p1,@lit,40.000,86.50),
(@doc_pub,@p2,@kg,120.000,18.90),
(@doc_val,@p3,@kg,75.000,24.80),
(@doc_val,@p4,@lit,32.000,112.00),
(@doc_bor,@p5,@und,16.000,42.50),
(@doc_obs,@p1,@lit,20.000,86.50),
(@doc_obs,@p4,@lit,8.000,112.00);

-- 6. GUÍAS DE REMISIÓN
INSERT INTO guias_cabecera(numero,fecha,cliente_id,vendedor_id,sucursal_id,departamento_id,provincia_id,distrito_id,estado_registro,created_by,created_at,published_at,validated_at,validated_by,published_by,observation_reason)
VALUES
('T001-000891','2026-09-06',@cli1,@ven1,@suc_chincha,@dep_ica,@prov_chincha,@dist_chincha,'PUBLICADO',@digitador_id,'2026-09-06 08:40:00','2026-09-06 09:35:00','2026-09-06 09:20:00',@supervisor_id,@supervisor_id,NULL),
('T001-000892','2026-09-09',@cli2,@ven2,@suc_grocio,@dep_ica,@prov_chincha,@dist_grocio,'VALIDADO',@digitador_id,'2026-09-09 13:15:00',NULL,'2026-09-09 14:05:00',@supervisor_id,NULL,NULL),
('T001-000893','2026-09-12',@cli3,@ven1,@suc_chincha,@dep_ica,@prov_chincha,@dist_chincha,'BORRADOR',@digitador_id,'2026-09-12 10:00:00',NULL,NULL,NULL,NULL,NULL)
ON DUPLICATE KEY UPDATE
fecha=VALUES(fecha),cliente_id=VALUES(cliente_id),vendedor_id=VALUES(vendedor_id),sucursal_id=VALUES(sucursal_id),estado_registro=VALUES(estado_registro),
published_at=VALUES(published_at),validated_at=VALUES(validated_at),validated_by=VALUES(validated_by),published_by=VALUES(published_by),observation_reason=VALUES(observation_reason);

SET @guia_pub := (SELECT id FROM guias_cabecera WHERE numero='T001-000891' LIMIT 1);
SET @guia_val := (SELECT id FROM guias_cabecera WHERE numero='T001-000892' LIMIT 1);
SET @guia_bor := (SELECT id FROM guias_cabecera WHERE numero='T001-000893' LIMIT 1);

DELETE FROM guias_detalle WHERE guia_id IN (@guia_pub,@guia_val,@guia_bor);
INSERT INTO guias_detalle(guia_id,producto_id,unidad_id,cantidad) VALUES
(@guia_pub,@p1,@lit,40.000),
(@guia_pub,@p2,@kg,120.000),
(@guia_val,@p3,@kg,75.000),
(@guia_val,@p4,@lit,32.000),
(@guia_bor,@p5,@und,16.000);

-- 7. STOCK
INSERT INTO stock_cabecera(fecha_stock,almacen_id,estado_registro,created_by,created_at,published_at,validated_at,validated_by,published_by,idempotency_key,request_hash)
VALUES
('2026-09-10',@alm_chincha,'PUBLICADO',@digitador_id,'2026-09-10 17:00:00','2026-09-10 18:00:00','2026-09-10 17:45:00',@supervisor_id,@supervisor_id,'seed-stock-chi-20260910','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
('2026-09-11',@alm_grocio,'VALIDADO',@digitador_id,'2026-09-11 17:00:00',NULL,'2026-09-11 17:40:00',@supervisor_id,NULL,'seed-stock-gro-20260911','bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
('2026-09-12',@alm_chincha,'BORRADOR',@digitador_id,'2026-09-12 17:00:00',NULL,NULL,NULL,NULL,'seed-stock-chi-20260912','cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc')
ON DUPLICATE KEY UPDATE
estado_registro=VALUES(estado_registro),published_at=VALUES(published_at),validated_at=VALUES(validated_at),validated_by=VALUES(validated_by),published_by=VALUES(published_by);

SET @stock_pub := (SELECT id FROM stock_cabecera WHERE idempotency_key='seed-stock-chi-20260910' LIMIT 1);
SET @stock_val := (SELECT id FROM stock_cabecera WHERE idempotency_key='seed-stock-gro-20260911' LIMIT 1);
SET @stock_bor := (SELECT id FROM stock_cabecera WHERE idempotency_key='seed-stock-chi-20260912' LIMIT 1);

DELETE FROM stock_detalle WHERE stock_id IN (@stock_pub,@stock_val,@stock_bor);
INSERT INTO stock_detalle(stock_id,lote_id,producto_id,unidad_id,cantidad,fecha_stock,almacen_id) VALUES
(@stock_pub,@l1,@p1,@lit,180.000,'2026-09-10',@alm_chincha),
(@stock_pub,@l2,@p2,@kg,620.000,'2026-09-10',@alm_chincha),
(@stock_pub,@l3,@p3,@kg,245.000,'2026-09-10',@alm_chincha),
(@stock_val,@l4,@p4,@lit,95.000,'2026-09-11',@alm_grocio),
(@stock_val,NULL,@p5,@und,140.000,'2026-09-11',@alm_grocio),
(@stock_bor,@l1,@p1,@lit,170.000,'2026-09-12',@alm_chincha);

-- 8. VALIDACIONES
INSERT INTO validaciones(modulo,registro_id,usuario_id,resultado,observacion,validated_at,version,correlation_id)
SELECT 'documentos',@doc_val,@supervisor_id,'OK','Campos y consistencia verificados.','2026-09-08 12:00:00',1,'seed-val-doc-126'
WHERE NOT EXISTS (SELECT 1 FROM validaciones WHERE correlation_id='seed-val-doc-126');

INSERT INTO validaciones(modulo,registro_id,usuario_id,resultado,observacion,validated_at,version,correlation_id)
SELECT 'documentos',@doc_obs,@supervisor_id,'OBSERVADO','Se requiere revisar unidad y cantidad.','2026-09-11 09:00:00',1,'seed-val-doc-127'
WHERE NOT EXISTS (SELECT 1 FROM validaciones WHERE correlation_id='seed-val-doc-127');

INSERT INTO validaciones(modulo,registro_id,usuario_id,resultado,observacion,validated_at,version,correlation_id)
SELECT 'guias',@guia_val,@supervisor_id,'OK','Guía revisada y conforme.','2026-09-09 14:05:00',1,'seed-val-guia-892'
WHERE NOT EXISTS (SELECT 1 FROM validaciones WHERE correlation_id='seed-val-guia-892');

INSERT INTO validaciones(modulo,registro_id,usuario_id,resultado,observacion,validated_at,version,correlation_id)
SELECT 'stock',@stock_val,@supervisor_id,'OK','Existencias verificadas.','2026-09-11 17:40:00',1,'seed-val-stock-0911'
WHERE NOT EXISTS (SELECT 1 FROM validaciones WHERE correlation_id='seed-val-stock-0911');

SET @val_doc := (SELECT id FROM validaciones WHERE correlation_id='seed-val-doc-126' LIMIT 1);
SET @val_obs := (SELECT id FROM validaciones WHERE correlation_id='seed-val-doc-127' LIMIT 1);

INSERT INTO validaciones_detalle(validacion_id,codigo,campo,severidad,mensaje,resultado)
SELECT @val_doc,'VAL-001','campos_obligatorios','BLOQUEANTE','Todos los campos obligatorios están completos.','OK'
WHERE NOT EXISTS (SELECT 1 FROM validaciones_detalle WHERE validacion_id=@val_doc AND codigo='VAL-001');
INSERT INTO validaciones_detalle(validacion_id,codigo,campo,severidad,mensaje,resultado)
SELECT @val_obs,'VAL-003','cantidad','BLOQUEANTE','La cantidad debe ser confirmada por supervisión.','ERROR'
WHERE NOT EXISTS (SELECT 1 FROM validaciones_detalle WHERE validacion_id=@val_obs AND codigo='VAL-003');

-- 9. PUBLICACIONES
INSERT INTO publicaciones(modulo,fecha_publicacion,usuario_id,estado,correlation_id)
SELECT 'documentos','2026-09-05 10:20:00',@supervisor_id,'PUBLICADO','seed-pub-doc-125'
WHERE NOT EXISTS (SELECT 1 FROM publicaciones WHERE correlation_id='seed-pub-doc-125');
SET @pub_doc := (SELECT id FROM publicaciones WHERE correlation_id='seed-pub-doc-125' LIMIT 1);
INSERT IGNORE INTO detalle_publicacion(publicacion_id,registro_id,dataset,version)
VALUES(@pub_doc,@doc_pub,'documentos',1);

INSERT INTO publicaciones(modulo,fecha_publicacion,usuario_id,estado,correlation_id)
SELECT 'guias','2026-09-06 09:35:00',@supervisor_id,'PUBLICADO','seed-pub-guia-891'
WHERE NOT EXISTS (SELECT 1 FROM publicaciones WHERE correlation_id='seed-pub-guia-891');
SET @pub_guia := (SELECT id FROM publicaciones WHERE correlation_id='seed-pub-guia-891' LIMIT 1);
INSERT IGNORE INTO detalle_publicacion(publicacion_id,registro_id,dataset,version)
VALUES(@pub_guia,@guia_pub,'guias',1);

INSERT INTO publicaciones(modulo,fecha_publicacion,usuario_id,estado,correlation_id)
SELECT 'stock','2026-09-10 18:00:00',@supervisor_id,'PUBLICADO','seed-pub-stock-0910'
WHERE NOT EXISTS (SELECT 1 FROM publicaciones WHERE correlation_id='seed-pub-stock-0910');
SET @pub_stock := (SELECT id FROM publicaciones WHERE correlation_id='seed-pub-stock-0910' LIMIT 1);
INSERT IGNORE INTO detalle_publicacion(publicacion_id,registro_id,dataset,version)
VALUES(@pub_stock,@stock_pub,'stock',1);

-- 10. EXPORTACIONES
INSERT INTO exportaciones(nombre_archivo,tipo_dataset,formato,filtro_json,usuario_id,generated_at,record_count,resultado,duration_ms,correlation_id)
SELECT 'documentos_publicados_202609.xlsx','documentos','XLSX',JSON_OBJECT('estado','PUBLICADO','periodo','2026-09'),@gerencia_id,'2026-09-12 16:10:00',1,'COMPLETADO',148,'seed-exp-doc-xlsx'
WHERE NOT EXISTS (SELECT 1 FROM exportaciones WHERE correlation_id='seed-exp-doc-xlsx');

INSERT INTO exportaciones(nombre_archivo,tipo_dataset,formato,filtro_json,usuario_id,generated_at,record_count,resultado,duration_ms,correlation_id)
SELECT 'stock_publicado_202609.csv','stock','CSV',JSON_OBJECT('estado','PUBLICADO','periodo','2026-09'),@bayer_id,'2026-09-12 16:25:00',1,'COMPLETADO',96,'seed-exp-stock-csv'
WHERE NOT EXISTS (SELECT 1 FROM exportaciones WHERE correlation_id='seed-exp-stock-csv');

INSERT INTO exportaciones(nombre_archivo,tipo_dataset,formato,filtro_json,usuario_id,generated_at,record_count,resultado,duration_ms,correlation_id)
SELECT 'guias_publicadas_202609.json','guias','JSON',JSON_OBJECT('estado','PUBLICADO','periodo','2026-09'),@bayer_id,'2026-09-12 16:35:00',1,'COMPLETADO',71,'seed-exp-guia-json'
WHERE NOT EXISTS (SELECT 1 FROM exportaciones WHERE correlation_id='seed-exp-guia-json');

-- 11. AUDITORÍA
INSERT INTO auditoria_acciones(usuario_id,modulo,accion,entidad_id,fecha_hora,ip,user_agent,metadata_json,resultado,correlation_id)
SELECT @digitador_id,'documentos','crear',@doc_bor,'2026-09-10 15:30:00','192.168.1.24','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/152.0 Safari/537.36',JSON_OBJECT('numero','B001-000321'),'OK','seed-aud-doc-create'
WHERE NOT EXISTS (SELECT 1 FROM auditoria_acciones WHERE correlation_id='seed-aud-doc-create');

INSERT INTO auditoria_acciones(usuario_id,modulo,accion,entidad_id,fecha_hora,ip,user_agent,metadata_json,resultado,correlation_id)
SELECT @supervisor_id,'validacion','validar',@doc_val,'2026-09-08 12:00:00','192.168.1.31','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/152.0 Safari/537.36',JSON_OBJECT('estado','VALIDADO'),'OK','seed-aud-doc-validate'
WHERE NOT EXISTS (SELECT 1 FROM auditoria_acciones WHERE correlation_id='seed-aud-doc-validate');

INSERT INTO auditoria_acciones(usuario_id,modulo,accion,entidad_id,fecha_hora,ip,user_agent,metadata_json,resultado,correlation_id)
SELECT @supervisor_id,'publicaciones','publicar',@doc_pub,'2026-09-05 10:20:00','192.168.1.31','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/152.0 Safari/537.36',JSON_OBJECT('dataset','documentos'),'OK','seed-aud-doc-publish'
WHERE NOT EXISTS (SELECT 1 FROM auditoria_acciones WHERE correlation_id='seed-aud-doc-publish');

INSERT INTO auditoria_acciones(usuario_id,modulo,accion,entidad_id,fecha_hora,ip,user_agent,metadata_json,resultado,correlation_id)
SELECT @bayer_id,'exportaciones','descargar',NULL,'2026-09-12 16:25:00','181.65.42.18','Mozilla/5.0 (Linux; Android 14; SM-S928B) AppleWebKit/537.36 Chrome/152.0 Mobile Safari/537.36',JSON_OBJECT('formato','CSV','dataset','stock'),'OK','seed-aud-bayer-export'
WHERE NOT EXISTS (SELECT 1 FROM auditoria_acciones WHERE correlation_id='seed-aud-bayer-export');

COMMIT;

-- Resultado esperado:
-- Catálogos maestros con datos.
-- Homologaciones Bayer con registros.
-- Documentos, guías y stock en varios estados.
-- Registros publicados visibles en Portal Bayer.
-- Validaciones, publicaciones, exportaciones y auditoría con contenido.
-- Dashboards con valores y tendencias para pruebas funcionales.
