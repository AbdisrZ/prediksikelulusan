<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Prediksi Kelulusan Mahasiswa</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background-color: #f4f6f9; }
        .tabel-scroll { max-height: 600px; overflow-y: auto; }
        .tabel-scroll thead th {
            position: sticky;
            top: 0;
            background-color: #0d6efd;
            color: #fff;
            z-index: 1;
        }
        .badge-ya { background-color: #198754; }
        .badge-tidak { background-color: #dc3545; }

        /* ---- Visualisasi ---- */
        .viz-tab {
            cursor: pointer;
            border: 1px solid #dee2e6;
            background: #fff;
            padding: .5rem 1rem;
            border-radius: 8px 8px 0 0;
            margin-right: 4px;
            color: #495057;
            font-weight: 600;
        }
        .viz-tab.active { background: #0d6efd; color: #fff; border-color: #0d6efd; }

        /* Decision tree (CSS tree) */
        .dt-tree-scroll { overflow-x: auto; padding-bottom: .5rem; }
        .dt-tree ul { position: relative; padding-top: 22px; display: flex; justify-content: center; margin: 0; list-style: none; }
        .dt-tree li { position: relative; padding: 22px 8px 0 8px; text-align: center; }
        .dt-tree li::before, .dt-tree li::after {
            content: ''; position: absolute; top: 0; right: 50%;
            border-top: 1px solid #ccc; width: 50%; height: 22px;
        }
        .dt-tree li::after { right: auto; left: 50%; border-left: 1px solid #ccc; }
        .dt-tree li:only-child::before, .dt-tree li:only-child::after { display: none; }
        .dt-tree li:first-child::before, .dt-tree li:last-child::after { border: 0; }
        .dt-tree li:last-child::before { border-right: 1px solid #ccc; border-radius: 0 6px 0 0; }
        .dt-tree li:first-child::after { border-radius: 6px 0 0 0; }
        .dt-tree ul ul::before {
            content: ''; position: absolute; top: 0; left: 50%;
            border-left: 1px solid #ccc; height: 22px;
        }
        .dt-node {
            display: inline-block; border: 1px solid #ced4da; background: #fff;
            padding: 6px 10px; border-radius: 8px; font-size: 13px; white-space: nowrap;
        }
        .dt-node.dt-active { box-shadow: 0 0 0 3px rgba(13,110,253,.35); border-color: #0d6efd; }
        .dt-branch { padding-top: 6px; }
        .dt-edge { font-size: 11px; color: #adb5bd; }
        .dt-edge-on { color: #0d6efd; font-weight: 700; }
    </style>
</head>

<body>

<div class="container-fluid py-4">

    <div class="text-center mb-4">
        <h2 class="fw-bold text-primary mb-1">Sistem Prediksi Kelulusan Mahasiswa</h2>
        <p class="text-muted mb-0">Data Mining &mdash; Klasifikasi (Naive Bayes / KNN / Decision Tree)</p>
    </div>

    <div class="row g-4">

        {{-- tabel data mahasiswa --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Data Mahasiswa</span>
                    <span class="badge bg-light text-dark">{{ $totalTraining }} data historis</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive tabel-scroll">
                        <table class="table table-striped table-hover table-sm mb-0 align-middle text-center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>IPK</th>
                                    <th>Kehadiran (%)</th>
                                    <th>SKS Lulus</th>
                                    <th>Status Kerja</th>
                                    <th>Tepat Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mahasiswa as $m)
                                    <tr>
                                        <td>{{ $m->id }}</td>
                                        <td>{{ number_format($m->ipk, 2) }}</td>
                                        <td>{{ $m->kehadiran }}</td>
                                        <td>{{ $m->sks_lulus }}</td>
                                        <td>{{ $m->status_kerja }}</td>
                                        <td>
                                            <span class="badge {{ $m->tepat_waktu === 'Ya' ? 'badge-ya' : 'badge-tidak' }}">
                                                {{ $m->tepat_waktu }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- form prediksi --}}
        <div class="col-lg-6">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="mb-0">Prediksi Kelulusan Mahasiswa</h4>
                </div>

                <div class="card-body">

                    {{-- hasil prediksi --}}
                    @if (session('prediction'))
                        @php $lulus = session('prediction') === 'Ya'; @endphp
                        <div class="alert {{ $lulus ? 'alert-success' : 'alert-danger' }} border-0 shadow-sm">
                            <div class="row text-center">
                                <div class="col-7 border-end">
                                    <small class="text-uppercase fw-bold">Hasil Prediksi</small>
                                    <h4 class="mb-0 mt-1">
                                        {{ $lulus ? '✅ Lulus Tepat Waktu' : '❌ Tidak Tepat Waktu' }}
                                    </h4>
                                </div>
                                <div class="col-5">
                                    <small class="text-uppercase fw-bold">Akurasi</small>
                                    <h4 class="mb-0 mt-1">{{ session('accuracy') }}%</h4>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="text-center small">
                                Algoritma: <strong>{{ session('algoritma_label') }}</strong>
                            </div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-warning">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ url('/predict') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Algoritma</label>
                            <select name="algoritma" class="form-select form-select-lg" required>
                                @foreach ($algoritma as $key => $label)
                                    <option value="{{ $key }}" {{ old('algoritma') === $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">IPK</label>
                                <input type="number" step="0.01" min="0" max="4" name="ipk"
                                       value="{{ old('ipk') }}" class="form-control"
                                       placeholder="Contoh: 3.50" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kehadiran (%)</label>
                                <input type="number" min="0" max="100" name="kehadiran"
                                       value="{{ old('kehadiran') }}" class="form-control"
                                       placeholder="Contoh: 90" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SKS Lulus</label>
                                <input type="number" min="0" name="sks_lulus"
                                       value="{{ old('sks_lulus') }}" class="form-control"
                                       placeholder="Contoh: 120" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Status Kerja</label>
                                <select name="status_kerja" class="form-select" required>
                                    <option value="">-- Pilih Status --</option>
                                    <option value="Ya" {{ old('status_kerja') === 'Ya' ? 'selected' : '' }}>Ya</option>
                                    <option value="Tidak" {{ old('status_kerja') === 'Tidak' ? 'selected' : '' }}>Tidak</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-success btn-lg">
                                Prediksi Kelulusan
                            </button>
                        </div>
                    </form>

                </div>

                <div class="card-footer text-center text-muted small">
                    Data displit 80% training &amp; 20% testing &mdash; akurasi dihitung dari data testing.
                </div>
            </div>
        </div>

    </div>

    {{-- visualisasi proses algoritma --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="mb-1">Visualisasi Proses Algoritma</h5>
                    <p class="text-muted small mb-3">
                        Gambar di bawah memperlihatkan <em>cara kerja</em> tiap algoritma dan ikut berubah
                        secara langsung saat Anda mengetik di form di atas (tanpa perlu submit).
                    </p>
                    <div>
                        <span class="viz-tab active" data-viz-tab="naive_bayes">Naive Bayes</span>
                        <span class="viz-tab" data-viz-tab="knn">KNN</span>
                        <span class="viz-tab" data-viz-tab="decision_tree">Decision Tree</span>
                    </div>
                </div>
                <div class="card-body border-top">

                    <div id="panel-nb">
                        <div id="viz-nb"></div>
                    </div>

                    <div id="panel-knn" style="display:none;">
                        <div class="text-center">
                            <canvas id="viz-knn-canvas" width="620" height="420"
                                    style="max-width:100%;border:1px solid #eee;border-radius:8px;"></canvas>
                        </div>
                        <p id="viz-knn-info" class="mt-2 mb-0"></p>
                    </div>

                    <div id="panel-dt" style="display:none;">
                        <div id="viz-dt"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

{{-- data buat visualisasi --}}
<script>
    window.DATASET = {!! $dataset->toJson() !!};
</script>
<script src="{{ asset('js/visualisasi.js') }}"></script>

{{-- Popup hasil prediksi --}}
@if (session('prediction'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: '{{ session("prediction") === "Ya" ? "success" : "warning" }}',
        title: 'Hasil Prediksi',
        html: `
            <div style="text-align:center;font-size:15px;">
                <p class="mb-1"><strong>Algoritma:</strong> {{ session('algoritma_label') }}</p>
                <h4 style="color: {{ session('prediction') === 'Ya' ? '#198754' : '#dc3545' }}">
                    {{ session('prediction') === 'Ya' ? '✅ Lulus Tepat Waktu' : '❌ Tidak Lulus Tepat Waktu' }}
                </h4>
                <hr>
                <p class="mb-0">Tingkat Akurasi Algoritma:
                    <strong>{{ session('accuracy') }}%</strong>
                </p>
            </div>
        `,
        width: 600,
        confirmButtonText: 'Tutup',
        confirmButtonColor: '#0d6efd'
    });
});
</script>
@endif

</body>

</html>
