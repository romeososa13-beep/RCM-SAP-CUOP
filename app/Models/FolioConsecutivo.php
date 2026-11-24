<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolioConsecutivo extends Model
{
    protected $table = 'folios_consecutivos';

    protected $primaryKey = 'serie';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['serie', 'ultimo_folio', 'longitud'];

    protected function casts(): array
    {
        return ['ultimo_folio' => 'integer', 'longitud' => 'integer'];
    }
}
