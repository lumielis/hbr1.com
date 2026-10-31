<?php

namespace App\Models;

use Azera\Orm\Model;

class GALGallery extends Model
{
    public int $GAL_Index;
    public int $GAL_MCG_Index;
    public string $GAL_Submitter;
    public string $GAL_Password;
    public string $GAL_Title;
    public string $GAL_Info;
    public int $GAL_Status;
    public int $GAL_TimeCreate;
    public int $GAL_TimeModify;
    public function source(): string { return 'GAL_Gallery'; }

    /** The legacy gallery tables use their own index column, not `id`. */
    public function idFields(): array { return ['GAL_Index']; }
}