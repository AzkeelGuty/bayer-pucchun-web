# Seguridad
- Passwords con `password_hash/password_verify`.
- PDO con prepared statements.
- CSRF en formularios POST.
- Cookies de sesión HttpOnly, SameSite=Lax y Secure bajo HTTPS.
- RBAC por roles.
- Bayer no tiene rutas CRUD habilitadas.
- API deshabilitada por defecto.
- No exponer `.env` ni carpetas privadas.
- Rotar credenciales, token API y clave inicial antes de producción.


## Gestión de usuarios, perfiles y credenciales

El administrador accede desde **Usuarios y accesos**. El botón **Editar** abre
la ficha completa con nombre, correo, teléfono, cargo, rol, estado,
fotografía opcional y restablecimiento de contraseña. El botón
**Desactivar** utiliza color rojo y **Activar**, verde. La pantalla muestra
un icono por rol (ADMIN, DIGITADOR, SUPERVISOR, GERENCIA o BAYER).

Todos los usuarios autenticados, incluido el rol BAYER, pueden abrir
**Mi perfil** desde su identificación en la barra superior. Allí pueden
actualizar su nombre, correo, teléfono y fotografía y cambiar su
contraseña indicando la contraseña actual. No pueden aumentar sus
privilegios ni modificar su propio cargo. El administrador sí puede
asignar un rol y restablecer una contraseña temporal.

Las fotografías se almacenan en
\`storage/private/user_avatars\` y solo se sirven mediante
\`GET /avatar-usuario?id=<id>\` con sesión autenticada; únicamente el
propio usuario y ADMIN pueden acceder. Se admiten JPG, PNG y WEBP
con tamaño máximo 2 MB, dimensiones entre 32 y 4000 píxeles
y nombres aleatorios. No se admiten SVG ni formatos ejecutables.
Sin foto se muestra la inicial del nombre. Se puede reemplazar o
retirar la fotografía.

Las credenciales se guardan con \`password_hash\`; el servidor nunca
devuelve las contraseñas. Se exige un mínimo de 10 caracteres, se validan
las confirmaciones y se revocan los tokens API activos del usuario cuando
se restablece su contraseña. No se permite desactivar la propia cuenta
ADMIN ni retirarle ese rol desde la administración.

### Actualización de base de datos

**Ejecutar una sola vez antes de activar los nuevos formularios:**
\`database/migrations/016_perfiles_usuarios.sql\`. Añade
\`foto_perfil\`, \`cargo\`, \`telefono\` y \`actualizado_en\`
a la tabla \`usuarios\`. No modifica claves, estados, roles ni hashes
previos. Se requiere que PHP pueda crear y escribir el directorio
\`storage/private/user_avatars\` fuera del directorio público.

### Importación Bayer

La importación unificada muestra únicamente el selector XLSX/CSV y el
botón **Analizar Excel**; se retiraron los controles de mes y fecha
alternativos del formulario, así como la explicación extensa.
El texto breve presenta las cabeceras principales admitidas.
**Cancelar y descartar** utiliza botón rojo e icono; la selección de
pestañas y vista previa continúan funcionando automáticamente.
