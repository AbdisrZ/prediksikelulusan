// Visualisasi cara kerja 3 algoritma. Data diambil dari window.DATASET
// yang dikirim dari blade. Gambar update otomatis tiap form diubah.

(function () {
    'use strict';

    const DATA = window.DATASET || [];
    if (!DATA.length) return;

    const C_YA = '#198754';
    const C_TIDAK = '#dc3545';
    const C_QUERY = '#0d6efd';

    // threshold naive bayes (sama kayak di service php)
    const TH = { ipk: 3.0, kehadiran: 80, sks_lulus: 110 };

    const form = document.querySelector('form[action$="/predict"]');

    function getInput() {
        return {
            ipk: parseFloat(form.ipk.value) || 0,
            kehadiran: parseFloat(form.kehadiran.value) || 0,
            sks_lulus: parseFloat(form.sks_lulus.value) || 0,
            status_kerja: form.status_kerja.value || 'Tidak',
        };
    }

    // --- naive bayes ---
    function kategori(input) {
        return {
            ipk: input.ipk >= TH.ipk ? 'tinggi' : 'rendah',
            kehadiran: input.kehadiran >= TH.kehadiran ? 'tinggi' : 'rendah',
            sks_lulus: input.sks_lulus >= TH.sks_lulus ? 'tinggi' : 'rendah',
            status_kerja: input.status_kerja,
        };
    }

    function hitungNaiveBayes(input) {
        const kelasList = ['Ya', 'Tidak'];
        const total = DATA.length;
        const kat = kategori(input);
        const hasil = {};

        kelasList.forEach((kelas) => {
            const subset = DATA.filter((d) => d.tepat_waktu === kelas);
            const n = subset.length;
            const prior = n / total;

            const faktor = [{ nama: 'Prior P(' + kelas + ')', nilai: prior }];
            let skor = prior;

            ['ipk', 'kehadiran', 'sks_lulus', 'status_kerja'].forEach((f) => {
                const target = kat[f];
                const cocok = subset.filter((d) => {
                    if (f === 'status_kerja') return d.status_kerja === target;
                    return (d[f] >= TH[f] ? 'tinggi' : 'rendah') === target;
                }).length;
                const p = (cocok + 1) / (n + 2); // laplace
                faktor.push({ nama: f + ' = ' + target, nilai: p });
                skor *= p;
            });

            hasil[kelas] = { skor, faktor, n };
        });

        return { kat, hasil };
    }

    function renderNaiveBayes() {
        const el = document.getElementById('viz-nb');
        if (!el) return;
        const input = getInput();
        const { kat, hasil } = hitungNaiveBayes(input);

        const skorYa = hasil.Ya.skor;
        const skorTidak = hasil.Tidak.skor;
        const totalSkor = skorYa + skorTidak || 1;
        const persenYa = (skorYa / totalSkor) * 100;
        const menang = skorYa >= skorTidak ? 'Ya' : 'Tidak';

        const chip = (label, val) =>
            `<span class="badge ${val === 'tinggi' || val === 'Ya' ? 'bg-success' : 'bg-secondary'} me-1">${label}: ${val}</span>`;

        const faktorRows = (kelas) =>
            hasil[kelas].faktor
                .map(
                    (f) =>
                        `<tr><td>${f.nama}</td><td class="text-end"><code>${f.nilai.toFixed(4)}</code></td></tr>`
                )
                .join('');

        el.innerHTML = `
            <p class="text-muted small mb-2">
                Input dikategorikan dulu (threshold: IPK≥3, Kehadiran≥80, SKS≥110),
                lalu dihitung <em>prior × likelihood</em> tiap kelas dengan Laplace Smoothing.
            </p>
            <div class="mb-3">
                ${chip('IPK', kat.ipk)} ${chip('Kehadiran', kat.kehadiran)}
                ${chip('SKS', kat.sks_lulus)} ${chip('Status Kerja', kat.status_kerja)}
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border rounded p-2 h-100">
                        <strong class="text-success">Kelas "Ya" (${hasil.Ya.n} data)</strong>
                        <table class="table table-sm mb-0 mt-2"><tbody>${faktorRows('Ya')}</tbody>
                        <tfoot><tr class="table-light"><td><strong>Skor akhir</strong></td>
                        <td class="text-end"><strong>${skorYa.toExponential(3)}</strong></td></tr></tfoot></table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-2 h-100">
                        <strong class="text-danger">Kelas "Tidak" (${hasil.Tidak.n} data)</strong>
                        <table class="table table-sm mb-0 mt-2"><tbody>${faktorRows('Tidak')}</tbody>
                        <tfoot><tr class="table-light"><td><strong>Skor akhir</strong></td>
                        <td class="text-end"><strong>${skorTidak.toExponential(3)}</strong></td></tr></tfoot></table>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <div class="d-flex justify-content-between small">
                    <span class="text-success">Ya ${persenYa.toFixed(1)}%</span>
                    <span class="text-danger">Tidak ${(100 - persenYa).toFixed(1)}%</span>
                </div>
                <div class="progress" style="height:24px;">
                    <div class="progress-bar bg-success" style="width:${persenYa}%"></div>
                    <div class="progress-bar bg-danger" style="width:${100 - persenYa}%"></div>
                </div>
                <p class="mt-2 mb-0">Skor lebih besar → prediksi:
                    <span class="badge ${menang === 'Ya' ? 'bg-success' : 'bg-danger'}">${menang === 'Ya' ? 'Lulus Tepat Waktu' : 'Tidak Tepat Waktu'}</span>
                </p>
            </div>
        `;
    }

    // --- knn: scatter plot ipk vs kehadiran ---
    const KNN_K = 5;

    function renderKNN() {
        const canvas = document.getElementById('viz-knn-canvas');
        const info = document.getElementById('viz-knn-info');
        if (!canvas) return;

        const input = getInput();
        const ctx = canvas.getContext('2d');
        const W = canvas.width, H = canvas.height;
        const pad = 45;

        // rentang sumbu
        const xMin = 2.0, xMax = 4.0;      // ipk
        const yMin = 60, yMax = 100;       // kehadiran
        const sx = (v) => pad + ((v - xMin) / (xMax - xMin)) * (W - 2 * pad);
        const sy = (v) => H - pad - ((v - yMin) / (yMax - yMin)) * (H - 2 * pad);

        ctx.clearRect(0, 0, W, H);

        // grid + label sumbu
        ctx.strokeStyle = '#e9ecef';
        ctx.fillStyle = '#6c757d';
        ctx.font = '11px sans-serif';
        ctx.lineWidth = 1;
        for (let v = xMin; v <= xMax + 0.001; v += 0.5) {
            ctx.beginPath(); ctx.moveTo(sx(v), pad); ctx.lineTo(sx(v), H - pad); ctx.stroke();
            ctx.fillText(v.toFixed(1), sx(v) - 8, H - pad + 16);
        }
        for (let v = yMin; v <= yMax; v += 10) {
            ctx.beginPath(); ctx.moveTo(pad, sy(v)); ctx.lineTo(W - pad, sy(v)); ctx.stroke();
            ctx.fillText(v, pad - 28, sy(v) + 4);
        }
        ctx.fillStyle = '#212529';
        ctx.fillText('IPK →', W - pad - 30, H - pad + 32);
        ctx.save(); ctx.translate(14, pad + 40); ctx.rotate(-Math.PI / 2);
        ctx.fillText('Kehadiran % →', 0, 0); ctx.restore();

        // cari k tetangga terdekat (jarak dinormalisasi 2d)
        const nx = (v) => (v - xMin) / (xMax - xMin);
        const ny = (v) => (v - yMin) / (yMax - yMin);
        const qx = nx(input.ipk), qy = ny(input.kehadiran);
        const withDist = DATA.map((d, i) => ({
            i,
            d,
            dist: Math.hypot(nx(d.ipk) - qx, ny(d.kehadiran) - qy),
        })).sort((a, b) => a.dist - b.dist);
        const neighbors = withDist.slice(0, KNN_K);
        const neighborSet = new Set(neighbors.map((n) => n.i));

        // titik data biasa (transparan)
        DATA.forEach((d, i) => {
            if (neighborSet.has(i)) return;
            ctx.beginPath();
            ctx.fillStyle = (d.tepat_waktu === 'Ya' ? C_YA : C_TIDAK) + '40';
            ctx.arc(sx(d.ipk), sy(d.kehadiran), 3, 0, Math.PI * 2);
            ctx.fill();
        });

        // garis ke tetangga + highlight
        const qpx = sx(input.ipk), qpy = sy(input.kehadiran);
        let voteYa = 0, voteTidak = 0;
        neighbors.forEach((n) => {
            ctx.strokeStyle = '#adb5bd';
            ctx.setLineDash([4, 3]);
            ctx.beginPath(); ctx.moveTo(qpx, qpy);
            ctx.lineTo(sx(n.d.ipk), sy(n.d.kehadiran)); ctx.stroke();
            ctx.setLineDash([]);

            ctx.beginPath();
            ctx.fillStyle = n.d.tepat_waktu === 'Ya' ? C_YA : C_TIDAK;
            ctx.arc(sx(n.d.ipk), sy(n.d.kehadiran), 6, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.stroke();

            if (n.d.tepat_waktu === 'Ya') voteYa++; else voteTidak++;
        });

        // titik input user (bintang)
        drawStar(ctx, qpx, qpy, 9, C_QUERY);

        const hasil = voteYa >= voteTidak ? 'Ya' : 'Tidak';
        if (info) {
            info.innerHTML = `
                Dari <strong>${KNN_K}</strong> tetangga terdekat (jarak Euclidean):
                <span class="badge bg-success">Ya: ${voteYa}</span>
                <span class="badge bg-danger">Tidak: ${voteTidak}</span>
                &rarr; voting mayoritas:
                <span class="badge ${hasil === 'Ya' ? 'bg-success' : 'bg-danger'}">
                ${hasil === 'Ya' ? 'Lulus Tepat Waktu' : 'Tidak Tepat Waktu'}</span>
                <br><span class="text-muted small">★ = data input Anda. Visualisasi 2D memakai IPK &amp; Kehadiran
                (perhitungan asli di backend memakai 4 fitur).</span>`;
        }
    }

    function drawStar(ctx, cx, cy, r, color) {
        ctx.beginPath();
        for (let i = 0; i < 10; i++) {
            const rad = (Math.PI / 5) * i - Math.PI / 2;
            const rr = i % 2 === 0 ? r : r / 2;
            ctx.lineTo(cx + Math.cos(rad) * rr, cy + Math.sin(rad) * rr);
        }
        ctx.closePath();
        ctx.fillStyle = color; ctx.fill();
        ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.stroke();
    }

    // --- decision tree: pohon ringkas (maxDepth 3) ---
    const DT_MAX_DEPTH = 3;
    const DT_MIN = 20;
    const NUMERIC = ['ipk', 'kehadiran', 'sks_lulus'];
    let DT_TREE = null;

    function gini(rows) {
        if (!rows.length) return 0;
        const ya = rows.filter((r) => r.tepat_waktu === 'Ya').length;
        const py = ya / rows.length, pt = 1 - py;
        return 1 - (py * py + pt * pt);
    }

    function majority(rows) {
        const ya = rows.filter((r) => r.tepat_waktu === 'Ya').length;
        return ya >= rows.length - ya ? 'Ya' : 'Tidak';
    }

    function buildTree(rows, depth) {
        const label = majority(rows);
        const yaCount = rows.filter((r) => r.tepat_waktu === 'Ya').length;
        if (depth >= DT_MAX_DEPTH || rows.length < DT_MIN || gini(rows) === 0) {
            return { leaf: true, label, n: rows.length, ya: yaCount };
        }

        let best = null, bestGini = gini(rows);

        NUMERIC.forEach((f) => {
            const vals = Array.from(new Set(rows.map((r) => r[f]))).sort((a, b) => a - b);
            for (let i = 0; i < vals.length - 1; i++) {
                const thr = (vals[i] + vals[i + 1]) / 2;
                const L = rows.filter((r) => r[f] <= thr);
                const R = rows.filter((r) => r[f] > thr);
                if (!L.length || !R.length) continue;
                const g = (L.length * gini(L) + R.length * gini(R)) / rows.length;
                if (g < bestGini) { bestGini = g; best = { f, type: 'numeric', thr, L, R }; }
            }
        });
        // status_kerja
        const L = rows.filter((r) => r.status_kerja === 'Ya');
        const R = rows.filter((r) => r.status_kerja !== 'Ya');
        if (L.length && R.length) {
            const g = (L.length * gini(L) + R.length * gini(R)) / rows.length;
            if (g < bestGini) { bestGini = g; best = { f: 'status_kerja', type: 'cat', L, R }; }
        }

        if (!best) return { leaf: true, label, n: rows.length, ya: yaCount };

        return {
            leaf: false,
            feature: best.f,
            type: best.type,
            thr: best.thr,
            n: rows.length,
            ya: yaCount,
            left: buildTree(best.L, depth + 1),
            right: buildTree(best.R, depth + 1),
        };
    }

    function nodeLabel(node) {
        if (node.leaf) return null;
        if (node.type === 'numeric') {
            const f = node.feature === 'sks_lulus' ? 'SKS' : node.feature.charAt(0).toUpperCase() + node.feature.slice(1);
            return `${f} ≤ ${node.thr.toFixed(node.feature === 'ipk' ? 2 : 0)} ?`;
        }
        return 'Status Kerja = Ya ?';
    }

    function goesLeft(node, input) {
        if (node.type === 'numeric') return input[node.feature] <= node.thr;
        return input.status_kerja === 'Ya';
    }

    function renderTreeHTML(node, input, onPath) {
        const pct = node.n ? Math.round((node.ya / node.n) * 100) : 0;
        if (node.leaf) {
            const cls = node.label === 'Ya' ? 'border-success text-success' : 'border-danger text-danger';
            return `<li><div class="dt-node ${cls} ${onPath ? 'dt-active' : ''}">
                <strong>${node.label === 'Ya' ? 'Lulus' : 'Tidak'}</strong>
                <div class="small text-muted">${node.n} data · ${pct}% Ya</div></div></li>`;
        }
        const left = goesLeft(node, input);
        return `<li>
            <div class="dt-node ${onPath ? 'dt-active' : ''}">${nodeLabel(node)}
                <div class="small text-muted">${node.n} data · ${pct}% Ya</div></div>
            <ul>
                <li class="dt-branch"><span class="dt-edge ${onPath && left ? 'dt-edge-on' : ''}">Ya / ≤</span>
                    <ul>${renderTreeHTML(node.left, input, onPath && left)}</ul></li>
                <li class="dt-branch"><span class="dt-edge ${onPath && !left ? 'dt-edge-on' : ''}">Tidak / ></span>
                    <ul>${renderTreeHTML(node.right, input, onPath && !left)}</ul></li>
            </ul></li>`;
    }

    function predictTree(node, input) {
        while (!node.leaf) node = goesLeft(node, input) ? node.left : node.right;
        return node.label;
    }

    function renderDecisionTree() {
        const el = document.getElementById('viz-dt');
        if (!el) return;
        if (!DT_TREE) DT_TREE = buildTree(DATA, 0);
        const input = getInput();
        const hasil = predictTree(DT_TREE, input);

        el.innerHTML = `
            <p class="text-muted small mb-2">
                Pohon dibangun dengan Gini Impurity (di sini dibatasi kedalaman ${DT_MAX_DEPTH} agar mudah dibaca).
                Jalur biru = keputusan untuk input Anda. Hasil:
                <span class="badge ${hasil === 'Ya' ? 'bg-success' : 'bg-danger'}">
                ${hasil === 'Ya' ? 'Lulus Tepat Waktu' : 'Tidak Tepat Waktu'}</span>
            </p>
            <div class="dt-tree-scroll"><div class="dt-tree"><ul>${renderTreeHTML(DT_TREE, input, true)}</ul></div></div>`;
    }

    // --- pindah tab + render ulang ---
    const panels = {
        naive_bayes: document.getElementById('panel-nb'),
        knn: document.getElementById('panel-knn'),
        decision_tree: document.getElementById('panel-dt'),
    };
    const tabs = document.querySelectorAll('[data-viz-tab]');

    function showTab(key) {
        Object.entries(panels).forEach(([k, p]) => { if (p) p.style.display = k === key ? 'block' : 'none'; });
        tabs.forEach((t) => t.classList.toggle('active', t.dataset.vizTab === key));
        renderActive(key);
    }

    function renderActive(key) {
        if (key === 'naive_bayes') renderNaiveBayes();
        else if (key === 'knn') renderKNN();
        else if (key === 'decision_tree') renderDecisionTree();
    }

    function currentTab() {
        const active = document.querySelector('[data-viz-tab].active');
        return active ? active.dataset.vizTab : 'knn';
    }

    tabs.forEach((t) => t.addEventListener('click', () => showTab(t.dataset.vizTab)));

    // re-render tiap form berubah
    ['ipk', 'kehadiran', 'sks_lulus', 'status_kerja'].forEach((name) => {
        if (form[name]) form[name].addEventListener('input', () => renderActive(currentTab()));
        if (form[name]) form[name].addEventListener('change', () => renderActive(currentTab()));
    });

    // ganti algoritma di dropdown -> tab visualisasi ikut pindah
    if (form.algoritma) {
        form.algoritma.addEventListener('change', () => showTab(form.algoritma.value));
    }

    // render awal ikut algoritma yang terpilih
    showTab(form.algoritma ? form.algoritma.value : 'knn');
})();
