<?php

namespace App\Models;

use App\Traits\BelongsToBarbearia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BarbeariaFoto extends Model
{
    use BelongsToBarbearia, HasFactory;

    protected $fillable = [
        'barbearia_id', 'foto_path', 'ordem',
    ];

    public function getFotoUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->foto_path);
    }
}
