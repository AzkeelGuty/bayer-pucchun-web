-- Rollback 006: elimina exclusivamente el almacenamiento nuevo de Logistica.
-- Operacion destructiva para sus datos; usar solo para una reversion deliberada.
-- Mantiene FOREIGN_KEY_CHECKS activo y elimina primero las dependencias.
SET FOREIGN_KEY_CHECKS=1;
DROP TABLE IF EXISTS logistica_historial;
DROP TABLE IF EXISTS logistica_operaciones;
DROP TABLE IF EXISTS logistica_entrega_detalle;
DROP TABLE IF EXISTS logistica_entregas;
DROP TABLE IF EXISTS logistica_despacho_detalle;
DROP TABLE IF EXISTS logistica_despachos;
DROP TABLE IF EXISTS logistica_preparacion_detalle;
DROP TABLE IF EXISTS logistica_preparaciones;
DELETE FROM schema_migrations WHERE version=6;
