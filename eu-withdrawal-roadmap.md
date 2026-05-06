# EU Withdrawal Compliance — Roadmap

> **Archivo personal del autor.** No se publica con el plugin ni se sube al repositorio público. Mantenerlo fuera de Git (`.gitignore`) y fuera del ZIP de WordPress.org (`.distignore`).

Documento de trabajo para planificar futuras versiones. Refleja las decisiones tomadas el 2026-05-06 y se actualiza al cierre de cada release.

---

## Contexto

La Directiva (UE) 2023/2673 sobre derecho de desistimiento online es aplicable desde el **19 de junio de 2026**. Ese plazo puede caer antes de que la revisión de WordPress.org apruebe la primera versión publicada, así que las versiones tempranas del roadmap priorizan **cumplimiento legal vital** sobre pulidos de UX y accesibilidad.

Estado del código a fecha de este roadmap: muy sólido en seguridad (nonces, sanitize/escape, capabilities), HPOS declarado, i18n con `.pot` y `es_ES.po`, sin telemetría. No hay correcciones pendientes: el roadmap es de funcionalidad nueva.

Las tareas de assets visuales (banner, icono, screenshots) y la unificación `capturas/`+`screenshots/` están fuera del roadmap por ser gestión manual del autor — ver [MANUAL_WP_ORG_ASSETS.md](MANUAL_WP_ORG_ASSETS.md).

---

## Hito v1.2.0 — Cumplimiento legal vital + base WP.org ✅ Released

**Objetivo:** lo imprescindible antes de la entrada en vigor de la directiva.

- [x] **Refactor de `includes/functions-admin.php`**. Dividido en `admin/columns.php`, `admin/metaboxes.php` y `admin/bulk-actions.php`. `functions-admin.php` queda como loader.
- [x] **Actualizar `WC tested up to`** a 10.7.
- [x] **Ampliar FAQ y documentación**. Añadida sección `== Privacy ==` al `readme.txt`, FAQ ampliado con productos excluidos, hash, plazos UI, GDPR, deleciones de cuenta, hooks. README.md de GitHub actualizado en inglés y español.
- [x] **Productos/categorías excluidas (Art. 16 Dir. 2011/83/UE)**. Settings con multi-select de categorías, casilla por producto en el editor (`woocommerce_product_options_general_product_data` + `woocommerce_process_product_meta`). Detección automática en el handler que guarda meta `_ayudawp_euw_excluded_items` y avisa al admin. Nunca rechaza automáticamente.
- [x] **Acuse de recibo con hash SHA-256**. Helper `ayudawp_euw_compute_receipt_hash()`, hash guardado en meta `_ayudawp_euw_receipt_hash`, expuesto en email de confirmación y en el detalle del CPT. Submission timestamp también se guarda en `_ayudawp_euw_submitted_at`.
- [x] **Plazos legales configurables vía UI**. Settings con campo "días de cortesía" y selector "fecha de pedido vs. fecha de completado". Helper `ayudawp_euw_get_order_deadline_timestamp()` centraliza el cálculo y se reutiliza en validación y en el botón "Withdraw" de My Account.

---

## Hito v1.3.0 — Pulido WP.org (post-publicación)

**Objetivo:** una vez aprobado el plugin en el repositorio y con el cumplimiento legal cubierto, refinar accesibilidad, RGPD nativo, responsive y experiencia de admin.

- [ ] **Integración con el sistema RGPD nativo de WordPress**. Crear `includes/functions-privacy.php` con `wp_add_privacy_policy_content()`, `wp_privacy_personal_data_exporters` y `wp_privacy_personal_data_erasers` filtrando por `_ayudawp_euw_email`. Permite al admin responder a peticiones GDPR desde **Tools → Export Personal Data**.
- [ ] **Mejoras de accesibilidad en el formulario** ([includes/functions-form.php](includes/functions-form.php)): `id` en cada `<small class="ayudawp-euw-help">` enlazado por `aria-describedby`, `aria-invalid="true"` en error, `aria-live="polite"` en `.ayudawp-euw-notice`.
- [ ] **Responsive y RTL en el CSS**. `@media (max-width: 600px)` en `assets/css/frontend.css` y `frontend-rtl.css` autogenerado.
- [ ] **Filtros y búsqueda en el CPT**. En el nuevo `includes/admin/columns.php`: `restrict_manage_posts` + `pre_get_posts` para filtrar por `_ayudawp_euw_status`; `posts_search` para buscar por número de pedido o email del cliente.

