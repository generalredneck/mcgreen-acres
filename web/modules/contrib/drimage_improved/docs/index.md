# Dynamic Responsive Image (Drimage) - Improved

Responsive images in Drupal without configuring a single responsive image style.

## What it does

The module ships one field formatter, **Dynamic Responsive Image**. It renders an image
field as a placeholder, measures the space the image actually occupies in the browser, and
requests a derivative at that width. Image styles are created on demand, so there is no
breakpoint mapping to maintain.

### Key features

- **No configuration required**: no responsive image styles, no breakpoints, no `sizes` attribute.
- **Derivatives on demand**: one image style per requested width, created the first time it is asked for.
- **Fewer derivatives**: a threshold rounds requested widths, so a site does not fill the disk.
- **WebP support**: through Drupal core's image toolkit or ImageAPI Optimize WebP.
- **Cropping options**: scale, fixed aspect ratio, background image, container size, or Image Widget Crop.
- **Focal point aware**: uses Focal Point crops when the module is installed.
- **Loading hints**: native lazy loading and a `fetchpriority` attribute per display.

## Getting started

### For site builders and content editors

- [Installation and Setup](1-users/0-installation.md) - install the module and render a first image
- [Image Fields](1-users/1-image-fields.md) - use the formatter on a plain image field
- [Media References](1-users/2-media-references.md) - use the formatter when content references media

### For site administrators

- [Configuration](2-admins/0-configuration.md) - thresholds, upscaling, placeholders, caching
- [Image Handling](2-admins/1-image-handling.md) - scale, crop, background and container modes
- [WebP Images](2-admins/2-webp.md) - serve WebP derivatives

### For developers

- [Architecture Overview](3-developers/0-architecture.md) - the request flow, from placeholder to derivative
- [Testing](3-developers/1-testing.md) - run the test suites

## Quick links

- [Project page](https://www.drupal.org/project/drimage_improved)
- [Issue queue](https://www.drupal.org/project/issues/drimage_improved)
- [FAQ](faq.md)
