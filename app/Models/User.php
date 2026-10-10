<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function espacos(): BelongsToMany
    {
        return $this->belongsToMany(Espaco::class)->orderBy('espacos.nome');
    }

    public function participaDe(int $espacoId): bool
    {
        return $this->espacos()->where('espacos.id', $espacoId)->exists();
    }
}
