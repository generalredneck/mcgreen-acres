# Installation and Setup

## Requirements

- Drupal 10 or 11
- Image (Drupal core)

Disable Responsive Image if you have it enabled. This module replaces it.

## Optional modules

| Module | What it adds |
| --- | --- |
| [Focal Point](https://www.drupal.org/project/focal_point) | Crops keep the editor's chosen focal point. |
| [Image Widget Crop](https://www.drupal.org/project/image_widget_crop) | Editors crop per crop type, and the crop is applied before scaling. |
| [Automated Crop](https://www.drupal.org/project/automated_crop) | Automatic crop calculation for aspect ratio handling. |
| [ImageAPI Optimize WebP](https://www.drupal.org/project/imageapi_optimize_webp) | WebP derivatives without core's WebP support. |
| [S3 File System](https://www.drupal.org/project/s3fs) | Serve derivatives from S3 with the bundled Drimage S3fs submodule. |
| [Stage File Proxy](https://www.drupal.org/project/stage_file_proxy) | Fetch missing originals from production while developing. |

## Install

```bash
composer require drupal/drimage_improved
drush en drimage_improved
```

## Set up a first image

1. Go to **Structure > Content types > Article > Manage display** (`/admin/structure/types/manage/article/display`).
2. Set the **Format** of your image field to **Dynamic Responsive Image**.
3. Save.

![The Article display with the Dynamic Responsive Image formatter selected](https://www.drupal.org/files/issues/2026-08-22/drimage-improved-02-manage-display-content-type.png)

View a node with that field. The image now loads at a width that matches the space it
occupies, and the derivative is created on the first request.

![An article rendering an image field and a referenced media item](https://www.drupal.org/files/issues/2026-08-22/drimage-improved-05-rendered-article.png)

If your content references **media** instead of holding an image field directly, configure the
media type's display: see [Media References](2-media-references.md).

## Verify it works

- The `<img>` element sits inside a `div.drimage` wrapper.
- Its `src` points at `/sites/default/files/styles/drimage_improved_<width>_<height>/...`.
- Reload at a different browser width, and a different width appears.

## Uninstall

```bash
drush pmu drimage_improved
```

Uninstalling deletes the image styles the module generated, and their derivative files.
