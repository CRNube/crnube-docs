# CRNube Docs

Gestor de documentos para WordPress: categorías de varios niveles que se contraen, búsqueda, filtro por año y diseño configurable por sitio. Hecho para publicar estados financieros, informes y documentos normativos de cooperativas.

**Requisitos:** PHP 8.1+, WordPress 6.5+.

## Uso

1. **Documentos › Categorías**: cree la estructura (por ejemplo *Estados financieros › Auditados*). El campo **Orden** define la posición.
2. **Documentos › Agregar documento**: título, archivo (o enlace externo), categoría y, si hace falta, el **año del documento** (el periodo que cubre; si se deja vacío se usa el año de publicación).
3. Pegue el shortcode en una página o en un widget *Shortcode* de Elementor. Cada categoría muestra el suyo listo para copiar.

```
[crnube_docs]
[crnube_docs category="informes" group_by_year="yes"]
[crnube_docs category="estados-financieros" open="first" header_style="solid" primary="#7a1fa2"]
```

| Atributo | Valores | Por defecto |
|---|---|---|
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

**Documentos › Ajustes** tiene colores, tipografía, redondeo, estilo de encabezados, opciones de visualización y textos, con vista previa en vivo.

## Permisos

| Rol | Documentos y categorías | Ajustes |
|---|---|---|
| Administrador | ✔ | ✔ |
| Editor | ✔ | — |
| **Gestor de documentos** (rol nuevo) | ✔ (solo esto y subir archivos) | — |

## Descargas

Los enlaces apuntan a `/doc-download/{id}/view|download/`, nunca al archivo directo: no se rompen al reemplazar el archivo, cuentan descargas y son el único punto donde se verificará el acceso cuando exista el área protegida (`crnd_access_policy`).

## Personalización para desarrolladores

- **Plantillas**: copie `templates/tree.php` o `templates/parts/*.php` a `{tema}/crnube-docs/`.
- **CSS**: todas las reglas usan variables (`--crnd-primary`, `--crnd-radius`, …) sobre `.crnd`.
- **Filtros**: `crnd_access_policy`, `crnd_allowed_mimes`, `crnd_download_base`, `crnd_sendfile_header`, `crnd_template_path`, `crnd_tree_limit`. **Acciones**: `crnd_loaded`, `crnd_before_download`.

## Desarrollo

```bash
composer install
composer check          # PHPCS (seguridad WordPress) + PHPStan nivel 6
composer translations   # copia es_CR al resto de locales en español y avisa textos sin traducir
```

Los textos del código están en inglés; la traducción fuente es `languages/crnube-docs-es_CR.l10n.php`.
