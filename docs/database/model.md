# Modelo de datos
El esquema está normalizado por maestros, transacciones, homologaciones, publicación y auditoría. Las tablas operativas usan cabecera/detalle para documentos, guías y stock. Lotes y vencimientos se manejan en `lotes`. Los nombres/códigos externos pueden mapearse con `homologacion_*`.

El diccionario Bayer se materializa mediante consultas JOIN en `App\Services\BayerDataService`, manteniendo desacoplada la estructura interna.
