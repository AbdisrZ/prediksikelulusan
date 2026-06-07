<?php

namespace App\Services;

// kontrak biar 3 algoritma bisa dipanggil dengan cara yang sama di controller
interface Classifier
{
    // latih model pakai data training
    public function train(array $training): void;

    // prediksi 1 data, hasilnya 'Ya' atau 'Tidak'
    public function predict(array $sample): string;
}
