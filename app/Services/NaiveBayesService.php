<?php

namespace App\Services;

// Naive Bayes. Tiap fitur numerik dikategorikan dulu (tinggi/rendah)
// pakai threshold, lalu hitung probabilitasnya + Laplace smoothing.
class NaiveBayesService implements Classifier
{
    private array $classCount = ['Ya' => 0, 'Tidak' => 0];
    private int $total = 0;

    // $featureCount[fitur][kategori][kelas] = jumlah
    private array $featureCount = [];

    private const THRESHOLDS = [
        'ipk' => 3.0,
        'kehadiran' => 80,
        'sks_lulus' => 110,
    ];

    public function train(array $training): void
    {
        $this->classCount = ['Ya' => 0, 'Tidak' => 0];
        $this->featureCount = [];
        $this->total = 0;

        foreach ($training as $row) {
            $kelas = $row['tepat_waktu'];
            if (! isset($this->classCount[$kelas])) {
                continue;
            }

            $this->classCount[$kelas]++;
            $this->total++;

            foreach ($this->extractCategories($row) as $fitur => $kategori) {
                $this->featureCount[$fitur][$kategori][$kelas] =
                    ($this->featureCount[$fitur][$kategori][$kelas] ?? 0) + 1;
            }
        }
    }

    public function predict(array $sample): string
    {
        $skor = [];

        foreach (['Ya', 'Tidak'] as $kelas) {
            $classCount = $this->classCount[$kelas];

            // P(kelas)
            $prob = $this->total > 0 ? $classCount / $this->total : 0;

            // kali P(fitur|kelas) tiap fitur, pakai Laplace smoothing (+1/+2)
            foreach ($this->extractCategories($sample) as $fitur => $kategori) {
                $count = $this->featureCount[$fitur][$kategori][$kelas] ?? 0;
                $prob *= ($count + 1) / ($classCount + 2);
            }

            $skor[$kelas] = $prob;
        }

        return $skor['Ya'] >= $skor['Tidak'] ? 'Ya' : 'Tidak';
    }

    // ubah satu baris jadi kategori per fitur
    private function extractCategories(array $row): array
    {
        return [
            'ipk' => $row['ipk'] >= self::THRESHOLDS['ipk'] ? 'tinggi' : 'rendah',
            'kehadiran' => $row['kehadiran'] >= self::THRESHOLDS['kehadiran'] ? 'tinggi' : 'rendah',
            'sks_lulus' => $row['sks_lulus'] >= self::THRESHOLDS['sks_lulus'] ? 'tinggi' : 'rendah',
            'status_kerja' => $row['status_kerja'],
        ];
    }
}
