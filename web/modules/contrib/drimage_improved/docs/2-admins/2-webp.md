# WebP Images

Two mutually exclusive routes. Both are switched on at
`/admin/config/media/drimage_improved`, and each option only appears when it can work.

## Core WebP

Adds a convert effect to every generated image style, so derivatives are written as
`.webp`.

Requirements:

- The image toolkit reports WebP support. Check
  `/admin/reports/status/php#module_gd`; on GD that means the extension was built with
  `--with-webp`.
- ImageAPI Optimize WebP is **not** enabled.

## ImageAPI Optimize WebP

Uses [ImageAPI Optimize WebP](https://www.drupal.org/project/imageapi_optimize_webp)
instead, for sites whose toolkit cannot write WebP.

Requirements:

- The module is installed.
- A sitewide default pipeline is configured.
- The core WebP option is **off**.

## What the markup does

With WebP active, the wrapper emits a `<picture>` with a WebP `<source>` alongside the
original format, and the browser picks:

```html
<picture>
  <source data-format="webp" type="image/webp">
  <img class="drimage-image" src="…" alt="…">
</picture>
```

The `srcset` is filled in by the JavaScript once the width is known, so the browser never
downloads a size it will not use.

## Verifying

Load a page and check the network panel: requests should end in `.webp` and respond with
`content-type: image/webp`. Or from the shell:

```bash
curl -sI "https://example.com/sites/default/files/styles/drimage_improved_920_0/public/articles/photo.jpg.webp" | grep -i content-type
```

## If WebP does not appear

- Neither option is switched on, or both prerequisites failed, so the checkbox never showed.
- The toolkit has no WebP support: the option is hidden by design.
- The source file is already a WebP: no second conversion happens.
