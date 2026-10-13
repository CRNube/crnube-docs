# CRNube Docs

Gestor de documentos para WordPress: categorías de varios niveles que se contraen, búsqueda, filtro por año y diseño configurable por sitio. Hecho para publicar estados financieros, informes y documentos normativos de cooperativas.

**Requisitos:** PHP 8.1+, WordPress 6.5+.

## Uso

**Documentos › Biblioteca** es la pantalla principal:

- **Categorías a la izquierda**: crear, renombrar, mover dentro de otra, subir/bajar, eliminar (los documentos nunca se borran: pasan a la categoría superior) y copiar el shortcode.
- **Subir**: arrastre PDF, Word, Excel o PowerPoint sobre la lista (quedan en la categoría abierta) o **sobre una categoría del árbol**. El título se toma del nombre del archivo (`Informe-Anual-de-Riesgos-2024.pdf` → *Informe Anual de Riesgos 2024*) y el año se detecta solo. Se verifica el contenido real del archivo, no solo la extensión.
- **Editar en línea**: clic en el título o el año; clic en las categorías para cambiarlas; clic en el estado para publicar/despublicar.
- **Organizar**: arrastre filas sobre una categoría para moverlas, o seleccione varias y use las acciones en lote (mover, agregar/quitar categoría, año, publicar, borrador, papelera).
- **Reemplazar archivo** desde el menú de cada fila: los enlaces publicados siguen funcionando.
- **Ordenar con el mouse**: al abrir una categoría se ven sus subcategorías como carpetas y sus propios documentos. Arrastre las filas por la agarradera (≡) para ordenarlas; la categoría pasa a **orden manual** y el sitio muestra exactamente ese orden. El menú de cada fila tiene *Mover al inicio / Subir / Bajar / Mover al final* para hacerlo con el teclado.
- **Orden en el sitio por categoría**: cada categoría elige *Como en los ajustes generales*, *Más recientes primero*, *Más antiguos primero*, *Título A–Z / Z–A* o *Manual*. Así "Estados financieros" puede seguir automática por fecha y "Normativa" en el orden que usted defina. Los documentos nuevos en una categoría manual aparecen primero hasta que los ubique.
- **Reordenar categorías arrastrando** en el árbol: soltar en el borde superior o inferior de otra categoría la coloca antes o después; soltar en el centro la mete como subcategoría.

También existen las pantallas clásicas (**Todos los documentos**, **Agregar documento**, **Categorías**) para editar todos los detalles.

Para mostrar los documentos en el sitio hay tres caminos, todos con las mismas opciones:

- **Elementor**: categoría *CRNube Docs* del panel, widgets **Biblioteca de documentos** y **Portal de asociados**. Las opciones que quedan en *Como en los ajustes* usan los de **Documentos › Ajustes**.
- **Editor de bloques**: bloque **Biblioteca de documentos** (vista previa real en el editor).
- **Shortcode** en cualquier página, widget o constructor:

```
[crnube_docs]
[crnube_docs category="informes" group_by_year="yes"]
[crnube_docs category="estados-financieros" open="first" header_style="solid" primary="#7a1fa2"]
```

**Distribuciones** (`layout`):

| Valor | Cómo se ve | Ideal para |
|---|---|---|
| `tree` | Grupos que se contraen, de cualquier profundidad | Muchas categorías y niveles |
| `sections` | Títulos grandes por categoría; subcategorías como tarjetas que se abren | Páginas de "Gobierno corporativo" o "Transparencia" |
| `grid` | Una tarjeta por documento, bajo un título por categoría (`columns` = 2, 3 o 4) | Pocas categorías, documentos destacados |
| `list` | Una sola lista con filtros de categoría, año y búsqueda | Muchos documentos de pocas categorías |

