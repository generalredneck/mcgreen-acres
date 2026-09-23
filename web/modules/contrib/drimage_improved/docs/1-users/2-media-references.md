# Media References

**Dynamic Responsive Image** is an *image field* formatter. It does not appear on a field
that references media, because that field holds a reference, not an image.

This is the most common reason people cannot find the formatter: modern sites store images
as media entities, so the content type has an entity reference field, and the image field
lives one level deeper, on the media type.

## Configure it in two places

### 1. On the media type

1. Go to **Structure > Media types > Image > Manage display**
   (`/admin/structure/media/manage/image/display`).
2. Set the **Image** field's **Format** to **Dynamic Responsive Image**.
3. Save.

![The Image media type rendering its image field through the formatter](https://www.drupal.org/files/issues/2026-08-22/drimage-improved-04-manage-display-media-type.png)

### 2. On the content type

1. Go to the content type's **Manage display** screen.
2. Set the media reference field's **Format** to **Rendered entity**.
3. Pick the media **view mode** you configured in step 1 (`Default` above).
4. Save.

The reference field now renders the media entity, and the media entity renders its image
field through this module.

## Multiple view modes

Configure the formatter per media view mode. A wide `Hero` view mode and a small
`Thumbnail` view mode can use different image handling, and each reference field chooses
the view mode it needs.

## Checklist when the image does not appear

- Is the **media type's** display using the formatter, not just the content type's?
- Is the reference field set to **Rendered entity** rather than a label or a link?
- Does the view mode selected on the reference field match the one you configured?
