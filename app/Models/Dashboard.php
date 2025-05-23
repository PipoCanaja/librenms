<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'dashboard_id';
    protected $fillable = ['user_id', 'dashboard_name', 'access'];

    // ---- Query scopes ----

    /**
     * @param  Builder  $query
     * @param  User  $user
     * @return Builder|static
     */
    public function scopeAllAvailable(Builder $query, $user)
    {
        return $query->where('user_id', $user->user_id)
            ->orWhere('access', '>', 0);
    }

    // ---- Define Relationships ----
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserWidget, $this>
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(UserWidget::class, 'dashboard_id');
    }
}