| Atributo | Valores | Por defecto |
|---|---|---|
| `layout` | `tree`, `sections`, `grid`, `list` | Ajustes |
| `columns` | `1` a `4` (solo `grid`) | Ajustes (3) |
| `category` | slugs o IDs separados por coma. Con una sola, sus documentos van arriba y sus subcategorías como grupos | todas las de primer nivel |
| `title` | texto | — |
| `search`, `year_filter`, `counts`, `date`, `size`, `type`, `toggle_all`, `group_by_year` | `yes` / `no` | Ajustes |
| `open` | `none`, `first`, `all` | Ajustes |
| `orderby` / `order` | `date`, `title`, `menu_order` / `asc`, `desc` | Ajustes |
| `depth` | niveles de grupos a mostrar (0 = todos) | 0 |
| `header_style` | `soft`, `solid`, `minimal` | Ajustes |
| `density` | `comfortable`, `compact` | Ajustes |
| `primary`, `accent` | color hex | Ajustes |
| `class` | clases CSS extra | — |

**Documentos › Ajustes** tiene la distribución por defecto, colores, tipografía, redondeo, estilo de encabezados, opciones de visualización y textos, con vista previa en vivo de las cuatro distribuciones.

**Presets de marca**: ocho diseños listos (Azul institucional, Verde cooperativo, Vino, Morado, Turquesa, Naranja cálido, Grafito sobrio, Alto contraste), todos con contraste AA. **Exportar diseño** descarga un `.json` con colores, tipografía, distribución, etc.; **Importar diseño** lo carga en otro sitio. Nada se aplica hasta guardar, y el servidor valida cada valor. Un tema o plugin puede agregar presets con el filtro `crnd_presets`.

## Migrar desde FIVO Docs

**Documentos › Importar** (requiere permiso de ajustes):

1. **Qué se importará**: muestra las categorías de FIVO como quedarán. *Omitir subcategorías genéricas* une niveles como "Ver Aquí" o "Documentos" a su categoría superior; *Limpiar títulos* convierte `Informe-Anual_2024` en *Informe Anual 2024*.
2. **Importar**: crea las categorías y un documento por archivo, en lotes de 50. Los archivos **no se copian ni se modifican**: cada documento usa el mismo adjunto, así que las páginas actuales siguen funcionando. Se puede repetir sin duplicar (solo actualiza categorías).
3. **Reemplazar shortcodes**: lista las páginas con `[fivo…]` (contenido clásico y Elementor). *Reemplazar* guarda una copia de seguridad y cambia cada shortcode por `[crnube_docs]` con las categorías equivalentes; las selecciones a mano de archivos se convierten en una categoría *Selección: {página}* bajo *Selecciones FIVO*, con el mismo orden. *Deshacer* restaura la página.

Cuando todas las páginas estén revisadas se puede desactivar FIVO Docs; los archivos quedan en la biblioteca de medios.

## Área protegida

1. **Documentos › Acceso**: cree grupos (*Junta Directiva*, *Delegados 2026*…) y agregue personas por correo, con fecha de vencimiento opcional, o **importe un CSV** (columnas nombre, correo, vence; `;` o `,`; fechas AAAA-MM-DD o DD/MM/AAAA). Puede avisarles por correo con el enlace al portal. Quien no tiene cuenta recibe una con el rol **Miembro de documentos** (sin acceso al admin).
2. **Biblioteca**: en el menú de una categoría, **Quién puede abrirla…** → *Solo miembros* de uno o varios grupos (ninguno marcado = cualquier miembro). Aplica a sus subcategorías. Cada documento puede tener su propia excepción en la columna **Acceso** (o en la caja "Quién puede abrirlo" de la edición clásica).
3. **Ajustes › Área protegida**: **Crear la página del portal** (`[crnube_docs_portal]`), mostrar los privados con candado u ocultarlos, y duración de la sesión.

- **Ingreso sin contraseña**: el miembro escribe su correo y recibe un código de 6 dígitos (10 minutos, un solo uso, 5 intentos). No se revela si un correo tiene acceso. Al vencer la membresía pierde el acceso solo.
- **Archivos privados**: se mueven a `uploads/crnd-private-{aleatorio}/` (con `.htaccess`/`web.config` que niegan todo); su URL directa deja de funcionar y solo se entregan tras verificar permisos, sin caché. La pantalla Acceso comprueba por HTTP que el servidor realmente los bloquea; en **Nginx** agregue `location ~* /wp-content/uploads/crnd-private- { deny all; }`.
- Un documento en varias categorías es privado si alguna lo es. Si se borra un grupo, sus documentos quedan solo para el personal (nunca se abren a todos por error).
- Cada vista/descarga privada queda en **Reportes** con el nombre del usuario.
- `[crnube_docs_portal category="actas"]` muestra en el portal solo esa categoría.

