<?php

namespace App\Http\Controllers;

use App\Models\Mahasiswa;
use App\Services\Classifier;
use App\Services\DecisionTreeService;
use App\Services\KNNService;
use App\Services\NaiveBayesService;
use Illuminate\Http\Request;

class ClassificationController extends Controller
{
    // pilihan algoritma buat dropdown
    private const ALGORITMA = [
        'naive_bayes' => 'Naive Bayes',
        'knn' => 'K-Nearest Neighbors (KNN)',
        'decision_tree' => 'Decision Tree',
    ];

    // 80% training, 20% testing
    private const TRAIN_RATIO = 0.8;

    public function index()
    {
        $mahasiswa = Mahasiswa::orderBy('id')->get();

        // data buat visualisasi di sisi JS
        $dataset = $mahasiswa->map(fn ($m) => [
            'ipk' => (float) $m->ipk,
            'kehadiran' => (int) $m->kehadiran,
            'sks_lulus' => (int) $m->sks_lulus,
            'status_kerja' => $m->status_kerja,
            'tepat_waktu' => $m->tepat_waktu,
        ])->values();

        return view('klasifikasi', [
            'mahasiswa' => $mahasiswa,
            'totalTraining' => $mahasiswa->count(),
            'algoritma' => self::ALGORITMA,
            'dataset' => $dataset,
        ]);
    }

    public function predict(Request $request)
    {
        $validated = $request->validate([
            'algoritma' => 'required|in:naive_bayes,knn,decision_tree',
            'ipk' => 'required|numeric|min:0|max:4',
            'kehadiran' => 'required|numeric|min:0|max:100',
            'sks_lulus' => 'required|numeric|min:0',
            'status_kerja' => 'required|in:Ya,Tidak',
        ]);

        // ambil semua data
        $rows = Mahasiswa::orderBy('id')->get()->map(fn ($m) => [
            'ipk' => (float) $m->ipk,
            'kehadiran' => (int) $m->kehadiran,
            'sks_lulus' => (int) $m->sks_lulus,
            'status_kerja' => $m->status_kerja,
            'tepat_waktu' => $m->tepat_waktu,
        ])->all();

        if (empty($rows)) {
            return redirect('/')->with('error', 'Data training tidak ditemukan.');
        }

        // bagi 80/20
        [$training, $testing] = $this->splitData($rows, self::TRAIN_RATIO);

        // pilih algoritma & latih
        $model = $this->makeClassifier($validated['algoritma']);
        $model->train($training);

        // akurasi dari data testing
        $akurasi = $this->hitungAkurasi($model, $testing);

        // prediksi input user
        $input = [
            'ipk' => (float) $validated['ipk'],
            'kehadiran' => (int) $validated['kehadiran'],
            'sks_lulus' => (int) $validated['sks_lulus'],
            'status_kerja' => $validated['status_kerja'],
        ];
        $hasil = $model->predict($input);

        return redirect('/')
            ->with('prediction', $hasil)
            ->with('accuracy', $akurasi)
            ->with('algoritma_key', $validated['algoritma'])
            ->with('algoritma_label', self::ALGORITMA[$validated['algoritma']])
            ->withInput();
    }

    private function makeClassifier(string $key): Classifier
    {
        return match ($key) {
            'knn' => new KNNService(5),
            'decision_tree' => new DecisionTreeService(),
            default => new NaiveBayesService(),
        };
    }

    // bagi data jadi training & testing.
    // pakai seed tetap biar akurasinya gak berubah-ubah tiap refresh.
    private function splitData(array $rows, float $ratio): array
    {
        mt_srand(42);
        shuffle($rows);
        mt_srand();

        $batas = (int) floor(count($rows) * $ratio);

        return [
            array_slice($rows, 0, $batas),
            array_slice($rows, $batas),
        ];
    }

    // akurasi (%) model terhadap data testing
    private function hitungAkurasi(Classifier $model, array $testing): float
    {
        if (empty($testing)) {
            return 0.0;
        }

        $benar = 0;
        foreach ($testing as $row) {
            if ($model->predict($row) === $row['tepat_waktu']) {
                $benar++;
            }
        }

        return round($benar / count($testing) * 100, 2);
    }
}
