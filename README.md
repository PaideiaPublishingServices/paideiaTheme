# Paideia — tema moderno para OJS 3.4 y 3.5

Tema gratuito (GPL v3) para Open Journal Systems con la estética y las funciones de las revistas científicas actuales: página de artículo de revista indexada, texto completo HTML dentro de la página, metadatos SEO completos y modo oscuro. Es un *child theme* del tema Default de OJS.

Desarrollado por [Paideia Studio](https://paideiastudio.net/). El tema es libre; el soporte, la configuración y las actualizaciones gestionadas son servicios pagos. Consultas: **contact [at] paideiastudio.net**.

Guía con capturas: **https://paideiapublishingservices.github.io/paideiaTheme/** (fuente en [`docs/`](docs/)).

## Instalación

1. Descargar `paideia-x.y.z.w.tar.gz` desde [Releases](../../releases).
2. En OJS: *Configuración → Sitio web → Módulos → Cargar un nuevo módulo* y subir el archivo.
3. *Configuración → Sitio web → Apariencia → Tema*: elegir **Paideia** y revisar las opciones.

También se puede copiar la carpeta en `plugins/themes/paideia` (el nombre del directorio **debe** ser `paideia`) y registrar la versión con `php lib/pkp/tools/installPluginVersion.php plugins/themes/paideia/version.xml`.

## Qué incluye

**Página de artículo**
- Sección, acceso abierto, licencia ("CC BY-NC-SA 4.0"), número y páginas.
- Título, subtítulo y títulos en otros idiomas.
- Autores con ORCID (marca si no está verificado), autor de correspondencia y afiliaciones numeradas con ROR.
- Fechas de recepción, aceptación, publicación y actualización.
- DOI con botón para copiar.
- Resúmenes por idioma en pestañas y palabras clave que enlazan a la búsqueda.
- Índice lateral fijo con la sección visible resaltada.
- **Texto completo HTML inline** (opcional), con figuras ampliables, vista previa de las citas y tablas con scroll.
- Descargas (PDF, HTML) una sola vez, arriba. **Cómo citar** en una ventana, con los formatos del plugin Citation Style Language, copia y descarga RIS/BibTeX.
- Panel lateral fijo: métricas, **Citado por** (OpenAlex), **Artículos relacionados** (por palabras clave compartidas, sin el índice de búsqueda) y "Publicado en".
- **Crossmark** (opcional).
- Botón para volver arriba con el progreso de lectura.

**Inicio**
- Portada con descripción, ISSN, editorial y llamadas a la acción.
- Bloque **Indexada en**: 15 índices predefinidos (Scopus, Web of Science, DOAJ, SciELO, Redalyc, Latindex, PubMed, LILACS, Dialnet, REDIB, ERIH PLUS, EBSCO, MIAR, Biblat) que se activan con una casilla y un enlace opcional, más logos propios. Arriba en el inicio o abajo en todas las páginas.
- Número actual con su sumario, o **publicación continua**: número en curso (artículos del más nuevo al más antiguo) y último número cerrado.
- Pestañas **Más vistos / Más descargados** (estadísticas de los últimos 12 meses).
- Avisos en tarjetas y buscador de números por año.

**Todo el sitio**
- Menú principal en una línea: lo que no entra pasa a un menú **Más**.
- Selector de idioma en el menú (botones hasta 3 idiomas; desplegable si hay más).
- Barra opcional con el último aviso y **aviso de cookies** opcional, con texto editable.
- Páginas de texto en una columna de lectura centrada; sumario de número, archivo, búsqueda, avisos, ingreso y registro con el mismo diseño.
- Bloques de la barra lateral al final de la página, en mosaico.
- Pie de página en 2, 3 o 4 columnas.
- Anula lo que el tema ya resuelve: bloques "Enviar un artículo" e "Idioma", y el "Cómo citar" del plugin CSL en el panel lateral.

**SEO y accesibilidad**
- JSON-LD `ScholarlyArticle` y `Periodical`, Open Graph, enlace canónico.
- `citation_fulltext_html_url` apuntando a la página del artículo cuando el texto está inline.
- Fuentes locales (Inter y Source Serif 4, OFL), sin servicios de terceros.
- Foco visible, pestañas accesibles por teclado, `prefers-reduced-motion` y estilos de impresión.

**Idiomas de la interfaz:** español, inglés, portugués (Brasil) e indonesio.

## Compatibilidad

| | OJS 3.4 | OJS 3.5 |
|---|---|---|
| Probado en | 3.4.0.10 | 3.5.0.5 |
| PHP | 8.0+ | 8.2+ |

Las diferencias entre versiones se resuelven en `classes/Compat.php`.

## Notas

### Pie de página en columnas

El contenido se edita en *Apariencia → Configuración → Pie de página*. Cada línea horizontal (`<hr>`) abre una columna; si no hay líneas pero hay dos o más títulos (h2–h4), cada título abre una columna.

### Texto completo inline

- Se usa la primera galerada HTML. Su enlace lleva a la página del artículo y la lectura cuenta como solicitud HTML en las estadísticas (COUNTER *Request*).
- El HTML se limpia antes de insertarlo: scripts, atributos `on*`, URLs `javascript:`/`data:`, iframes, objetos y formularios.
- En revistas por suscripción, el texto sólo se inserta si OJS confirma que el lector tiene acceso.
- **Tipografía del tema**: recomendado para HTML generado desde JATS. **Estilos de la galerada**: conserva el diseño propio, encapsulado con `@scope`.

### Integración con plugins de producción

Un plugin puede entregar el HTML del texto completo aunque el artículo no tenga galerada HTML (por ejemplo, generado desde JATS):

```php
Hook::add('PaideiaTheme::articleFulltext', function (string $hookName, array $args) {
    // $args: [&$html, &$galley, $article, $publication, $request]
    $args[0] = $miRenderizador->html($args[3]);
    return false;
});
```

### Aviso de cookies

Muestra un aviso con "Aceptar" y "Sólo necesarias" y guarda la elección en el navegador. El tema no bloquea scripts de terceros: los servicios de analítica deben esperar el evento `pdt:cookies` o leer `document.documentElement.dataset.pdtCookies`.

## Crédito y licencia

Software libre bajo la **GPL v3** con un **término adicional (sección 7b)**: todo sitio que lo use debe mantener visible el crédito del pie de página. Ver `LICENSE`.

El crédito se inyecta desde PHP y el JavaScript del tema verifica que siga visible; si se oculta o se borra, el tema se desactiva en el navegador. No es un DRM: es una forma razonable de acompañar la condición de la licencia.

Las fuentes Inter y Source Serif 4 se distribuyen bajo la SIL Open Font License (ver `fonts/`).
