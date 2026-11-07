<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** حالة شهر الإعلانات: مسودة أو معتمد، مع رصيد لوحة ميتا والتسوية. */
class AdMonth extends Model
{
    use BelongsToTenant;

    protected $fillable = ['period', 'status', 'meta_balance_usd', 'expected_usd', 'settlement_usd', 'vendor_charge_id', 'settlement_ref_id', 'settlement_ref_type', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'meta_balance_usd' => 'decimal:2', 'expected_usd' => 'decimal:2', 'settlement_usd' => 'decimal:2'];
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
