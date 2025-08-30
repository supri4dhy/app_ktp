<?php
// Note: This code demonstrates using Tesseract OCR installed on the server.
// You must have Tesseract properly configured in your system's PATH.

$extracted_data = [];
$message = '';

// --- Logika Unduh CSV ---
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    // Data tiruan yang akan diunduh
    // Dalam implementasi nyata, Anda akan mengambil data ini dari database atau sesi
    $mock_download_data = [
        'NIK' => '3273xxxxxxxxxxxx',
        'Nama' => 'Joko Santoso',
        'Tempat_Lahir' => 'Bandung',
        'Tanggal_Lahir' => '10-01-1985',
        'Alamat' => 'Jl. Merdeka No. 123',
        'Jenis_Kelamin' => 'Laki-laki',
        'Agama' => 'Islam',
        'Pekerjaan' => 'Wiraswasta',
    ];
    
    // Header untuk memaksa unduhan file
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="data_ktp.csv"');

    // Buka output PHP sebagai stream file
    $output = fopen('php://output', 'w');

    // Tulis header kolom
    fputcsv($output, array_keys($mock_download_data));

    // Tulis data baris
    fputcsv($output, $mock_download_data);

    // Tutup file stream
    fclose($output);
    exit();
}

// --- Logika Unggah dan Ekstraksi ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ktp_file'])) {
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            $message = 'Gagal membuat direktori unggahan.';
            goto end_script;
        }
    }
    
    $target_file = $target_dir . basename($_FILES["ktp_file"]["name"]);
    $uploadOk = 1;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    if($fileType != "jpg" && $fileType != "jpeg" && $fileType != "png" && $fileType != "pdf") {
        $message = 'Maaf, hanya file JPG, JPEG, PNG & PDF yang diizinkan.';
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["ktp_file"]["tmp_name"], $target_file)) {
            try {
                // Panggil fungsi untuk memproses file dengan Tesseract
                $ocr_response = processOcrWithTesseract($target_file);
                
                // Proses data yang diekstrak
                $extracted_data = extractDataFromOcrText($ocr_response);
                $message = 'Data berhasil diekstrak!';
            } catch (Exception $e) {
                $message = 'Terjadi kesalahan saat menjalankan OCR: ' . $e->getMessage();
            }

        } else {
            $message = 'Maaf, ada kesalahan saat mengunggah file Anda.';
        }
    }
}

/**
 * Fungsi untuk memproses file gambar dengan Tesseract OCR.
 * @param string $filePath Path ke file yang diunggah.
 * @return string Teks yang diekstrak oleh Tesseract.
 * @throws Exception Jika Tesseract gagal dieksekusi.
 */
function processOcrWithTesseract($filePath) {
    // Nama file output sementara (tanpa ekstensi)
    $outputFile = dirname($filePath) . '/' . uniqid();
    
    // Perintah Tesseract. "-l ind+eng" untuk bahasa Indonesia dan Inggris.
    // PDF akan diolah oleh Tesseract dengan bantuan ghostscript
    $command = "tesseract \"{$filePath}\" \"{$outputFile}\" -l ind+eng";

    // Jalankan perintah
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        throw new Exception("Tesseract failed with return code {$returnCode}");
    }
    
    // Baca teks dari file output .txt
    $ocrText = file_get_contents("{$outputFile}.txt");
    
    // Hapus file sementara
    unlink("{$outputFile}.txt");
    unlink($filePath); // Opsional: hapus juga file gambar asli
    
    return $ocrText;
}


/**
 * Fungsi untuk mengolah teks mentah dari OCR menjadi data terstruktur.
 * @param string $ocrText Teks mentah dari respons OCR.
 * @return array Data KTP yang diekstrak dalam bentuk array asosiatif.
 */
function extractDataFromOcrText($ocrText) {
    $lines = explode("\n", $ocrText);
    $data = [];
    $regex = [
        'NIK' => '/NIK\s*(\d{16})/',
        'Nama' => '/Nama\s*([A-Z\s]+)/',
        'Tempat_Lahir' => '/Tempat\/Tgl Lahir\s*([A-Z\s]+),\s*(\d{2}-\d{2}-\d{4})/',
        'Jenis_Kelamin' => '/Jenis Kelamin\s*(LAKI-LAKI|PEREMPUAN)/',
        'Alamat' => '/Alamat\s*(.+)/',
        'Agama' => '/Agama\s*([A-Z]+)/',
        'Pekerjaan' => '/Pekerjaan\s*(.+)/'
    ];

    foreach ($lines as $line) {
        foreach ($regex as $key => $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                if ($key === 'Tempat_Lahir') {
                    $data['Tempat_Lahir'] = trim($matches[1]);
                    $data['Tanggal_Lahir'] = trim($matches[2]);
                } else {
                    $data[$key] = trim($matches[1]);
                }
            }
        }
    }
    
    if (!isset($data['NIK'])) {
        $data['NIK'] = 'Tidak ditemukan';
    }

    return $data;
}

