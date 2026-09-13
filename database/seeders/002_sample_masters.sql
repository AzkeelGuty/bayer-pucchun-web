-- Catálogos de muestra para Schema v2: 44 registros en una carga nueva.
-- Empresas, personas y documentos son ficticios; no representan datos comerciales verificados.
-- 10 productos, 10 clientes, 10 vendedores, 1 empresa, 2 sucursales,
-- 3 almacenes, 2 unidades y 2 departamentos/provincias/distritos.
-- Importar en phpMyAdmin sobre la base de pruebas seleccionada, con respaldo.
-- Sin escrituras concurrentes. Si ocurre un error, detenerse y hacer ROLLBACK.
-- No crea usuarios, operaciones ni homologaciones.
SET NAMES utf8mb4;
START TRANSACTION;

-- 1. Empresa ficticia: 1 registro.
INSERT INTO empresas (ruc,razon_social,nombre_comercial,estado)
SELECT '00000000001','Agroinsumos Valle del Sur S.A.C.','Valle del Sur',1
WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE ruc='00000000001');

-- 2. Departamentos: 2 ubicaciones, respetando su jerarquía.
INSERT INTO departamentos (nombre)
SELECT 'Arequipa'
WHERE NOT EXISTS (SELECT 1 FROM departamentos WHERE nombre='Arequipa');

INSERT INTO departamentos (nombre)
SELECT 'Lima'
WHERE NOT EXISTS (SELECT 1 FROM departamentos WHERE nombre='Lima');

-- 3. Provincias: 2 ubicaciones, respetando su jerarquía.
INSERT INTO provincias (departamento_id,nombre)
SELECT d.id,'Arequipa' FROM departamentos d
WHERE d.nombre='Arequipa'
AND NOT EXISTS (SELECT 1 FROM provincias p WHERE p.departamento_id=d.id AND p.nombre='Arequipa');

INSERT INTO provincias (departamento_id,nombre)
SELECT d.id,'Lima' FROM departamentos d
WHERE d.nombre='Lima'
AND NOT EXISTS (SELECT 1 FROM provincias p WHERE p.departamento_id=d.id AND p.nombre='Lima');

-- 4. Distritos: 2 ubicaciones, respetando su jerarquía.
INSERT INTO distritos (provincia_id,nombre)
SELECT p.id,'Cayma' FROM provincias p
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Arequipa' AND p.nombre='Arequipa'
AND NOT EXISTS (SELECT 1 FROM distritos x WHERE x.provincia_id=p.id AND x.nombre='Cayma');

INSERT INTO distritos (provincia_id,nombre)
SELECT p.id,'Miraflores' FROM provincias p
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Lima' AND p.nombre='Lima'
AND NOT EXISTS (SELECT 1 FROM distritos x WHERE x.provincia_id=p.id AND x.nombre='Miraflores');

-- 5. Sucursales: 2 registros de la misma empresa, en distintas ubicaciones.
INSERT INTO sucursales (empresa_id,codigo,nombre,distrito_id,estado)
SELECT e.id,'SUC-001','Sucursal Arequipa',x.id,1
FROM empresas e CROSS JOIN distritos x
JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE e.ruc='00000000001' AND d.nombre='Arequipa'
AND p.nombre='Arequipa' AND x.nombre='Cayma'
AND NOT EXISTS (SELECT 1 FROM sucursales WHERE codigo='SUC-001');

INSERT INTO sucursales (empresa_id,codigo,nombre,distrito_id,estado)
SELECT e.id,'SUC-002','Sucursal Lima',x.id,1
FROM empresas e CROSS JOIN distritos x
JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE e.ruc='00000000001' AND d.nombre='Lima'
AND p.nombre='Lima' AND x.nombre='Miraflores'
AND NOT EXISTS (SELECT 1 FROM sucursales WHERE codigo='SUC-002');

-- 6. Almacenes: 2 en Arequipa y 1 en Lima.
INSERT INTO almacenes (sucursal_id,codigo,nombre,tipo,estado)
SELECT s.id,'ALM-001','Almacén Central Arequipa','GENERAL',1 FROM sucursales s
WHERE s.codigo='SUC-001'
AND NOT EXISTS (SELECT 1 FROM almacenes WHERE codigo='ALM-001');

INSERT INTO almacenes (sucursal_id,codigo,nombre,tipo,estado)
SELECT s.id,'ALM-002','Almacén de Despacho Arequipa','GENERAL',1 FROM sucursales s
WHERE s.codigo='SUC-001'
AND NOT EXISTS (SELECT 1 FROM almacenes WHERE codigo='ALM-002');

INSERT INTO almacenes (sucursal_id,codigo,nombre,tipo,estado)
SELECT s.id,'ALM-003','Almacén Lima','GENERAL',1 FROM sucursales s
WHERE s.codigo='SUC-002'
AND NOT EXISTS (SELECT 1 FROM almacenes WHERE codigo='ALM-003');

