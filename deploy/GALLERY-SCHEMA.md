# Gallery schema decision (W5)

## Question

Two parallel gallery schemas exist in the `hbr1` database. Which one survives?

| New | Legacy |
|---|---|
| `MCG_MediaCategory` | `media_category` |
| `GAL_Gallery` | `gallery` |
| `GAP_GalleryPicture` | `gallery_picture` |

`gallery.cgi` / the ported `gallery.php` read the `GAL_` set; the Perl admin
module `include/admin/gallery.pl` reads the legacy set.

## Evidence

Measured against the live database:

```
categories   MCG_MediaCategory 19   media_category 19    identical by id+name:  YES
pictures     GAP_GalleryPicture 596 gallery_picture 596  identical file sets:   YES
galleries    GAL_Gallery 39         gallery 41          ids only in legacy:    43, 44
```

The two extra legacy galleries are:

| id | title | status | pictures |
|---|---|---|---|
| 43 | The Real Cuba | 0 (unpublished) | 0 |
| 44 | leex's | 0 (unpublished) | 0 |

Both were created in 2006, neither was ever published, neither has a single
picture. They are abandoned test rows.

Newest write in `GAL_Gallery` is `GAL_TimeModify = 1081558669` (2004-04-09),
so neither table has been written to in a long time — the gallery is effectively
read-only content today.

## Decision

**Keep the `GAL_/GAP_/MCG_` set.** It is what the public pages already read, it
holds every published gallery and picture, and it is the schema with the
documented column names.

The legacy set is a duplicate and becomes dead once `include/admin/gallery.pl`
is replaced by the PHP admin (W8).

## Migration

Nothing needs to be copied: the data is already identical.

Before dropping the legacy tables (at W8, not now):

1. Export them for the record:
   ```sql
   -- keep a copy outside the app schema
   CREATE TABLE hbr1_archive.gallery_legacy AS SELECT * FROM hbr1.gallery;
   CREATE TABLE hbr1_archive.gallery_picture_legacy AS SELECT * FROM hbr1.gallery_picture;
   CREATE TABLE hbr1_archive.media_category_legacy AS SELECT * FROM hbr1.media_category;
   ```
2. Confirm the admin no longer reads them (W8 acceptance).
3. `DROP TABLE gallery, gallery_picture, media_category;`

Do **not** drop them before W8: the Perl admin is still the only way to edit
galleries, and it would break.

## Consequences for the code

- `App\Models\GALGallery`, `GAPGalleryPicture`, `MCGMediaCategory` are the
  gallery models (already generated, with `idFields()` overrides for their
  `*_Index` primary keys).
- `App\Models\Gallery`, `GalleryPicture`, `MediaCategory` are **legacy and
  unused**; they stay until the legacy admin is retired, then get deleted
  together with the tables.
- No dual-write, no sync, no migration script — the data is already the same.

## Caveats found

- `GAL_Gallery.GAL_Password` is a plaintext gallery password (e.g.
  `'BlueLock'`), and `gallery.password` likewise. Galleries are protected by a
  shared plaintext password sent as a form field. This is a real weakness but
  it is content-level, not account-level; handle it in W5 by hashing on write
  and verifying on read, or leave as-is and note it. Decide in W5.
- `GAL_Gallery` has no `status`-independent moderation trail, and
  `GAL_Status` is the only publish flag.
