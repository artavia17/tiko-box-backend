<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso de pago hecho por el cliente, a la espera de verificación.
 *
 * Reportar no es pagar: mientras el almacén no lo verifique, la factura se
 * sigue debiendo.
 */
#[Fillable([
    'package_id',
    'user_id',
    'method',
    'reference',
    'paid_on',
    'receipt_path',
    'comment',
    'amount',
    'status',
    'reviewed_by',
    'reviewed_at',
    'review_note',
])]
class PaymentReport extends Model
{
    public const PENDIENTE = 'pendiente';

    public const VERIFICADO = 'verificado';

    public const RECHAZADO = 'rechazado';

    /**
     * Formas de pago que el cliente puede reportar.
     *
     * No hay tarjeta: acá no se cobra nada en línea, el cliente avisa de un
     * pago que ya hizo por fuera y el almacén lo confirma.
     */
    public const METHODS = [
        'efectivo' => 'Efectivo',
        'sinpe' => 'SINPE Móvil',
        'transferencia' => 'Transferencia bancaria',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
