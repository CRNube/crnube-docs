# CRNube Docs

Gestor de documentos para WordPress: categorías de varios niveles que se contraen, búsqueda, filtro por año y diseño configurable por sitio. Hecho para publicar estados financieros, informes y documentos normativos de cooperativas.

**Requisitos:** PHP 8.1+, WordPress 6.5+. Listo para el directorio de WordPress.org (`readme.txt`); el área protegida y los reportes están en el complemento **CRNube Docs Pro**.

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

## Contadores de vistas y descargas

Cada documento muestra en la Biblioteca cuántas veces se vio y se descargó. *Vista* es abrirlo en el navegador (botón Ver o el título); *Descarga* es el botón Descargar. No se cuentan bots, precargas del navegador ni clics repetidos de la misma persona en 30 segundos. No se guarda nada del visitante: la clave anónima que evita contar dos veces cambia cada día y dura 30 segundos. Se puede apagar en **Ajustes › Avanzado**.

## CRNube Docs Pro

El complemento **CRNube Docs Pro** (repositorio aparte, `crnube-docs-pro`) agrega:

- **Área protegida**: grupos con vencimiento, portal de asociados con código por correo (`[crnube_docs_portal]` y widget de Elementor) y archivos privados fuera del alcance público.
- **Reportes**: quién vio o descargó qué y cuándo, dispositivo, navegador, país, hora del día y exportación CSV.

Sin Pro, este plugin funciona completo. Si se desactiva Pro, los documentos que estaban restringidos **siguen cerrados**: solo el personal puede abrirlos (`StaffOnlyPolicy`), y la Biblioteca lo avisa. Los grupos, el historial y los ajustes de Pro se conservan para cuando vuelva a activarse.

## Permisos

| Rol | Documentos y categorías | Reportes (Pro) | Acceso (Pro) | Ajustes |
|---|---|---|---|---|
| Administrador | ✔ | ✔ | ✔ | ✔ |
| Editor | ✔ | ✔ | ✔ | — |
| **Gestor de documentos** | ✔ (solo esto y subir archivos) | ✔ | ✔ | — |
| **Miembro de documentos** | — (solo abre documentos de sus grupos, desde el portal) | — | — | — |

## Descargas

Los enlaces apuntan a `/doc-download/{id}/view|download/`, nunca al archivo directo: no se rompen al reemplazar el archivo, cuentan descargas y son el único punto donde se verifica el acceso (`crnd_access_policy`).

## Personalización para desarrolladores

- **Plantillas**: copie `templates/{tree,sections,grid,list}.php` o `templates/parts/*.php` a `{tema}/crnube-docs/`.
- **CSS**: todas las reglas usan variables (`--crnd-primary`, `--crnd-radius`, …) sobre `.crnd`.
- **Filtros**: `crnd_presets`, `crnd_access_policy`, `crnd_login_url`, `crnd_allowed_mimes`, `crnd_download_base`, `crnd_sendfile_header`, `crnd_template_path`, `crnd_tree_limit`, `crnd_visitor_ip`. **Acciones**: `crnd_loaded`, `crnd_before_download`, `crnd_hit` (cada vista o descarga contada).
- **Para complementos** (así se conecta Pro): `crnd_settings_defaults` + `crnd_settings_sanitize` (ajustes propios en la misma opción; se conservan si el complemento se desactiva), acción `crnd_settings_cards` (tarjetas en Ajustes, con `SettingsPage::fieldName()`), `crnd_library_config` + acción `crnd_library_enqueue` (datos y scripts de la Biblioteca), hooks JS `crnd.library.documentColumns` y `crnd.library.categoryActions` (ver `assets/src/library/extensions.js`), `crnd_rest_document`, `crnd_rest_document_args`, `crnd_rest_document_update` y `crnd_submenu_order`.

## Desarrollo

```bash
composer install
npm install
npm run build           # compila la Biblioteca (assets/src → build/); build/ va en el repo
npm run start           # lo mismo, recompilando al guardar
composer check          # PHPCS (seguridad WordPress) + PHPStan nivel 6
npm run lint:js         # ESLint + Prettier (estándar de WordPress)
composer translations   # traducciones PHP y JSON de JS para todos los locales es_*; falla si falta un texto
php bin/translations.php --prune   # además quita del archivo fuente los textos que ya no se usan
```

Los textos del código están en inglés; la traducción fuente es `languages/crnube-docs-es_CR.l10n.php` (PHP y JS).

**API REST** (`crnd/v1`, usada por la Biblioteca): `GET /documents`, `POST /documents/upload`, `PATCH|DELETE /documents/{id}`, `POST /documents/{id}/file`, `POST /documents/bulk`, `GET /documents/years`, `GET|POST /categories`, `PATCH|DELETE /categories/{id}`, `POST /categories/reorder`. Todas exigen sesión y nonce, y verifican los permisos de cada documento.