## Reportes

**Documentos › Reportes** muestra, para el periodo elegido (7, 30 o 90 días, este año, 12 meses o un rango propio) y opcionalmente para una categoría o un documento:

- Descargas, vistas, visitantes únicos y documentos con actividad, comparados con el periodo anterior.
- Gráfico de actividad en el tiempo (por día, o por mes en periodos largos), con los datos también en tabla.
- Documentos más consultados, actividad por categoría, dispositivos, navegadores, sistemas, países y hora del día.
- Registro de cada vista o descarga: fecha y hora, documento, dispositivo, navegador, sistema, país, página de origen y usuario (si inició sesión).
- Exportación CSV para Excel: **detalle** (una fila por evento) o **resumen por documento**.

Desde la Biblioteca, el menú de cada documento tiene **Ver estadísticas**.

**Qué cuenta**: *Vista* es abrir el documento en el navegador (botón Ver o el título); *Descarga* es el botón Descargar. No se cuentan bots, precargas del navegador ni clics repetidos de la misma persona en 30 segundos.

**Privacidad (Ley 8968)**: no se guarda la IP ni el navegador completo. Cada visitante recibe una clave anónima que cambia cada día. Quien envía "No rastrear" / *Global Privacy Control* se cuenta sin dispositivo ni país. El historial se borra solo después del plazo elegido en Ajustes (24 meses por defecto). El país solo se registra si el servidor lo informa (por ejemplo, detrás de Cloudflare); para servidores con proxy propio existe el filtro `crnd_visitor_ip`, y `crnd_visitor_country` para otra fuente de país.

## Permisos

| Rol | Documentos y categorías | Reportes | Acceso (grupos) | Ajustes |
|---|---|---|---|---|
| Administrador | ✔ | ✔ | ✔ | ✔ |
| Editor | ✔ | ✔ | ✔ | — |
| **Gestor de documentos** | ✔ (solo esto y subir archivos) | ✔ | ✔ | — |
| **Miembro de documentos** | — (solo abre documentos de sus grupos, desde el portal) | — | — | — |

## Descargas

Los enlaces apuntan a `/doc-download/{id}/view|download/`, nunca al archivo directo: no se rompen al reemplazar el archivo, cuentan descargas y son el único punto donde se verificará el acceso cuando exista el área protegida (`crnd_access_policy`).

## Personalización para desarrolladores

- **Plantillas**: copie `templates/{tree,sections,grid,list}.php` o `templates/parts/*.php` a `{tema}/crnube-docs/`.
- **CSS**: todas las reglas usan variables (`--crnd-primary`, `--crnd-radius`, …) sobre `.crnd`.
- **Filtros**: `crnd_presets`, `crnd_access_policy`, `crnd_allowed_mimes`, `crnd_download_base`, `crnd_sendfile_header`, `crnd_template_path`, `crnd_tree_limit`. **Acciones**: `crnd_loaded`, `crnd_before_download`.

## Desarrollo

```bash
composer install
npm install
npm run build           # compila la Biblioteca (assets/src → build/); build/ va en el repo
npm run start           # lo mismo, recompilando al guardar
composer check          # PHPCS (seguridad WordPress) + PHPStan nivel 6
npm run lint:js         # ESLint + Prettier (estándar de WordPress)
composer translations   # traducciones PHP y JSON de JS para todos los locales es_*; falla si falta un texto
```

Los textos del código están en inglés; la traducción fuente es `languages/crnube-docs-es_CR.l10n.php` (PHP y JS).

**API REST** (`crnd/v1`, usada por la Biblioteca): `GET /documents`, `POST /documents/upload`, `PATCH|DELETE /documents/{id}`, `POST /documents/{id}/file`, `POST /documents/bulk`, `GET /documents/years`, `GET|POST /categories`, `PATCH|DELETE /categories/{id}`, `POST /categories/reorder`. Todas exigen sesión y nonce, y verifican los permisos de cada documento.
