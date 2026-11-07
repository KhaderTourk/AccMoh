<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** فاتورة ميتا مدفوعة: بتسدّ حساب ميتا (مورد)، وسعر الدولار يومها للربح بالشيكل بس. */
class MetaInvoice extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = ['invoice_date', 'amount_usd', 'meta_ref', 'fund_id', 'payment_method_id', 'usd_rate', 'cash_payment_id', 'fund_transfer_id', 'notes'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'amount_usd' => 'decimal:2', 'usd_rate' => 'decimal:4'];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
