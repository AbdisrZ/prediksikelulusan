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

</div>

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
