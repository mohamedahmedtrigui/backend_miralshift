<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'company_ids', 'agency_id', 'start_time', 'end_time', 'color'])]
class Shift extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'company_ids', 'agency_id', 'start_time', 'end_time', 'color'];

    protected function casts(): array
    {
        return [
            'company_ids' => 'array',
        ];
    }

    // DB column is a plain `time` type and always round-trips with seconds
    // (e.g. "08:00:00"), regardless of the H:i format sent on write. Truncate
    // on read so the API only ever exposes HH:MM.
    protected function startTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? substr($value, 0, 5) : $value,
        );
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? substr($value, 0, 5) : $value,
        );
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
