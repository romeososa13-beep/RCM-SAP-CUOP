# Instalación en servidor

Guía para poner el ERP en producción. La opción recomendada es un **VPS con Ubuntu 24.04**
(DigitalOcean, AWS Lightsail, Linode, Hostinger VPS, etc.): 2 vCPU, 4 GB de RAM y 40 GB de disco
alcanzan de sobra para las 5 sedes. Al final hay notas para **hosting compartido con cPanel**.

> Reemplace `erp.midominio.mx` por el dominio real en todos los pasos.

---

## 1. Paquetes del servidor

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server supervisor git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-zip \
  php8.3-gd php8.3-intl php8.3-curl php8.3-bcmath
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

## 2. Base de datos

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE erp_cuop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY 'UNA-CONTRASEÑA-LARGA';
GRANT ALL PRIVILEGES ON erp_cuop.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Código

```bash
sudo mkdir -p /var/www/erp-cuop && sudo chown $USER:www-data /var/www/erp-cuop
git clone https://github.com/romeososa13-beep/RCM-SAP-CUOP.git /var/www/erp-cuop
cd /var/www/erp-cuop
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
sudo chown -R www-data:www-data storage bootstrap/cache
```

Edite `.env` (los valores que importan en producción):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.midominio.mx
APP_TIMEZONE=America/Mexico_City

DB_DATABASE=erp_cuop
DB_USERNAME=erp
DB_PASSWORD=UNA-CONTRASEÑA-LARGA

QUEUE_CONNECTION=database

# Correo para enviar recibos (Gmail Workspace, Microsoft 365, Amazon SES, etc.)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=caja@midominio.mx
MAIL_PASSWORD=contraseña-de-aplicación
MAIL_FROM_ADDRESS=caja@midominio.mx
MAIL_FROM_NAME="CUOP Caja"
```

```bash
php artisan migrate --force
php artisan db:seed --class=CatalogosSeeder --force   # roles, sedes, carreras y planes; solo Loreto activa (NO usar --seed)
php artisan optimize
```

Cree el primer administrador (después se crean los demás desde **Administración › Usuarios**):

```bash
php artisan tinker --execute='$u = App\Models\User::create(["name"=>"Administrador","email"=>"admin@midominio.mx","password"=>bcrypt("CAMBIAR-YA"),"email_verified_at"=>now()]); $u->assignRole("Administrador");'
```

## 4. Servidor web y HTTPS

```bash
sudo cp deploy/nginx-erp-cuop.conf /etc/nginx/sites-available/erp-cuop
sudo sed -i 's/erp.midominio.mx/SU-DOMINIO/' /etc/nginx/sites-available/erp-cuop
sudo ln -s /etc/nginx/sites-available/erp-cuop /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d SU-DOMINIO
```

## 5. Procesos en segundo plano (obligatorio)

**Cola** — envía los recibos por correo y los mensajes de WhatsApp:

```bash
sudo cp deploy/supervisor-erp-cuop.conf /etc/supervisor/conf.d/erp-cuop.conf
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

