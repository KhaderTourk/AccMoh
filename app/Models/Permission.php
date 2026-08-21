<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'module'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public static function slugs(): array
    {
        return [
            'settings', 'home', 'about', 'kanani', 'tamkeen', 'parasols',
            'scholarships', 'partners', 'content', 'applications', 'newsletter',
            'financial', 'financial_add', 'financial_review', 'financial_approve', 'users',
        ];
    }
}
