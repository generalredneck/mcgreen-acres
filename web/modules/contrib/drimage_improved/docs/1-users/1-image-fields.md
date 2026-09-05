# Image Fields

The formatter works on any field of type **Image**, on any entity type.

## Configure the field

1. Go to the entity's **Manage display** screen, for example
   `/admin/structure/types/manage/article/display`.
2. Set the field's **Format** to **Dynamic Responsive Image**.
3. Open the settings (the gear icon) to choose how the image is handled.
4. **Update**, then **Save**.

![The formatter settings, with the image handling options](https://www.drupal.org/files/issues/2026-08-22/drimage-improved-03-formatter-settings.png)

## Settings on the formatter

| Setting | Effect |
| --- | --- |
| Link image to | Nothing, the content, or the image file. |
| Image handling | Scale, fixed aspect ratio, background image, container size, or Image Widget Crop. See [Image Handling](../2-admins/1-image-handling.md). |
| Fetch priority | Sets `fetchpriority` on the `<img>`: `auto`, `high` or `low`. |
| Image loading | Core's setting: `lazy` or `eager`. |

Use **Fetch priority: high** for the one image that is the largest element above the fold,
and leave everything else on `auto`.

## Multi-value fields

Every value renders through the same settings. A three-value field produces three
independently measured images.

## What the markup looks like

```html
<div class="drimage" data-drimage_improved="{...}">
  <img class="drimage-image" src="/sites/default/files/styles/drimage_improved_920_0/public/…" alt="…">
</div>
```

The `data-drimage_improved` attribute carries the file id, the original dimensions and the
active settings. The JavaScript reads it, measures the wrapper, and sets the `src`.