**Programador** — recargos diarios, mensualidad el día 1 y recordatorios de WhatsApp:

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/erp-cuop && php artisan schedule:run >> /dev/null 2>&1
```

Verifique con `php artisan schedule:list`.

## 6. Respaldos diarios

```bash
sudo bash -c 'printf "[client]\nuser=erp\npassword=UNA-CONTRASEÑA-LARGA\n" > /root/.my.cnf && chmod 600 /root/.my.cnf'
sudo crontab -e
```

```cron
30 2 * * * DB_DATABASE=erp_cuop /var/www/erp-cuop/scripts/respaldo-mysql.sh >> /var/log/erp-respaldo.log 2>&1
```

Guarda 30 días en `/var/backups/erp-cuop`. **Copie esos archivos fuera del servidor**
(Google Drive, S3, otro equipo): un respaldo en el mismo disco no sirve si el servidor se pierde.
Para restaurar: `gunzip -c archivo.sql.gz | mysql erp_cuop`.

## 7. Actualizar a una versión nueva

```bash
cd /var/www/erp-cuop && scripts/desplegar.sh
```

Pone el sitio en mantenimiento, baja lo último de `main`, migra, recompila y reinicia la cola.

## 8. Carga inicial de datos (Loreto)

```bash
# 1) Padrón (o desde Padrón › Importar Excel en la pantalla)
# 2) Saldos del sistema anterior: simular primero, luego migrar
php artisan cobranza:migrar-saldos saldos_para_migrar.xlsx --simular
php artisan cobranza:migrar-saldos saldos_para_migrar.xlsx
```

Use los archivos de Loreto (`padron_lor_para_importar.xlsx` y `saldos_lor_para_migrar.xlsx`).
Antes de importar, asigne plan a los 10 alumnos activos marcados en rojo en la hoja «Revisión».

Ajuste el folio inicial para que continúe la numeración del sistema anterior:

```bash
php artisan tinker --execute='App\Models\FolioConsecutivo::updateOrCreate(["serie"=>"PAGOS"],["ultimo_folio"=>ULTIMO_FOLIO_DE_INTELL,"longitud"=>5]);'
```

### Sumar otra sede más adelante

El sistema arranca **solo en Loreto**: las otras 4 sedes, sus 55 planes y las 10 carreras que no se
imparten en Loreto ya están registradas pero **inactivas** (no aparecen en selectores, filtros,
reportes ni importación). Para sumar una sede:

1. **Administración › Catálogos › Sedes:** «Activar» la sede (y en *Carreras* las que imparta, con su modalidad).
2. **Catálogos › Planes de pago:** revisar sus planes y montos.
3. Importar su padrón y migrar sus saldos igual que en Loreto (cada archivo solo con alumnos de esa sede).

No se puede desactivar una sede o carrera que tenga alumnos activos.

> **Facturación:** este sistema emite recibos internos de pago; las facturas (CFDI) las emite otro sistema.

---

## 9. WhatsApp Business (Cloud API de Meta)

El sistema funciona en **modo prueba** (`WHATSAPP_DRIVER=log`) hasta completar estos pasos:
registra los mensajes en la bandeja pero no envía nada.

1. **Cuenta:** en <https://business.facebook.com> cree o verifique el portafolio de la institución.
2. **App:** en <https://developers.facebook.com> → *Crear app* → tipo *Empresa* → agregue el producto **WhatsApp**.
3. **Número:** registre un número que **no** esté usando WhatsApp normal ni WhatsApp Business app
   (puede ser un número nuevo de la institución). Anote el **Phone number ID**.
4. **Token permanente:** en *Configuración del negocio › Usuarios del sistema* cree un usuario del sistema
   con permiso `whatsapp_business_messaging` y genere un token (sin vencimiento).
5. **App secret:** en la app, *Configuración › Básica › Clave secreta de la app*.
6. **Webhook:** en *WhatsApp › Configuración*:
   - URL de devolución de llamada: `https://SU-DOMINIO/webhooks/whatsapp`
   - Token de verificación: invente una cadena larga y póngala también en `WHATSAPP_VERIFY_TOKEN`
   - Suscríbase al campo **messages**.
7. **Plantillas:** en *WhatsApp Manager › Plantillas de mensajes* cree estas 4, categoría **Utilidad**,
   idioma **Español (MEX)**, con este texto exacto:

| Nombre | Texto |
|---|---|
| `recordatorio_pago` | Hola {{1}}, te recordamos que tu pago de {{2}} por ${{3}} vence el {{4}}. Paga a tiempo y evita el recargo. Responde BAJA si no deseas recibir avisos. |
| `aviso_recargo` | Hola {{1}}, tu pago de {{2}} ya generó recargo. Tu saldo es de ${{3}}. Si liquidas antes del día 16 evitas el bloqueo de plataformas. Responde BAJA si no deseas recibir avisos. |
| `aviso_bloqueo` | Hola {{1}}, por un adeudo de ${{2}} ({{3}}) tu acceso a plataformas está bloqueado. Se libera al liquidar. Responde a este mensaje si necesitas ayuda. |
| `pago_recibido` | Hola {{1}}, recibimos tu pago de ${{2}} por {{3}}. Folio {{4}}. ¡Gracias! |

   Si Meta pide cambiar el texto, cambie también `App\Services\WhatsApp\Plantillas::TEXTOS`
   para que la vista previa coincida. Si usa otros nombres, defínalos con `WA_TPL_*` en `.env`.

