# Image Handling

Set per display, in the formatter settings. Five modes.

## Scale

The default. The image is scaled on width and keeps its own aspect ratio. The height
follows from the source image.

Use it for editorial images where the author's framing should survive.

## Fixed aspect ratio crop

You give a ratio, for example 16 by 9, and every derivative is scaled and cropped to it.

- Focal Point, when installed, keeps the editor's chosen point inside the crop.
- Automated Crop, when installed and enabled in the settings, calculates the crop.
- The *Maximum allowed ratio distortion* setting decides how far an existing style may be
  reused for a nearby ratio.

Use it for teaser grids and cards, where every image must be the same shape.

## Background image

The derivative is emitted as a CSS `background-image` on the wrapper instead of an `<img>`.
The wrapper takes its height from your theme's CSS. You control `background-attachment`,
`background-position` and `background-size` in the settings.

Use it for hero bands whose height is fixed by the design.

## Container size

The image is scaled and cropped to the exact width *and* height of the container the
wrapper occupies, as measured in the browser.

Use it when the design fixes both dimensions and cropping is acceptable.

## Image widget crop

Requires [Image Widget Crop](https://www.drupal.org/project/image_widget_crop). Pick a crop
type, and the editor's crop for that crop type is applied first, then the result is scaled
on width.

Use it when editors must decide the crop per image, per usage.

## Fetch priority

Independent of the mode above:

| Value | When |
| --- | --- |
| Auto (default) | Everything, unless you have a reason. |
| High | The single largest image above the fold, usually a hero. |
| Low | Images far down the page that must never compete with content. |

Setting `high` on many images removes the benefit, because nothing is prioritized when
everything is.