-- 7. Clientes: 10 registros con documentos ficticios; 5 por ubicación.
INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-001','06','00000000002','Agrícola Los Olivos S.A.C.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Arequipa' AND p.nombre='Arequipa'
AND x.nombre='Cayma'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000002');

INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-002','06','00000000003','Cultivos Santa Elena E.I.R.L.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Lima' AND p.nombre='Lima'
AND x.nombre='Miraflores'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000003');

INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-003','06','00000000004','Fundo El Manantial S.A.C.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Arequipa' AND p.nombre='Arequipa'
AND x.nombre='Cayma'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000004');

INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-004','06','00000000005','Agrocomercial Las Palmeras S.R.L.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Lima' AND p.nombre='Lima'
AND x.nombre='Miraflores'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000005');

INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-005','06','00000000006','Agrícola Monte Verde S.A.C.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Arequipa' AND p.nombre='Arequipa'
AND x.nombre='Cayma'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000006');

INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-006','06','00000000007','Viveros La Campiña E.I.R.L.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Lima' AND p.nombre='Lima'
AND x.nombre='Miraflores'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000007');

INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-007','06','00000000008','Productores del Valle S.R.L.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Arequipa' AND p.nombre='Arequipa'
AND x.nombre='Cayma'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000008');

INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-008','06','00000000009','Fundo Los Girasoles S.A.C.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Lima' AND p.nombre='Lima'
AND x.nombre='Miraflores'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000009');

INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-009','06','00000000010','Agroservicios Buena Tierra E.I.R.L.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Arequipa' AND p.nombre='Arequipa'
AND x.nombre='Cayma'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000010');

INSERT INTO clientes (codigo,tipo_doc,nro_doc,razon_social,departamento_id,provincia_id,distrito_id)
SELECT 'CLI-010','06','00000000011','Cultivos Nuevo Horizonte S.A.C.',d.id,p.id,x.id
FROM distritos x JOIN provincias p ON p.id=x.provincia_id
JOIN departamentos d ON d.id=p.departamento_id
WHERE d.nombre='Lima' AND p.nombre='Lima'
AND x.nombre='Miraflores'
AND NOT EXISTS (SELECT 1 FROM clientes WHERE nro_doc='00000000011');

-- 8. Vendedores: 10 registros ficticios.
INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-001','Carlos','Mendoza Salas',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-001');

INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-002','Lucía','Quispe Rojas',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-002');

INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-003','Jorge','Vargas Medina',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-003');

INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-004','Ana','Torres Paredes',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-004');

INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-005','Luis','Huamán Castro',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-005');

INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-006','Rosa','Flores Aguilar',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-006');

INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-007','Miguel','Ramos Cárdenas',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-007');

INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-008','Elena','Soto Valdivia',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-008');

INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-009','Diego','Paredes Núñez',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-009');

INSERT INTO vendedores (codigo,nombres,apellidos,estado)
SELECT 'VEN-010','María','Chávez Romero',1
WHERE NOT EXISTS (SELECT 1 FROM vendedores WHERE codigo='VEN-010');

-- 9. Unidades: KG y LT de muestra. Factor 1 en cada unidad; no implica convertir KG a LT.
INSERT INTO unidades_medida (codigo,nombre,abreviatura,factor_base)
SELECT 'KG','Kilogramo','KG',1.0000
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE codigo='KG');

INSERT INTO unidades_medida (codigo,nombre,abreviatura,factor_base)
SELECT 'LT','Litro','LT',1.0000
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE codigo='LT');

-- 10. Productos: 10 registros; 5 usan KG y 5 usan LT.
INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-001','Urea granulada',u.id,1 FROM unidades_medida u
WHERE u.codigo='KG'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-001');

INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-002','Fertilizante foliar líquido',u.id,1 FROM unidades_medida u
WHERE u.codigo='LT'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-002');

INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-003','Sulfato de potasio',u.id,1 FROM unidades_medida u
WHERE u.codigo='KG'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-003');

INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-004','Extracto de algas líquido',u.id,1 FROM unidades_medida u
WHERE u.codigo='LT'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-004');

INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-005','Fosfato monoamónico',u.id,1 FROM unidades_medida u
WHERE u.codigo='KG'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-005');

INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-006','Ácidos húmicos líquidos',u.id,1 FROM unidades_medida u
WHERE u.codigo='LT'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-006');

INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-007','Nitrato de calcio',u.id,1 FROM unidades_medida u
WHERE u.codigo='KG'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-007');

INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-008','Aminoácidos líquidos',u.id,1 FROM unidades_medida u
WHERE u.codigo='LT'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-008');

INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-009','Sulfato de magnesio',u.id,1 FROM unidades_medida u
WHERE u.codigo='KG'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-009');

INSERT INTO productos (codigo,nombre,unidad_base_id,estado)
SELECT 'PRD-010','Corrector de calcio líquido',u.id,1 FROM unidades_medida u
WHERE u.codigo='LT'
AND NOT EXISTS (SELECT 1 FROM productos WHERE codigo='PRD-010');

COMMIT;