---

## Hito v1.4.0 — Profundidad y soporte duradero

**Objetivo:** PDF descargable, emails HTML coherentes con la tienda.

- [ ] **Refactor de `includes/functions-emails.php`**. Antes de migrar a HTML templates, factorizar headers comunes y resolver del destinatario admin en helpers. Sin cambio de comportamiento.
- [ ] **PDF descargable de la solicitud**. `dompdf` o `tcpdf` vía Composer. Botón en email de confirmación (enlace firmado con expiración) y en `My Account → Withdrawal`. Generación on-demand, sin almacenar en disco. Incluye hash del acuse, datos del cliente, datos del pedido, motivos, timestamp.
- [ ] **Emails HTML con plantilla WooCommerce**. `wc_get_template()` con plantillas en `/templates/emails/`: `customer-withdrawal-received.php`, `customer-withdrawal-status-changed.php`, `admin-withdrawal-notification.php`. Fallback a texto plano si WC no está activo.

---

## Aplazado a versiones futuras (sin versión asignada)

Funcionalidades válidas pero no prioritarias. Se reincorporan al roadmap cuando proceda:

- **Bloque Gutenberg dedicado.** El archivo [includes/functions-shortcode.php](includes/functions-shortcode.php) tiene la docstring "Shortcode and block registration" pero sólo registra shortcode. De momento el shortcode `[ayudawp_withdrawal_form]` cubre el caso de uso.
- **Validación inline (JS) y feedback AJAX** del formulario. Hoy los errores hacen redirect con `?ayudawp_euw_error=…` (server-side, accesible). UX más fluida con `assets/js/frontend.js` y endpoint `wp_ajax_ayudawp_euw_validate_order`.
- **Selector de items reales en modo "parcial".** En [includes/functions-form.php](includes/functions-form.php), si el usuario está logueado y es propietario del pedido, sustituir el textarea libre por checkboxes con los items de `wc_get_order()->get_items()`.
- **Dashboard widget y exportación CSV.** `wp_add_dashboard_widget` con contadores por estado y últimas pendientes. Bulk action "Exportar a CSV" en el listado del CPT.
- **Hooks adicionales documentados.** Añadir filtros `ayudawp_euw_form_fields`, `ayudawp_euw_email_subject`, `ayudawp_euw_validation_result`. Sección "Hooks" centralizada en `readme.txt`.
- **API REST mínima.** Nuevo `includes/functions-rest.php` con `register_rest_route('ayudawp/v1', '/withdrawal/(?P<id>\d+)')` para consultar estado. Útil para apps móviles, Zapier, n8n.
- **Tests unitarios (PHPUnit + WP test suite).** Carpeta `/tests/`, `phpunit.xml.dist`, GitHub Actions workflow. Cubrir cálculo de plazo, sanitización del handler, honeypot y el hash del acuse.

---

## Pro 2.0 — Diferenciadores (futuro de pago)

Candidatos a versión Pro de pago, no para la versión gratuita inicial:

- **Reembolso automático vía pasarela** (Stripe, PayPal, Redsys): botón "Reembolsar y aceptar" en el CPT que invoque `WC_Payment_Gateway::process_refund()`. Esfuerzo: alto.
- **Webhooks salientes** (Slack, Discord, n8n, Zapier): UI para configurar URL destino y plantilla payload. Reutiliza la action `ayudawp_euw_after_submission` existente. Esfuerzo: medio.
- **Email multi-idioma según locale del cliente** (`_billing_locale` o WPML/Polylang). Esfuerzo: medio.
- **Informe mensual por email** al admin con tasas aceptación/rechazo y tiempo medio de resolución. Cron diario. Esfuerzo: medio.
- **Confirmación con código OTP** (6 dígitos por email antes de aceptar) como prueba reforzada. Esfuerzo: alto.

---

## Notas de mantenimiento

- Las traducciones se gestionan vía GlotPress en translate.wordpress.org una vez el plugin esté publicado en el repositorio. No hay que mantenerlas dentro del plugin más allá del `.pot` y la traducción inicial al `es_ES`.
- El `WC tested up to` se actualiza en cada release siguiendo la última estable de WooCommerce. Recordatorio en [MANUAL_WP_ORG_ASSETS.md](MANUAL_WP_ORG_ASSETS.md).
