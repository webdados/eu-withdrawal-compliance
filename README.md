# EU Withdrawal Compliance

WordPress plugin that adds the EU online withdrawal function required by Directive (EU) 2023/2673, mandatory from 19 June 2026 for every online retailer in the European Union.

🇬🇧 [English](#english) · 🇪🇸 [Español](#español)

---

## English

### What it does

- Creates a public withdrawal page automatically on activation, with a fully-escaped form embedded via the `[ayudawp_withdrawal_form]` shortcode. The page is pre-filled with a neutral, multilingual sample template (with a clear "review with a legal advisor" disclaimer) so you can publish it after a quick review.
- Adds a "Right of withdrawal" endpoint inside WooCommerce **My Account** with a per-order "Withdraw" button shown only while the configured withdrawal window is open.
- Injects an "Exercise withdrawal right here" notice with link to the form inside WooCommerce transactional emails (processing, on-hold and completed orders), to comply with the trader's obligation to inform consumers about the existence and placement of the withdrawal function.
- Validates the email/order pair against the WooCommerce database, including the deadline check.
- **Configurable deadline** (1.2.0+): choose whether the 14-day window starts from the order date or from the WooCommerce completion date, and add extra grace days from the settings page.
- **Article 16 exclusions** (1.2.0+): mark individual products or whole categories as excluded from the right of withdrawal (custom-made, perishable, sealed digital, etc.). Requests on orders containing excluded items are flagged for the admin to review manually — never auto-rejected, since a partial withdrawal over the rest of the order can still be valid.
- **Verifiable receipt hash** (1.2.0+): every submission generates a SHA-256 hash sent to the customer in the confirmation email so they keep a tamper-evident proof on a durable medium.
- Adds a private note to the WooCommerce order at every step of the lifecycle: when the request is received and again when it is accepted, rejected or marked as completed.
- Sends a confirmation email to the customer on submission **and** a follow-up email when the request is accepted, rejected or completed. Optional admin comment is forwarded to the customer (required for rejections, optional for completed). Notification email to the shop admin uses reply-to pointing to the customer.
- Bulk actions in the withdrawals listing to mark several requests as accepted, rejected or completed at once.
- "Withdrawal" column in the WooCommerce orders screen (legacy and HPOS) showing the status of any linked request, toggleable from "Screen Options".
- Logs every request as a private custom post type with status tracking (pending, accepted, rejected, completed), IP, user agent and submission timestamp for legal traceability.
- Integrates inside the WooCommerce admin menu when WooCommerce is active (settings live at **WooCommerce → EU Withdrawal**, request log at **WooCommerce → Withdrawals**). Falls back to a top-level menu when running standalone.

### Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- WooCommerce 7.0 or higher (recommended)

### Installation

#### From GitHub release

1. Download the latest `eu-withdrawal-compliance.zip` from [the latest release](https://github.com/fernandotellado/eu-withdrawal-compliance/releases/latest).
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip and activate.

> ⚠️ Do **not** use the green "Code → Download ZIP" button on the repo page. That zip extracts as `eu-withdrawal-compliance-main/` and would install the plugin under the wrong slug, breaking future updates from WordPress.org.

#### From source

```bash
git clone https://github.com/fernandotellado/eu-withdrawal-compliance.git
```

Move the folder to `wp-content/plugins/` and activate from the WordPress admin.

### Configuration

After activation:

1. The plugin creates a "Right of withdrawal" page automatically with a sample legal template. Review and edit it from **Pages**.
2. Go to **WooCommerce → EU Withdrawal** (or **Withdrawals → Settings** without WooCommerce) to configure the notification email and the page that hosts the form.
3. Add the URL of the withdrawal page to your footer or legal links so it is visible from any page on your site.

### Filters and hooks

| Filter | Purpose |
|--------|---------|
| `ayudawp_euw_grace_days` | Number of additional days to add to the 14-day deadline check. Default: the value stored in **WooCommerce → EU Withdrawal → Withdrawal deadline → Grace days** (`0` if unset). The filter receives that value, so `return $days + N;` adds on top of the configured grace. |
| `ayudawp_euw_skip_deadline_check` | Return `true` to disable the deadline check entirely. Receives the WC_Order as second argument. Default: `false`. |
| `ayudawp_euw_email_ids` | Array of WooCommerce email IDs where the withdrawal notice is injected. Default: `customer_processing_order`, `customer_on_hold_order`, `customer_completed_order`. |

| Action | Purpose |
|--------|---------|
| `ayudawp_euw_after_submission` | Fires after a withdrawal request has been processed. Receives the CPT ID and the submission data array. |
| `ayudawp_euw_after_status_change` | Fires after a withdrawal request changes status (individually or via bulk action). Receives the CPT ID, the new status and the optional admin comment. |

The receipt hash helper `ayudawp_euw_compute_receipt_hash( $post_id, $name, $email, $order, $scope, $date, $submitted_at )` is also available for recomputing the SHA-256 of a request from its stored meta fields when verifying a customer's saved confirmation email.

### Migrating from the old "Download ZIP" install

If you installed an early version of this plugin by clicking the green **Code → Download ZIP** button on GitHub, your install lives in `wp-content/plugins/eu-withdrawal-compliance-main/`. That is the wrong slug and it will block automatic updates from WordPress.org. Migrate like this without losing any data:

1. **Deactivate** the existing plugin (the one labeled with the `-main` suffix). Do **not** click "Delete" yet.
2. Upload the new `eu-withdrawal-compliance.zip` from the [latest release](https://github.com/fernandotellado/eu-withdrawal-compliance/releases/latest) via **Plugins → Add New → Upload Plugin**.
3. **Activate** the new plugin. It will reuse the existing settings, the existing withdrawal page and the existing log of requests automatically.
4. Now go back to **Plugins**, find the old `-main` entry and click **Delete**. The plugin's uninstall script detects the canonical install next to it and keeps every option intact.

### Compliance status

This plugin implements the **minimum compliant version** of EU Directive 2023/2673. It works on all member states from 19 June 2026.

The German interpretation of the directive (the strictest known so far) requires a two-step confirmation flow: a first button that opens the function, an intermediate page with the customer's data, and a second "confirm withdrawal" button that submits the request. This is not yet implemented because Spanish transposition is still pending as of May 2026 and a future update may be required to align with the final Spanish Real Decreto.

### Changelog

**1.2.0**
- Article 16 exclusions: mark individual products or whole WooCommerce categories as excluded from the right of withdrawal. Requests on orders containing excluded items are flagged for manual review (never auto-rejected).
- Verifiable SHA-256 receipt hash sent to the customer on submission as tamper-evident proof on a durable medium. Stored alongside the request and exposed in the detail screen.
- Configurable withdrawal deadline: choose order date vs. completion date as the basis, and add extra grace days from the settings page.
- Submission timestamp (UTC) stored on every request.
- Internal: split `functions-admin.php` into `admin/columns.php`, `admin/metaboxes.php` and `admin/bulk-actions.php`. No behavioural change.

**1.1.0**
- Customer email notifications on every status change (accepted, rejected, completed).
- Optional admin comment forwarded to the customer on status change. Required for rejections, optional for completed requests.
- WooCommerce order notes on every status change so the order timeline reflects the full withdrawal lifecycle.
- Bulk actions in the withdrawals listing to mark several requests as accepted, rejected or completed at once.
- "Withdrawal" column in the WooCommerce orders screen (legacy and HPOS) showing the status of any linked request, toggleable from "Screen Options".
- Trimmed inline styles in the WooCommerce email notice so it inherits the email template styles instead of forcing a coloured callout box.

**1.0.0**
- Initial release.

### License

GPL-2.0-or-later. See [LICENSE](LICENSE).

### Author

Created and maintained by Fernando Tellado at [AyudaWP.com](https://ayudawp.com).

For installation, configuration or custom development services, see [mantenimiento.ayudawp.com](https://mantenimiento.ayudawp.com).

### Contributing

Bug reports, pull requests and translations are welcome. Open an issue first to discuss any non-trivial change.

---

## Español

Plugin de WordPress que añade la función online de desistimiento exigida por la Directiva (UE) 2023/2673, obligatoria desde el 19 de junio de 2026 para todo comercio online de la Unión Europea.

### Qué hace

- Crea automáticamente al activar una página pública de desistimiento con el formulario embebido mediante el shortcode `[ayudawp_withdrawal_form]`. La página viene pre-rellenada con una plantilla legal de ejemplo, neutra y multilingüe, con un aviso claro de «revísalo con tu asesor legal» para que la publiques tras una revisión rápida.
- Añade un endpoint «Derecho de desistimiento» dentro de **Mi cuenta** de WooCommerce, con un botón «Desistir» por pedido que solo aparece mientras la ventana configurada sigue abierta.
- Inyecta un aviso «Solicitar desistimiento aquí» con enlace al formulario en los emails transaccionales de WooCommerce (pedido recibido, en espera y completado), cumpliendo la obligación del comerciante de informar al consumidor sobre la existencia y ubicación de la función de desistimiento.
- Valida el par email/pedido contra la base de datos de WooCommerce, incluyendo la comprobación del plazo.
- **Plazo configurable** (1.2.0+): elige si la ventana de 14 días empieza desde la fecha del pedido o desde la fecha de completado de WooCommerce, y suma días de cortesía adicionales desde la página de ajustes.
- **Exclusiones del Artículo 16** (1.2.0+): marca productos individuales o categorías enteras como excluidos del derecho de desistimiento (a medida, perecederos, contenido digital sellado, etc.). Las solicitudes sobre pedidos con productos excluidos quedan marcadas para revisión manual del admin — nunca se rechazan automáticamente, porque un desistimiento parcial sobre el resto del pedido puede seguir siendo válido.
- **Hash de acuse verificable** (1.2.0+): cada solicitud genera un SHA-256 que se envía al cliente en el email de confirmación como prueba inmutable en soporte duradero.
- Añade notas privadas al pedido de WooCommerce en cada paso del ciclo de vida: cuando se recibe la solicitud y de nuevo cuando se acepta, se rechaza o se marca como completada.
- Envía email de confirmación al cliente al recibir la solicitud **y además** un email de seguimiento cuando se acepta, se rechaza o se marca como completada. Se puede incluir un comentario opcional del administrador en el email al cliente (obligatorio en rechazos, opcional en completadas). El email al admin lleva `reply-to` apuntando al cliente.
- Acciones en lote en el listado de solicitudes para marcar varias como aceptadas, rechazadas o completadas a la vez.
- Columna «Desistimiento» en la pantalla de pedidos de WooCommerce (legacy y HPOS) que muestra el estado de la solicitud asociada al pedido. Activable/desactivable desde «Opciones de pantalla».
- Registra cada solicitud como un custom post type privado con seguimiento de estados (pendiente, aceptada, rechazada, completada), IP, user agent y timestamp de envío para trazabilidad legal.
- Se integra dentro del menú de administración de WooCommerce cuando WooCommerce está activo (los ajustes viven en **WooCommerce → EU Withdrawal**, el log de solicitudes en **WooCommerce → Withdrawals**). Si WooCommerce no está activo, cae a un menú de nivel superior propio.

### Requisitos

- WordPress 6.0 o superior
- PHP 7.4 o superior
- WooCommerce 7.0 o superior (recomendado)

### Instalación

#### Desde GitHub (Releases)

1. Descarga el archivo `eu-withdrawal-compliance.zip` desde [la última release](https://github.com/fernandotellado/eu-withdrawal-compliance/releases/latest).
2. En el panel de WordPress, ve a **Plugins → Añadir nuevo → Subir plugin**.
3. Sube el zip y activa.

> ⚠️ **No uses** el botón verde "Code → Download ZIP" del repo. Ese zip se descomprime como `eu-withdrawal-compliance-main/` e instalaría el plugin con un slug incorrecto, rompiendo las actualizaciones futuras desde WordPress.org.

#### Desde el código fuente

```bash
git clone https://github.com/fernandotellado/eu-withdrawal-compliance.git
```

Mueve la carpeta a `wp-content/plugins/` y activa desde el panel de WordPress.

### Configuración

Tras la activación:

1. El plugin crea automáticamente una página «Derecho de desistimiento» con una plantilla legal de ejemplo. Revísala y edítala desde **Páginas**.
2. Ve a **WooCommerce → EU Withdrawal** (o **Withdrawals → Settings** si no usas WooCommerce) para configurar el email de notificación y la página que aloja el formulario.
3. Añade la URL de la página de desistimiento al pie o a los enlaces legales para que sea visible desde cualquier página del sitio.

### Filtros y acciones

| Filtro | Para qué sirve |
|--------|---------|
| `ayudawp_euw_grace_days` | Días adicionales que se suman al plazo de 14 días. Por defecto: el valor guardado en **WooCommerce → EU Withdrawal → Withdrawal deadline → Grace days** (`0` si no está definido). El filtro recibe ese valor, así que `return $días + N;` suma encima de la cortesía configurada. |
| `ayudawp_euw_skip_deadline_check` | Devuelve `true` para desactivar completamente la comprobación del plazo. Recibe el WC_Order como segundo argumento. Por defecto: `false`. |
| `ayudawp_euw_email_ids` | Array de IDs de emails de WooCommerce donde se inyecta el aviso de desistimiento. Por defecto: `customer_processing_order`, `customer_on_hold_order`, `customer_completed_order`. |

| Acción | Para qué sirve |
|--------|---------|
| `ayudawp_euw_after_submission` | Se dispara tras procesar una solicitud de desistimiento. Recibe el ID del CPT y el array con los datos del envío. |
| `ayudawp_euw_after_status_change` | Se dispara tras un cambio de estado en una solicitud (individual o en lote). Recibe el ID del CPT, el nuevo estado y el comentario opcional del administrador. |

La función helper `ayudawp_euw_compute_receipt_hash( $post_id, $name, $email, $order, $scope, $date, $submitted_at )` también está disponible para recalcular el SHA-256 de una solicitud desde sus metas guardadas cuando se quiera verificar el email de confirmación que conserva el cliente.

### Migrar desde la instalación antigua de "Download ZIP"

Si instalaste una primera versión del plugin pulsando el botón verde **Code → Download ZIP** de GitHub, tu instalación vive en `wp-content/plugins/eu-withdrawal-compliance-main/`. Ese slug es incorrecto y bloquea las actualizaciones automáticas desde WordPress.org. Migra así, sin perder ningún dato:

1. **Desactiva** el plugin existente (el que termina en `-main`). **No** pulses "Eliminar" todavía.
2. Sube el nuevo `eu-withdrawal-compliance.zip` desde la [última release](https://github.com/fernandotellado/eu-withdrawal-compliance/releases/latest) en **Plugins → Añadir nuevo → Subir plugin**.
3. **Activa** el plugin nuevo. Reutilizará automáticamente los ajustes, la página de desistimiento existente y el log de solicitudes.
4. Vuelve a **Plugins**, busca la entrada con sufijo `-main` y pulsa **Eliminar**. El script de desinstalación detecta que existe la instalación canónica al lado y conserva todas las opciones intactas.

### Estado de cumplimiento

Este plugin implementa la **versión mínima conforme** con la Directiva (UE) 2023/2673. Es válida en todos los Estados miembros desde el 19 de junio de 2026.

La interpretación alemana de la directiva (la más estricta conocida hasta la fecha) exige un flujo de doble confirmación: un primer botón que abre la función, una página intermedia con los datos del cliente y un segundo botón «confirmar desistimiento» que envía la solicitud. Aún no está implementado porque la transposición española sigue pendiente a 1 de mayo de 2026, y es probable que se necesite una actualización futura para alinear el plugin con el Real Decreto definitivo.

### Registro de cambios

**1.2.0**
- Exclusiones del Artículo 16: marca productos individuales o categorías enteras de WooCommerce como excluidos del derecho de desistimiento. Las solicitudes sobre pedidos con productos excluidos se marcan para revisión manual (nunca se rechazan automáticamente).
- Hash SHA-256 de acuse verificable enviado al cliente al recibir la solicitud, como prueba inmutable en soporte duradero. Se almacena junto a la solicitud y aparece en el detalle.
- Plazo de desistimiento configurable: elige fecha de pedido vs. fecha de completado como base, y suma días de cortesía desde la página de ajustes.
- Timestamp de envío (UTC) almacenado en cada solicitud.
- Interno: `functions-admin.php` se ha dividido en `admin/columns.php`, `admin/metaboxes.php` y `admin/bulk-actions.php`. Sin cambios de comportamiento.

**1.1.0**
- Emails al cliente en cada cambio de estado (aceptada, rechazada, completada).
- Comentario opcional del administrador que se envía al cliente en el email de cambio de estado. Obligatorio en rechazos, opcional en completadas.
- Notas en el pedido de WooCommerce en cada cambio de estado, para que la línea temporal del pedido refleje todo el ciclo de vida del desistimiento.
- Acciones en lote en el listado de solicitudes para marcar varias como aceptadas, rechazadas o completadas a la vez.
- Columna «Desistimiento» en la pantalla de pedidos de WooCommerce (legacy y HPOS) que muestra el estado de la solicitud asociada al pedido. Activable/desactivable desde «Opciones de pantalla».
- Reducidos los estilos inline del aviso en el email de WooCommerce para que herede los estilos de la plantilla del email en lugar de forzar una caja con colores propios.

**1.0.0**
- Versión inicial.

### Licencia

GPL-2.0-or-later. Ver [LICENSE](LICENSE).

### Autor

Creado y mantenido por Fernando Tellado en [AyudaWP.com](https://ayudawp.com).

Para servicios de instalación, configuración o desarrollo a medida, ver [mantenimiento.ayudawp.com](https://mantenimiento.ayudawp.com).

### Contribuir

Se aceptan reportes de bugs, pull requests y traducciones. Abre un issue primero para discutir cualquier cambio no trivial.