end_script:
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KTP Data Extractor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
        }
        .card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .btn-gradient {
            background: linear-gradient(45deg, #4f46e5, #a855f7);
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .btn-gradient:hover {
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15), 0 2px 5px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .dark .gradient-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }
        .dark .card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.5) 0%, rgba(15, 23, 42, 0.5) 100%);
        }
        .toggle-btn {
            transition: background-color 0.3s ease;
        }
        .toggle-circle {
            transition: transform 0.3s ease;
        }
        .alert-box {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="gradient-bg text-white dark:gradient-bg">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="card p-8 rounded-2xl shadow-lg w-full max-w-xl mx-auto border border-gray-700">
            <div class="flex justify-end mb-4">
                <button id="theme-toggle" class="p-2 rounded-full toggle-btn focus:outline-none">
                    <div class="w-10 h-6 rounded-full flex items-center p-1" style="background-color: #a855f7;">
                        <div id="toggle-circle" class="w-4 h-4 rounded-full bg-white shadow-md toggle-circle"></div>
                    </div>
                </button>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold text-center mb-6" style="color: #a855f7;">
                KTP Data Extractor
            </h1>
            <p class="text-center text-gray-300 mb-8">
                Unggah file JPG, PNG, atau PDF KTP Anda untuk mengekstrak data secara otomatis.
            </p>

            <?php if (!empty($message)): ?>
                <div id="alert-message" class="alert-box bg-purple-500 bg-opacity-20 text-white text-sm p-3 rounded-lg text-center mb-4 border border-purple-500">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form action="" method="post" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="ktp_file" class="block text-sm font-medium text-gray-300 mb-2">Pilih File KTP:</label>
                    <input type="file" name="ktp_file" id="ktp_file" accept=".jpg, .jpeg, .png, .pdf" required class="block w-full text-sm text-gray-300 border border-gray-600 rounded-lg cursor-pointer bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
                </div>
                <div class="text-center">
                    <button type="submit" class="w-full md:w-auto px-6 py-3 rounded-lg font-semibold text-white btn-gradient focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 focus:ring-indigo-500">
                        Ekstrak Data
                    </button>
                </div>
            </form>

            <?php if (!empty($extracted_data)): ?>
                <div class="mt-8 p-6 rounded-lg bg-gray-800 border border-gray-700 shadow-inner">
                    <h2 class="text-xl font-bold text-violet-400 mb-4">Data Hasil Ekstraksi</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <?php foreach ($extracted_data as $key => $value): ?>
                            <div class="flex flex-col">
                                <span class="font-bold text-gray-400"><?= htmlspecialchars(str_replace('_', ' ', $key)) ?>:</span>
                                <span class="text-white"><?= htmlspecialchars($value) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 flex flex-col md:flex-row justify-center space-y-4 md:space-y-0 md:space-x-4">
                        <button onclick="copyToClipboard()" class="w-full md:w-auto px-6 py-3 rounded-lg font-semibold text-white btn-gradient focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 focus:ring-indigo-500">
                            Salin Data
                        </button>
                        <a href="?download=csv" class="w-full md:w-auto px-6 py-3 rounded-lg font-semibold text-white btn-gradient text-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 focus:ring-indigo-500">
                            Unduh sebagai CSV
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const themeToggleBtn = document.getElementById('theme-toggle');
        const body = document.body;
        const toggleCircle = document.getElementById('toggle-circle');

        function updateTheme(isDark) {
            if (isDark) {
                body.classList.add('dark');
                toggleCircle.style.transform = 'translateX(100%)';
            } else {
                body.classList.remove('dark');
                toggleCircle.style.transform = 'translateX(0)';
            }
        }

        themeToggleBtn.addEventListener('click', () => {
            const isDark = body.classList.contains('dark');
            updateTheme(!isDark);
        });

        const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)");
        updateTheme(prefersDarkScheme.matches);

        function showMessageBox(message) {
            const existingBox = document.getElementById('custom-message-box');
            if (existingBox) {
                existingBox.remove();
            }

            const box = document.createElement('div');
            box.id = 'custom-message-box';
            box.textContent = message;
            box.className = 'fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-900 text-white p-4 rounded-lg shadow-xl z-50 transition-transform duration-300 transform-gpu';
            document.body.appendChild(box);

            setTimeout(() => {
                box.style.transform = 'translateY(100%)';
                box.style.opacity = '0';
                setTimeout(() => box.remove(), 500);
            }, 3000);
        }

        function copyToClipboard() {
            const data = <?php echo json_encode($extracted_data ?? []); ?>;
            if (Object.keys(data).length === 0) {
                showMessageBox('Tidak ada data untuk disalin.');
                return;
            }

            let clipboardText = '';
            for (const key in data) {
                clipboardText += `${key}: ${data[key]}\n`;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(clipboardText).then(() => {
                    showMessageBox('Data berhasil disalin ke clipboard!');
                }).catch(err => {
                    fallbackCopyTextToClipboard(clipboardText);
                });
            } else {
                fallbackCopyTextToClipboard(clipboardText);
            }
        }

        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.width = "2em";
            textArea.style.height = "2em";
            textArea.style.padding = "0";
            textArea.style.border = "none";
            textArea.style.outline = "none";
            textArea.style.boxShadow = "none";
            textArea.style.background = "transparent";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showMessageBox('Data berhasil disalin ke clipboard!');
            } catch (err) {
                showMessageBox('Gagal menyalin data ke clipboard: ' + err);
            }
            document.body.removeChild(textArea);
        }
    </script>
</body>
</html>
