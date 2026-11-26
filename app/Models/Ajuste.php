<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor'];

    /** Datos de la institución que aparecen en recibos y cortes. */
    public const INSTITUCION = [
        'institucion_nombre' => 'CENTRO UNIVERSITARIO',
        'institucion_razon_social' => null,
        'institucion_rfc' => null,
        'institucion_direccion' => null,
        'institucion_telefono' => null,
        'recibo_leyenda' => 'Este recibo es un comprobante interno de pago. Consérvelo para cualquier aclaración.',
    ];

    public const LOGO = 'institucion/logo';

    public static function institucion(): array
    {
        return Cache::rememberForever('ajustes.institucion', fn () => array_merge(
            self::INSTITUCION,
            static::whereIn('clave', array_keys(self::INSTITUCION))->pluck('valor', 'clave')->filter(fn ($v) => filled($v))->all(),
        ));
    }

    public static function guardar(array $valores): void
    {
        foreach (array_intersect_key($valores, self::INSTITUCION) as $clave => $valor) {
            static::updateOrCreate(['clave' => $clave], ['valor' => filled($valor) ? trim((string) $valor) : null]);
        }
        Cache::forget('ajustes.institucion');
    }

    /** Logo como data-URI para DomPDF y correos (o null si no hay). */
    public static function logoDataUri(): ?string
    {
        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg'] as $ext => $mime) {
            $ruta = self::LOGO.'.'.$ext;
            if (Storage::disk('local')->exists($ruta)) {
                return "data:{$mime};base64,".base64_encode(Storage::disk('local')->get($ruta));
            }
        }

        return null;
    }
}
