<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'sort_order'];

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }
}
