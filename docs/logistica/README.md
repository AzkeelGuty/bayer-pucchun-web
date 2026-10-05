# Logística del Grupo 7: desarrollo y material para Alisson

Pedro confirmó el intercambio de responsabilidades: **Pedro desarrolla persistencia** y **Alisson desarrolla Services, Controllers, reglas, validaciones, permisos, rutas y transiciones**. Esta entrega se publica únicamente en `feature/logistics-flow-pedro`, por indicación de Pedro. Se conserva el nombre histórico de la rama; su contenido actual corresponde a persistencia.

## Para empezar

- [Galería de diez casos concretos](casos-para-alisson/index.html): aceptación parcial, rechazo, daño, visita fallida, reintento, retorno, pérdida, cierre y concurrencia.
- [Guía técnica para Alisson](casos-para-alisson/GUIA_PARA_ALISSON.md): quince escenarios, métodos, cantidades, estados y ejemplos de entrada.
- [Explicación en cinco minutos](casos-para-alisson/EXPLICACION_EN_5_MINUTOS.md): guion breve para exponer el módulo.
- [Coordinación e integración](casos-para-alisson/COORDINACION_Y_CONTRATO.md): decisiones con los responsables de Inventarios, Guías, G4 y G5.
- [Cuaderno visual de seis láminas](material-visual/index.html): recorrido general, responsabilidades, datos y estructura.

Las galerías funcionan sin Internet. Para usarlas localmente, abrir cada `index.html` y conservar sus PNG, fuentes y referencias. Las imágenes son conceptuales; no representan pantallas de una aplicación desplegada.

## Contratos y código vigentes

- [Contrato de persistencia](../contracts/logistics-persistence.md).
- [Contrato de incidencias, intentos, balances y cierre](../contracts/logistics-incidents.md): prevalece para los puntos ampliados por la migración 007.
- [Interfaz del Repository](../../app/Contracts/LogisticsRepositoryInterface.php) y [confirmación de Inventarios](../../app/Contracts/LogisticsInventoryConfirmationProviderInterface.php).
- [Documento de entrega](../handoffs/logistics-persistence-pedro.md), [registro de verificación](../handoffs/logistics-validation.md) y [modelo de datos](../database/logistics-persistence.md).

Las copias incluidas en `referencias/` permiten revisar las galerías offline. Para integrar o ejecutar, usar los archivos vigentes del repositorio. El parche incluido en la galería de casos es una referencia histórica contra `f38f4e8`; su contenido documental anterior no sustituye el reparto y la rama actuales.

## Base común y alcance

La persistencia se desarrolló tomando `schema_v2` de `integration/frontend-equipo` en `f38f4e8`. **El esquema concreto sigue provisional hasta validar la base y los contratos con G4, G5 e Inventarios.** Este trabajo no acredita aprobación del ingeniero para conservar todos los nombres o relaciones. Comparar con el commit de integración vigente antes de aplicar migraciones; no importar el esquema 002 sobre datos compartidos existentes.

La entrega incluye migraciones aditivas 006 y 007, Repository, integridad de cantidades, incidencias, historial, idempotencia y concurrencia. Reutiliza Guías y no crea stock/Kardex paralelo. Services, Controllers, permisos, rutas y adaptadores reales siguen fuera de esta entrega de persistencia. Inventarios confirma reservas, salidas, retornos y hechos de disposición; las confirmaciones de las pruebas son fixtures.

Los resultados locales certifican los casos ejecutados, no un ERP listo para producción. El reparto confirmado entre personas no elimina la validación técnica necesaria de la integración.
