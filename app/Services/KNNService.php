<?php

namespace App\Services;

// KNN. Fitur numerik dinormalisasi (min-max) biar skalanya setara,
// status_kerja diubah jadi angka (Ya=1, Tidak=0). Pas prediksi cari
// k tetangga terdekat pakai jarak euclidean lalu voting.
class KNNService implements Classifier
{
    private int $k;

    private array $training = [];

    private array $min = [];
    private array $max = [];

    private const NUMERIC = ['ipk', 'kehadiran', 'sks_lulus'];

    public function __construct(int $k = 5)
    {
        $this->k = $k;
    }

    public function train(array $training): void
    {
        $this->computeMinMax($training);

        $this->training = [];
        foreach ($training as $row) {
            $this->training[] = [
                'features' => $this->toVector($row),
                'label' => $row['tepat_waktu'],
            ];
        }
    }

    public function predict(array $sample): string
    {
        $target = $this->toVector($sample);

        // hitung jarak ke semua data training
        $distances = [];
        foreach ($this->training as $data) {
            $distances[] = [
                'jarak' => $this->euclidean($target, $data['features']),
                'label' => $data['label'],
            ];
        }

        // urutkan, ambil k terdekat
        usort($distances, fn ($a, $b) => $a['jarak'] <=> $b['jarak']);
        $tetangga = array_slice($distances, 0, $this->k);

        // voting
        $vote = ['Ya' => 0, 'Tidak' => 0];
        foreach ($tetangga as $t) {
            $vote[$t['label']]++;
        }

        return $vote['Ya'] >= $vote['Tidak'] ? 'Ya' : 'Tidak';
    }

    private function computeMinMax(array $training): void
    {
        $this->min = [];
        $this->max = [];

        foreach (self::NUMERIC as $fitur) {
            $nilai = array_map(fn ($r) => (float) $r[$fitur], $training);
            $this->min[$fitur] = $nilai ? min($nilai) : 0;
            $this->max[$fitur] = $nilai ? max($nilai) : 0;
        }
    }

    // ubah 1 baris jadi vektor angka yg sudah dinormalisasi
    private function toVector(array $row): array
    {
        $vector = [];

        foreach (self::NUMERIC as $fitur) {
            $range = $this->max[$fitur] - $this->min[$fitur];
            $vector[] = $range > 0
                ? ((float) $row[$fitur] - $this->min[$fitur]) / $range
                : 0.0;
        }

        $vector[] = $row['status_kerja'] === 'Ya' ? 1.0 : 0.0;

        return $vector;
    }

    private function euclidean(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $val) {
            $sum += ($val - $b[$i]) ** 2;
        }

        return sqrt($sum);
    }
}
