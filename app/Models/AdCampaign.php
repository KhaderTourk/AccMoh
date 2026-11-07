<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** حملة إعلان ممول لزبون بشهر: المطلوب = الصرف × سعر البيع. */
class AdCampaign extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = ['client_id', 'period', 'on_facebook', 'on_instagram', 'spend_usd', 'sell_rate', 'notes', 'client_service_id'];

    protected function casts(): array
    {
        return ['on_facebook' => 'boolean', 'on_instagram' => 'boolean', 'spend_usd' => 'decimal:2', 'sell_rate' => 'decimal:4'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function billed(): float
    {
        return round((float) $this->spend_usd * (float) $this->sell_rate, 2);
    }

    public function platformLabel(): string
    {
        return implode(' + ', array_filter([$this->on_facebook ? 'فيس' : null, $this->on_instagram ? 'انستا' : null]));
    }
}
