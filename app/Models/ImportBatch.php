<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['headers' => 'array', 'mapping' => 'array', 'options' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function rows()
    {
        return $this->hasMany(ImportRow::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
