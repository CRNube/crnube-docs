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

Para mostrar los documentos en el sitio, pegue el shortcode en una página o en un widget *Shortcode* de Elementor.

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
npm install
npm run build           # compila la Biblioteca (assets/src → build/); build/ va en el repo
npm run start           # lo mismo, recompilando al guardar
composer check          # PHPCS (seguridad WordPress) + PHPStan nivel 6
npm run lint:js         # ESLint + Prettier (estándar de WordPress)
composer translations   # traducciones PHP y JSON de JS para todos los locales es_*; falla si falta un texto
```

Los textos del código están en inglés; la traducción fuente es `languages/crnube-docs-es_CR.l10n.php` (PHP y JS).

**API REST** (`crnd/v1`, usada por la Biblioteca): `GET /documents`, `POST /documents/upload`, `PATCH|DELETE /documents/{id}`, `POST /documents/{id}/file`, `POST /documents/bulk`, `GET /documents/years`, `GET|POST /categories`, `PATCH|DELETE /categories/{id}`, `POST /categories/reorder`. Todas exigen sesión y nonce, y verifican los permisos de cada documento.
