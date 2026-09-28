# Architecture Overview

## The request flow

1. **Render.** `DrImageFormatter` renders each image field value as a `div.drimage`
   wrapper holding a placeholder, plus a `data-drimage_improved` attribute with the file
   id, the original dimensions and the active settings.
2. **Measure.** `js/drimage_improved.js` measures the wrapper, rounds the width against
   the threshold, upscale and downscale settings, and writes the derivative URL into the
   `img` (and the `source` when WebP is on).
3. **Deliver.** The request hits `/drimage/{width}/{height}/{fid}/{iwc_id}/{format}`
   (`drimage_improved.image`, permission *access content*). `DrImageController` hands it to
   `DrimageManager`.
4. **Resolve a style.** `DrimageManager::findImageStyle()` looks for
   `drimage_improved_<width>_<height>`, then for an existing style whose ratio is close
   enough, and creates one with `createDrimageStyle()` if neither matched.
5. **Generate.** The manager extends core's `ImageStyleDownloadController`, so the
   derivative is generated and streamed by core, with a simulated `itok`.

## Key classes

| Class | Responsibility |
| --- | --- |
| `DrImageFormatter` | The *Dynamic Responsive Image* field formatter. |
| `DrImageUriFormatter` | The same behavior for `uri` and `file_uri` fields. |
| `DrimageManager` | Dimension validation, style lookup, style creation, delivery. |
| `DrImageController` | Thin route controller over the manager. |
| `DrimageSubscriber` | Maps a direct request for a `styles/…` path back to an on-demand style. |
| `DrimageStageFileProxySubscriber` | Lets Stage File Proxy fetch a missing original. |
| `ImageStyleRepository` | Finds and deletes the styles this module generated. |
| `DrimageSettingsForm` | The settings page. |
| `Hook\DrimageImprovedHooks` | All hook implementations, as attribute hooks. |

Hooks are attribute based (`#[Hook]`), with thin `#[LegacyHook]` wrappers in the `.module`
file so Drupal 10 keeps working.

## Image style naming

```
drimage_improved_[focal_]<width>_<height>[_<crop_type>]
```

- `focal_` appears when Focal Point is installed.
- A height of `0` means scale only, no crop.
- The crop type suffix appears for Image Widget Crop styles.

Parsing this name back into dimensions is what `DrimageSubscriber` does, so anything that
changes the naming has to change both sides.

## Submodule: Drimage S3fs

`modules/drimage_s3fs` repeats the delivery path for
[S3 File System](https://www.drupal.org/project/s3fs), where derivatives live in the
bucket rather than the public files directory.

## Extending

`drimage_improved.api.php` documents two alter hooks:

- `hook_drimage_improved_image_style_alter(ImageStyle &$style)` - change a style as it is
  created, for example to add an effect.
- `hook_drimage_improved_proxy_cache_periods_alter(array &$periods)` - change the cache
  periods used by the proxy integration.
