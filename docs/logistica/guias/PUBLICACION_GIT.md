# Registro de la publicación inicial de Pedro

Fecha: 5 de octubre de 2026. Destino autorizado por Pedro: **feature/logistics-flow-pedro** en [AzkeelGuty/bayer-pucchun-web](https://github.com/AzkeelGuty/bayer-pucchun-web/tree/feature/logistics-flow-pedro).

Pedro confirmó el intercambio con Alisson: **Pedro desarrolla persistencia; Alisson desarrolla funcionamiento**. Por su indicación se conserva el nombre flow-pedro aunque esta entrega sea de persistencia. La base del trabajo es `f38f4e8`, común a las tres ramas de Logística creadas por Alisson.

Este registro conserva la primera entrega de quince commits. La reorganización posterior de documentación se registra en otro commit de la misma rama.

## Orden de los quince commits

Cada commit tiene título y cuerpo en español explicando el cambio y su propósito. La división sigue dependencias reales; el Repository se publica como una implementación completa.

| N.º | Tema | Contenido |
| --- | --- | --- |
| 01 | Cantidades exactas | Helper decimal, excepción de persistencia y 42 comprobaciones unitarias. |
| 02 | Migración 006 | Preparaciones, despachos, intentos, historial, operaciones, snapshot y reversión. |
| 03 | Migración 007 | Incidencias, resoluciones, fallos, restricciones de cantidades y reversión protegida. |
| 04 | Interfaces | Contrato del Repository y proveedor confiable de hechos de Inventarios. |
| 05 | Repository | Persistencia atómica, sede, versiones, historial, idempotencia y balances. |
| 06 | Pruebas de persistencia | Fixtures y 239 comprobaciones, incluidos workers concurrentes y rollback. |
| 07 | Pruebas de incidencias | 264 comprobaciones de rechazos, reintentos, cierre, retornos/pérdidas y concurrencia. |
| 08 | QA adicional portable | Cuatro scripts de migración, restricciones y normalizadores, con rutas relativas. |
| 09 | Portabilidad de pruebas | Comparación de rutas de branding consistente en Windows/Linux. |
| 10 | CI | Ejecución de suites y scripts de QA en ramas de Logística. |
| 11 | Contratos y modelo | Métodos, relaciones, cantidades, hechos externos y límites de integración. |
| 12 | Entrega y resultados | Reparto confirmado, guía de uso y registro de las pruebas ejecutadas. |
| 13 | Cuaderno visual | Seis láminas, galería offline, fuentes/licencias e instrucciones de generación. |
| 14 | Casos para Alisson | Diez láminas, quince casos, ejemplos de entrada, coordinación y referencias offline. |
| 15 | Índice y publicación | Acceso desde README, esta guía y recorrido para revisar la entrega. |

## Cómo se publica

Primero se consultó la rama remota y se seleccionó su mismo punto de partida. Los cambios se agregan **por archivos de cada tema**, no con un `git add .` indiscriminado.

```powershell
git fetch origin refs/heads/feature/logistics-flow-pedro:refs/remotes/origin/feature/logistics-flow-pedro
git switch feature/logistics-flow-pedro

# Ejemplo del primer tema; cada tema lleva su propio commit.
git add -- app/Support/LogisticsQuantity.php app/Exceptions/LogisticsPersistenceException.php tests/unit/logistics_quantity_test.php
git commit -m "feat(logistica): garantizar cantidades decimales exactas"

# Revisar todos los commits y el diff antes de publicar.
git log --oneline f38f4e8..HEAD
git diff --check f38f4e8..HEAD
git status --short

# Publicar únicamente la rama indicada por Pedro.
git push -u origin HEAD:refs/heads/feature/logistics-flow-pedro
```

No se usa force push. Si la rama remota cambia mientras se preparan los commits, Git debe rechazar una actualización que no pueda avanzar normalmente; se compara y resuelve antes de repetir. El push conserva las ramas de Alisson, la conjunta y la integración.

## Verificación realizada

- **34 suites locales correctas:** 27 originales, 3 de Logística y 4 scripts auxiliares.
- **545 comprobaciones de Logística:** 42 + 239 + 264.
- **132 archivos PHP** con sintaxis correcta.
- Snapshots 006 y 007 idénticos a sus migraciones.
- Enlaces locales de las galerías, fuentes, referencias y manifiestos revisados.

Las pruebas usan un servidor temporal dedicado en localhost:43316 y bases aleatorias `bayer_test_<hex>`; no cargan `.env`. El servidor se detuvo al terminar. El resultado de [GitHub Actions](https://github.com/AzkeelGuty/bayer-pucchun-web/actions) se consulta aparte del resultado local.

## Qué revisar después del push

1. [Historial de la rama](https://github.com/AzkeelGuty/bayer-pucchun-web/commits/feature/logistics-flow-pedro).
2. [Comparación con la rama conjunta](https://github.com/AzkeelGuty/bayer-pucchun-web/compare/feature/logistics-pedro-alisson...feature/logistics-flow-pedro).
3. [Índice de Logística](../README.md), [guía de casos](GUIA_PARA_ALISSON.md) y contratos.
4. Comparación técnica con Alisson antes de integrar; validar base y contratos con G4, G5 e Inventarios.

Los adjuntos originales del docente, ZIP duplicados, datos del servidor de QA, logs, archivos de procesos y `.env` permanecen fuera de los commits. La reorganización posterior retiró las copias de consulta y el parche histórico del repositorio. Los contratos, código y pruebas vigentes permanecen en sus carpetas originales. La base concreta sigue provisional y los adaptadores reales de Inventarios/Services continúan pendientes.
