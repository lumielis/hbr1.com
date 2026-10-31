<?php

namespace App\Models;

use Azera\Orm\Model;

class GAPGalleryPicture extends Model
{
    public int $GAP_Index;
    public int $GAP_GAL_Index;
    public string $GAP_Title;
    public string $GAP_File;
    public string $GAP_Copyright;
    public function source(): string { return 'GAP_GalleryPicture'; }

    /** The legacy gallery tables use their own index column, not `id`. */
    public function idFields(): array { return ['GAP_Index']; }
}