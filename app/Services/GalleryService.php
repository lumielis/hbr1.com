<?php

namespace App\Services;

use App\Models\GALGallery;
use App\Models\GAPGalleryPicture;
use App\Models\MCGMediaCategory;

/**
 * The public gallery.
 *
 * Reads the `GAL_/GAP_/MCG_` schema, which is the live one: the legacy
 * `gallery/gallery_picture/media_category` tables are a byte-identical
 * duplicate (see deploy/GALLERY-SCHEMA.md).
 *
 * Active Record fits here: these are our own tables with real primary keys and
 * real CRUD in the admin. No relations are used - the legacy pages issued
 * explicit second queries and the result set is small and cached by the
 * template engine.
 */
class GalleryService
{
    public const PICTURES_PER_PAGE = 6;

    /** Categories shown on the entrance page (flag 2 = public gallery). */
    public function categories(): array
    {
        $out = [];

        foreach (MCGMediaCategory::findAll(['MCG_Status' => 1, 'MCG_Flags' => 2]) as $category) {
            $out[] = [
                'id'    => (int) $category->MCG_Index,
                'name'  => (string) $category->MCG_Name,
                'path'  => (string) $category->MCG_Path,
                // One random thumbnail per category, as the legacy page did.
                'thumb' => $this->randomPictureForCategory((int) $category->MCG_Index),
            ];
        }

        usort($out, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $out;
    }

    public function category(int $id): ?array
    {
        $category = MCGMediaCategory::findOne(['MCG_Index' => $id]);

        if ($category === null || (int) $category->MCG_Status !== 1) {
            return null;
        }

        return [
            'id'   => (int) $category->MCG_Index,
            'name' => (string) $category->MCG_Name,
            'path' => (string) $category->MCG_Path,
        ];
    }

    /**
     * Published galleries in a category, newest first.
     *
     * @return array<int, array>
     */
    public function galleriesFor(int $categoryId, int $offset = 0, int $limit = self::PICTURES_PER_PAGE): array
    {
        $query = GALGallery::query()
            ->where('GAL_Status', 1)
            ->where('GAL_MCG_Index', $categoryId)
            ->orderBy('GAL_TimeModify DESC')
            ->limit($limit, max(0, $offset));

        $out = [];

        foreach ($query->entities() as $gallery) {
            $id = (int) $gallery->GAL_Index;

            $out[] = [
                'id'      => $id,
                'title'   => (string) $gallery->GAL_Title,
                'info'    => (string) $gallery->GAL_Info,
                'thumb'   => $this->randomPictureForGallery($id),
                'count'   => GAPGalleryPicture::count(['GAP_GAL_Index' => $id]),
                'locked'  => trim((string) $gallery->GAL_Password) !== '',
            ];
        }

        return $out;
    }

    public function countGalleriesFor(int $categoryId): int
    {
        return GALGallery::count(['GAL_Status' => 1, 'GAL_MCG_Index' => $categoryId]);
    }

    public function gallery(int $id): ?array
    {
        $gallery = GALGallery::findOne(['GAL_Index' => $id]);

        if ($gallery === null || (int) $gallery->GAL_Status !== 1) {
            return null;
        }

        return [
            'id'       => (int) $gallery->GAL_Index,
            'category' => (int) $gallery->GAL_MCG_Index,
            'title'    => (string) $gallery->GAL_Title,
            'info'     => (string) $gallery->GAL_Info,
            'locked'   => trim((string) $gallery->GAL_Password) !== '',
        ];
    }

    /**
     * Pictures in a gallery.
     *
     * @return array<int, array{id: int, file: string, title: string, copyright: string}>
     */
    public function picturesFor(int $galleryId, int $offset = 0, int $limit = self::PICTURES_PER_PAGE): array
    {
        $query = GAPGalleryPicture::query()
            ->where('GAP_GAL_Index', $galleryId)
            ->orderBy('GAP_Index')
            ->limit($limit, max(0, $offset));

        $out = [];

        foreach ($query->entities() as $picture) {
            $out[] = $this->pictureRow($picture);
        }

        return $out;
    }

    public function countPicturesFor(int $galleryId): int
    {
        return GAPGalleryPicture::count(['GAP_GAL_Index' => $galleryId]);
    }

    public function picture(int $id): ?array
    {
        $picture = GAPGalleryPicture::findOne(['GAP_Index' => $id]);

        if ($picture === null) {
            return null;
        }

        $row = $this->pictureRow($picture);

        $galleryId = (int) $picture->GAP_GAL_Index;

        $row['gallery']  = $galleryId;
        $row['previous'] = $this->neighbour($galleryId, $id, '<');
        $row['next']     = $this->neighbour($galleryId, $id, '>');

        return $row;
    }

    private function neighbour(int $galleryId, int $pictureId, string $direction): ?int
    {
        $query = GAPGalleryPicture::query()
            ->where('GAP_GAL_Index', $galleryId)
            ->limit(1);

        if ($direction === '<') {
            $query->where('GAP_Index <', $pictureId)->orderBy('GAP_Index DESC');
        } else {
            $query->where('GAP_Index >', $pictureId)->orderBy('GAP_Index');
        }

        $row = $query->select()->fetchAssoc();

        return $row === false ? null : (int) ((array) $row)['GAP_Index'];
    }

    private function pictureRow(GAPGalleryPicture $picture): array
    {
        return [
            'id'        => (int) $picture->GAP_Index,
            'file'      => (string) $picture->GAP_File,
            'title'     => (string) $picture->GAP_Title,
            'copyright' => (string) $picture->GAP_Copyright,
        ];
    }

    private function randomPictureForCategory(int $categoryId): ?array
    {
        $query = GAPGalleryPicture::query()
            ->innerJoin(GALGallery::class, 'g', 'GAP_GAL_Index = g.GAL_Index')
            ->where('g.GAL_MCG_Index', $categoryId);

        $total = $query->count();

        if ($total < 1) {
            return null;
        }

        // ORDER BY RAND() would sort the whole table; a random OFFSET is a
        // single indexed pass and is accurate enough for a thumbnail.
        $row = GAPGalleryPicture::query()
            ->innerJoin(GALGallery::class, 'g', 'GAP_GAL_Index = g.GAL_Index')
            ->where('g.GAL_MCG_Index', $categoryId)
            ->limit(1, random_int(0, $total - 1))
            ->select()
            ->fetchAssoc();

        return $row === false ? null : $this->pictureRowFromArray((array) $row);
    }

    private function randomPictureForGallery(int $galleryId): ?array
    {
        $total = GAPGalleryPicture::count(['GAP_GAL_Index' => $galleryId]);

        if ($total < 1) {
            return null;
        }

        $row = GAPGalleryPicture::query()
            ->where('GAP_GAL_Index', $galleryId)
            ->limit(1, random_int(0, $total - 1))
            ->select()
            ->fetchAssoc();

        return $row === false ? null : $this->pictureRowFromArray((array) $row);
    }

    private function pictureRowFromArray(array $row): array
    {
        return [
            'id'        => (int) ($row['GAP_Index'] ?? 0),
            'file'      => (string) ($row['GAP_File'] ?? ''),
            'title'     => (string) ($row['GAP_Title'] ?? ''),
            'copyright' => (string) ($row['GAP_Copyright'] ?? ''),
        ];
    }
}
