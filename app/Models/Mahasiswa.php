<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mahasiswa extends Model
{
    protected $table = 'mahasiswa';

    // tabel mahasiswa gak ada kolom created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'ipk',
        'kehadiran',
        'sks_lulus',
        'status_kerja',
        'tepat_waktu',
    ];
}
