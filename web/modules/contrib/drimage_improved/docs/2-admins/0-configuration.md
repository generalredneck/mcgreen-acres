# Configuration

One settings page, at **Configuration > Media > Drimage settings**
(`/admin/config/media/drimage_improved`). It needs the *administer image styles* permission.

![The Drimage settings form](https://www.drupal.org/files/issues/2026-08-22/drimage-improved-01-settings.png)

## Controlling how many image styles exist

| Setting | Default | What it does |
| --- | --- | --- |
| Minimum difference per image style | 200 | Requested widths are rounded to this step, so the site holds one style per step instead of one per pixel. |
| Maximum allowed ratio distortion | 60 | How far an existing cropping style may be reused for a slightly different aspect ratio, in minutes of arc. The browser absorbs the difference. |
| Maximum image style width | 3840 | Nothing wider than this is generated. |
| Minimum image style width | 320 | Nothing narrower than this is generated. |
| Enable device pixel ratio detection | off | Requests a larger derivative on high density screens. |

Raising the minimum difference means fewer files and slightly softer images on
in-between widths. Lowering it means sharper images and more derivatives on disk. The
default of 200 pixels is a reasonable starting point.

!!! note
    A width is only accepted if it is the maximum, or if `width - minimum` divides exactly
    by the threshold. The JavaScript already requests such widths.

## Loading and placeholders

| Setting | Default | What it does |
| --- | --- | --- |
| Lazyloader offset | 100 | Starts loading this many pixels before the image scrolls into view. |
| Color placeholder | `#ffffff` | Color shown in the reserved space before the image arrives. |
| Use image as placeholder | off | Shows an image instead of a flat color. |
| Image placeholder | empty | Path to that placeholder image. |

## Fallbacks and caching

| Setting | Default | What it does |
| --- | --- | --- |
| Fallback Image Style | none | Delivered when no matching style can be found, instead of failing. |
| Cache maximum age | 0 | `max-age` on derivative responses. Leave disabled when the web server serves existing derivatives without bootstrapping Drupal. |

Set it to 0 when the web server is configured to serve already generated derivatives
itself, so that the server's own caching headers apply.

## WebP and cropping integrations

`Enable core webp support`, `Enable ImageAPI Optimize WebP support` and
`Enable automated_crop support` only appear when their prerequisites are met. See
[WebP Images](2-webp.md) and [Image Handling](1-image-handling.md).

## Deprecated

**Use drimage_improved JS lazyloader** exists for sites built before browsers supported
native lazy loading. Leave it off on a new site.

## Drush

Delete the generated image styles, for example after changing the threshold:

```bash
# Every generated style.
drush drimage_improved:delete-styles

# Only the styles belonging to one Image Widget Crop crop type.
drush drimage_improved:delete-styles --crop-type=square
```

The styles are regenerated on demand the next time a page is viewed.