8. **`.env`:**

```dotenv
WHATSAPP_DRIVER=cloud
WHATSAPP_TOKEN=EAAG...
WHATSAPP_PHONE_NUMBER_ID=1234567890
WHATSAPP_APP_SECRET=abcdef...
WHATSAPP_VERIFY_TOKEN=la-cadena-del-paso-6
COBRANZA_DATOS_PAGO="Banco X · CLABE 000000000000000000 · a nombre de ... Envía tu comprobante con tu matrícula."
COBRANZA_HORARIO="lunes a viernes de 9:00 a 18:00"
```

```bash
php artisan optimize && php artisan queue:restart
```

9. **Prueba:** desde el estado de cuenta de un alumno con su celular envíe un aviso; escriba «Hola»
   al número de la institución y verifique que el asistente responda en **WhatsApp** del menú.
10. **Recordatorios automáticos:** cuando todo funcione, `WHATSAPP_RECORDATORIOS=true`.
    Ensaye antes: `php artisan whatsapp:recordatorios --fecha=2026-12-08 --simular`.

### Asistente con IA (opcional)

Con la IA apagada, el chatbot responde por reglas (menú 1–4). Al encenderla, las **preguntas libres**
de un número registrado («¿por qué tengo recargo?», «¿ya se registró mi pago de ayer?») las contesta
Claude, con los datos de **ese** alumno: adeudos, últimos pagos, beca, bloqueo y reglas de cobro. El menú,
BAJA/ALTA y los números no registrados siguen por reglas. Si la pregunta es una queja, un trámite o algo
que no está en los datos, responde que un asesor dará seguimiento y marca la conversación en ámbar.

1. Cree una llave en <https://platform.claude.com> (Settings › API keys) y defina un límite de gasto mensual.
2. En `.env`: `CHATBOT_IA=true` y `ANTHROPIC_API_KEY=sk-ant-...`; luego `php artisan optimize`.
3. `CHATBOT_IA_MAX_DIA` limita cuántas respuestas con IA recibe cada número al día (después responde
   el bot de reglas). Usa `claude-opus-5-5` con esfuerzo bajo.

**Privacidad:** con la IA encendida, los datos de cobranza del alumno que escribe se envían a la API de
Anthropic para generar la respuesta. Actualice su **aviso de privacidad** para mencionarlo antes de activarla.

**Reglas que aplica el sistema:** solo escribe a alumnos con celular registrado; respeta la
BAJA (el alumno responde «BAJA» y deja de recibir avisos; «ALTA» lo reactiva); manda cada aviso
una vez al día como máximo; el chatbot solo da información del alumno cuyo celular o teléfono
de tutor coincide con el número que escribe. Meta cobra por conversación iniciada con plantilla;
consulte la tarifa vigente para México en su panel.

---

## Hosting compartido con cPanel

Funciona, con limitaciones. Requisitos: PHP 8.3 con las extensiones de arriba, MySQL 8,
acceso **SSH** y **cron**. Sin SSH no es viable.

- Suba el código fuera de `public_html` (p. ej. `~/erp-cuop`) y apunte el dominio o subdominio a
  `~/erp-cuop/public` (en *Dominios* del cPanel), o reemplace `public_html` por un enlace simbólico.
- Compile los assets en su computadora (`npm run build`) y suba la carpeta `public/build`
  si el hosting no tiene Node.
- No hay supervisor: procese la cola con cron cada minuto:

```cron
* * * * * cd ~/erp-cuop && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd ~/erp-cuop && php artisan queue:work database --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

- Use los respaldos del propio cPanel o programe `scripts/respaldo-mysql.sh` con cron.
- El webhook de WhatsApp necesita HTTPS (cPanel suele incluir AutoSSL).
