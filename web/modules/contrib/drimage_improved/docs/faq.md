# Frequently Asked Questions

### The formatter does not appear in the Format list

The field is probably an entity reference to media, not an image field. Configure the
formatter on the media type's display instead. See
[Media References](1-users/2-media-references.md).

### How is this different from core's Responsive Image?

Core asks you to declare breakpoints, image styles and a mapping. This module measures the
space the image actually gets and requests that width, so there is nothing to declare and
nothing to keep in sync when the design changes.

### Does it need JavaScript?

Yes. The width is measured in the browser. Without JavaScript the placeholder stays, so
keep a fallback style configured if that matters for your audience.

### How many image styles will my site end up with?

Roughly `(maximum width - minimum width) / threshold` per shape in use. With the defaults
that is around eighteen widths, and one style per distinct aspect ratio and crop type on
top of that.

### Can I delete the generated image styles?

Yes, they are recreated on demand:

```bash
drush drimage_improved:delete-styles
```

### Why is an image blurry?

The threshold rounded the requested width down and the browser is scaling it up. Lower
*Minimum difference per image style*, at the cost of more derivatives.

### Do I have to disable Responsive Image?

Not strictly, but there is no reason to run both. This module replaces it.

### Does it work with S3?

Yes, with [S3 File System](https://www.drupal.org/project/s3fs) and the bundled Drimage
S3fs submodule.

### My field stores a file URI, not an image. Can it still work?

Yes. The module registers a second formatter, also called **Dynamic Responsive Image**,
for `uri` and `file_uri` fields.

### Why is the first request for an image slow?

The derivative is being generated. Later requests are served from the files directory,
and the web server can be configured to serve them without bootstrapping Drupal.
