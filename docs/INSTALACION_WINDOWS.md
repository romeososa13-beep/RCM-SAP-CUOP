# Instalación en tu computadora (Windows)

Para usar el ERP **tú solo, en tu computadora**, en horario de trabajo, con respaldo diario
en **Google Drive** y acceso desde tu **celular**. No necesitas servidor, dominio ni pagar nada.

**Requisitos:** Windows 10 u 11 de 64 bits, 8 GB de RAM, unos 10 GB libres en disco,
Google Drive para escritorio instalado. El ERP usa ~300 MB de memoria.

---

## 1. Instalar Docker Desktop (una sola vez)

1. Descarga **Docker Desktop para Windows** desde <https://www.docker.com/products/docker-desktop/> e instálalo
   (acepta usar **WSL 2** si lo pregunta). Reinicia la computadora si te lo pide.
2. Ábrelo y espera a que abajo diga **Engine running**.
3. En Docker Desktop › **Settings › General**, activa **Start Docker Desktop when you sign in**
   (así el ERP arranca solo al prender la computadora) y guarda.

## 2. Descargar el ERP

1. En <https://github.com/romeososa13-beep/RCM-SAP-CUOP> › botón verde **Code › Download ZIP**.
2. Descomprime el ZIP y renombra la carpeta a **`C:\ERP-CUOP`** (que adentro estén `windows`, `docker`, `app`…).

## 3. Primer arranque

1. Abre `C:\ERP-CUOP\windows` y da doble clic en **`iniciar.bat`**.
2. Se abrirá el Bloc de notas con el archivo **`.env`**. Cambia:
   - `DB_PASSWORD` y `DB_ROOT_PASSWORD`: dos contraseñas largas distintas (solo letras y números).
   - `ADMIN_NOMBRE`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`: tu usuario para entrar.
   - (Opcional) los datos `MAIL_*` para enviar recibos por correo (Gmail: usa una «contraseña de aplicación»).
3. **Guarda y cierra** el Bloc de notas. La primera vez tarda **5–15 minutos** (descarga e instala todo).
4. Se abrirá el navegador en **<http://localhost:8080>**. Entra con tu correo y contraseña.

> Si Windows pregunta por permisos de red para Docker, acepta.

## 4. Respaldo automático a Google Drive

El ERP guarda cada día (la primera vez que prendes la computadora) un respaldo en
`C:\ERP-CUOP\respaldos`. Para que **Google Drive lo suba solo**:

1. Clic en el ícono de **Google Drive** junto al reloj › ⚙ **Preferencias**.
2. **Mi computadora › Agregar carpeta** › elige `C:\ERP-CUOP\respaldos` ›
   **Sincronizar con Google Drive** › **Listo** › **Guardar**.

Los respaldos aparecerán en drive.google.com › **Computadoras**. Se guardan 45 días.

- **Respaldo inmediato:** `windows\respaldar-ahora.bat`
- **Restaurar:** copia el archivo a `C:\ERP-CUOP\respaldos` y ejecuta `windows\restaurar.bat`
  (antes de restaurar guarda automáticamente un respaldo de lo que había).

> No pongas la carpeta del ERP completa dentro de Google Drive: la base de datos solo debe copiarse
> en forma de respaldo. Por eso se sincroniza únicamente la carpeta `respaldos`.

## 5. Cargar los datos de Loreto

1. Ingresa al ERP › **Administración › Datos de la institución**: nombre, RFC, dirección, logo.
2. **Padrón › Importar Excel**: sube `padron_lor_para_importar.xlsx` (con los planes de los 10 alumnos en rojo).
3. Copia `saldos_lor_para_migrar.xlsx` a `C:\ERP-CUOP\importar` y ejecuta **`windows\migrar-saldos.bat`**
   (primero simula; escribe SI para migrar). Debe decir **$769,985.00**.
4. **`windows\folio-inicial.bat`**: escribe el último folio usado en INTELL.

## 6. Usarlo desde el celular (Tailscale, gratis)

1. Instala **Tailscale** en la computadora (<https://tailscale.com/download/windows>) y en el celular
   (App Store / Google Play). Entra **con la misma cuenta** en ambos.
2. En la computadora abre **PowerShell** y ejecuta (una sola vez):

   ```powershell
   tailscale serve --bg 8080
   ```

   Te mostrará una dirección como **`https://tu-pc.tu-red.ts.net`**. Ábrela en el celular
   (con Tailscale encendido). Es privada: solo tus dispositivos pueden entrar.

## 7. El día a día

- Prende la computadora y abre Docker Desktop (o déjalo arrancar solo). El ERP queda en <http://localhost:8080>.
- **Recargos y mensualidades:** se aplican solos cada hora entre 7:00 y 21:00 (la mensualidad del día 1 al 10).
  No importa si un día no prendes la computadora: se aplican la siguiente vez, sin duplicarse.
- **Detener:** `windows\detener.bat` (no es necesario al apagar la computadora).
- **Actualizar a una versión nueva:** descarga el ZIP nuevo, cópialo **encima** de `C:\ERP-CUOP`
  (conserva tu `.env` y las carpetas `respaldos` e `importar`) y ejecuta `windows\actualizar.bat`.

## 8. WhatsApp (opcional)

El envío de mensajes funciona desde tu computadora. Para **recibir** (chatbot y confirmación de entrega),
Meta necesita una dirección pública: con **Tailscale Funnel** se publica **solo** la dirección del webhook,
mientras tu computadora esté encendida:

```powershell
tailscale funnel --bg --set-path /webhooks/whatsapp http://127.0.0.1:8080/webhooks/whatsapp
```

Usa `https://tu-pc.tu-red.ts.net/webhooks/whatsapp` como URL del webhook en Meta y sigue
`docs/INSTALACION.md` §9 para la cuenta, las plantillas y el archivo `.env`.
Si la computadora está apagada, Meta reintenta entregar los mensajes durante un tiempo; los mensajes de
alumnos fuera de horario se reciben al prender (si pasa demasiado tiempo pueden perderse).

## Si algo falla

- «Docker Desktop no está abierto»: ábrelo y espera *Engine running*.
- La página no abre: en Docker Desktop › Containers › `erp-cuop` revisa que `app`, `db` y `respaldo` estén en verde;
  en `app` › *Logs* aparece el error.
- Olvidaste tu contraseña: pídele a otro administrador que la cambie en **Usuarios**, o vuelve a ejecutar
  `iniciar.bat` después de borrar los datos (solo si aún no cargaste información).
