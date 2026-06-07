<?php

namespace App\Services;

// Decision Tree (CART). Split biner di tiap node, kualitas split
// diukur pakai Gini. Fitur numerik di-split pakai threshold,
// status_kerja di-split berdasarkan nilainya.
class DecisionTreeService implements Classifier
{
    private int $maxDepth;
    private int $minSamplesSplit;

    private ?array $tree = null;

    private const NUMERIC = ['ipk', 'kehadiran', 'sks_lulus'];

    public function __construct(int $maxDepth = 8, int $minSamplesSplit = 5)
    {
        $this->maxDepth = $maxDepth;
        $this->minSamplesSplit = $minSamplesSplit;
    }

    public function train(array $training): void
    {
        $this->tree = $this->build($training, 0);
    }

    public function predict(array $sample): string
    {
        $node = $this->tree;

        if ($node === null) {
            return 'Ya';
        }

        // telusuri pohon sampai ketemu daun
        while (! ($node['leaf'] ?? false)) {
            if ($node['type'] === 'numeric') {
                $node = (float) $sample[$node['feature']] <= $node['threshold']
                    ? $node['left']
                    : $node['right'];
            } else {
                $node = $sample[$node['feature']] === $node['value']
                    ? $node['left']
                    : $node['right'];
            }
        }

        return $node['label'];
    }

    // bangun pohon secara rekursif
    private function build(array $rows, int $depth): array
    {
        $labels = array_column($rows, 'tepat_waktu');

        // berhenti kalau data habis / kedalaman max / data sedikit / sudah murni
        if (
            empty($rows)
            || $depth >= $this->maxDepth
            || count($rows) < $this->minSamplesSplit
            || $this->isPure($labels)
        ) {
            return $this->makeLeaf($labels);
        }

        $best = $this->bestSplit($rows);

        if ($best === null) {
            return $this->makeLeaf($labels);
        }

        $node = [
            'leaf' => false,
            'feature' => $best['feature'],
            'type' => $best['type'],
            'left' => $this->build($best['left'], $depth + 1),
            'right' => $this->build($best['right'], $depth + 1),
        ];

        if ($best['type'] === 'numeric') {
            $node['threshold'] = $best['threshold'];
        } else {
            $node['value'] = $best['value'];
        }

        return $node;
    }

    // cari split terbaik (gini paling kecil) dari semua fitur
    private function bestSplit(array $rows): ?array
    {
        $parentGini = $this->gini(array_column($rows, 'tepat_waktu'));
        $best = null;
        $bestGini = $parentGini;

        foreach (self::NUMERIC as $fitur) {
            foreach ($this->candidateThresholds($rows, $fitur) as $threshold) {
                $left = $right = [];
                foreach ($rows as $row) {
                    if ((float) $row[$fitur] <= $threshold) {
                        $left[] = $row;
                    } else {
                        $right[] = $row;
                    }
                }

                $gini = $this->weightedGini($left, $right);
                if ($gini < $bestGini) {
                    $bestGini = $gini;
                    $best = [
                        'feature' => $fitur,
                        'type' => 'numeric',
                        'threshold' => $threshold,
                        'left' => $left,
                        'right' => $right,
                    ];
                }
            }
        }

        // split status_kerja == 'Ya'
        $left = $right = [];
        foreach ($rows as $row) {
            if ($row['status_kerja'] === 'Ya') {
                $left[] = $row;
            } else {
                $right[] = $row;
            }
        }
        if ($left && $right) {
            $gini = $this->weightedGini($left, $right);
            if ($gini < $bestGini) {
                $best = [
                    'feature' => 'status_kerja',
                    'type' => 'cat',
                    'value' => 'Ya',
                    'left' => $left,
                    'right' => $right,
                ];
            }
        }

        return $best;
    }

    // kandidat threshold = titik tengah antar nilai unik
    private function candidateThresholds(array $rows, string $fitur): array
    {
        $nilai = array_unique(array_map(fn ($r) => (float) $r[$fitur], $rows));
        sort($nilai);

        $thresholds = [];
        for ($i = 0; $i < count($nilai) - 1; $i++) {
            $thresholds[] = ($nilai[$i] + $nilai[$i + 1]) / 2;
        }

        return $thresholds;
    }

    private function weightedGini(array $left, array $right): float
    {
        $total = count($left) + count($right);
        if ($total === 0) {
            return 0.0;
        }

        $giniLeft = $this->gini(array_column($left, 'tepat_waktu'));
        $giniRight = $this->gini(array_column($right, 'tepat_waktu'));

        return (count($left) / $total) * $giniLeft
            + (count($right) / $total) * $giniRight;
    }

    private function gini(array $labels): float
    {
        $total = count($labels);
        if ($total === 0) {
            return 0.0;
        }

        $counts = array_count_values($labels);
        $sum = 0.0;
        foreach ($counts as $jumlah) {
            $sum += ($jumlah / $total) ** 2;
        }

        return 1 - $sum;
    }

    private function isPure(array $labels): bool
    {
        return count(array_unique($labels)) <= 1;
    }

    // daun = label mayoritas
    private function makeLeaf(array $labels): array
    {
        if (empty($labels)) {
            return ['leaf' => true, 'label' => 'Ya'];
        }

        $counts = array_count_values($labels);
        arsort($counts);

        return ['leaf' => true, 'label' => array_key_first($counts)];
    }
}
