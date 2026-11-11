<?php
$dir = __DIR__;

// Handle file download request
if (isset($_GET['download'])) {
    // Sanitize filename to prevent directory traversal attacks (e.g. ../../etc/passwd)
    $filename = basename($_GET['download']);
    $filepath = $dir . '/' . $filename;

    if (file_exists($filepath) && is_file($filepath)) {
        // Clear output buffer to prevent corruption
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . encode_filename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));

        readfile($filepath);
        exit;
    } else {
        http_response_code(404);
        $error = "File not found.";
    }
}

// Helper to format file sizes nicely (KB, MB, GB)
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Helper for safe header filenames
function encode_filename($filename) {
    return str_replace(["\r", "\n"], '', $filename);
}

// Helper to format a timestamp as '09 nov 2026'
function formatDate($timestamp) {
    $months = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
    $m = (int) date('n', $timestamp);
    return date('d', $timestamp) . ' ' . $months[$m - 1] . ' ' . date('Y', $timestamp) . ' • ' . date('H:i', $timestamp);
}

// Scan directory for files (excluding '.' '..', 'index.php', and hidden files)
$scan = scandir($dir);
$files = [];
foreach ($scan as $file) {
    if ($file !== '.' && $file !== '..' && $file !== 'index.php' && $file[0] !== '.') {
        $filepath = $dir . '/' . $file;
        if (is_file($filepath)) {
            $files[] = [
                'name' => $file,
                'size' => formatBytes(filesize($filepath)),
                'mtime' => filemtime($filepath),
                'date' => formatDate(filemtime($filepath))
            ];
        }
    }
}

// Sort files by modification date (newest first)
usort($files, function($a, $b) {
    return $b['mtime'] <=> $a['mtime'];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Files</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .file-name {
            overflow: hidden;
            white-space: nowrap;
        }

        .file-name-text {
            display: inline-block;
        }

        .file-name.is-overflowing .file-name-text {
            animation: filename-scroll var(--scroll-duration) linear infinite alternate;
        }

        .file-name:hover .file-name-text {
            animation-play-state: paused;
        }

        @keyframes filename-scroll {
            0%, 10% { transform: translateX(0); }
            90%, 100% { transform: translateX(var(--scroll-distance)); }
        }

        @media (prefers-reduced-motion: reduce) {
            .file-name.is-overflowing .file-name-text {
                animation: none;
            }

            .file-name {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col justify-between font-sans">
    <div class="max-w-3xl w-full mx-auto p-6 sm:p-12">
        <header class="mb-8">
            <h1 class="text-2xl font-bold tracking-tight text-white">Shared Files</h1>
            <p class="text-sm text-gray-400 mt-1">Click on Download button to download your file.</p>
        </header>

        <?php if (isset($error)): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-lg mb-6 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($files)): ?>
            <div class="text-center py-16 border border-dashed border-gray-800 rounded-xl">
                <p class="text-gray-500">No files available right now.</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($files as $f): ?>
                    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 flex items-center justify-between hover:border-gray-700 transition">
                        <div class="min-w-0 flex-1 mr-4">
                            <h2 class="file-name font-medium text-white text-sm sm:text-base" title="<?= htmlspecialchars($f['name']) ?>"><span class="file-name-text"><?= htmlspecialchars($f['name']) ?></span></h2>
                            <div class="flex items-center space-x-3 text-xs text-gray-400 mt-1">
                                <span><?= $f['size'] ?></span>
                                <span>•</span>
                                <span><?= $f['date'] ?></span>
                            </div>
                        </div>
                        <a href="?download=<?= urlencode($f['name']) ?>" class="shrink-0 bg-white text-gray-950 font-medium px-4 py-2 rounded-lg text-xs sm:text-sm hover:bg-gray-200 transition">
                            Download
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <footer class="text-center py-6 text-xs text-gray-600">
        Lightweight File Sharing App - Powered by Giulietto 😎
    </footer>
    <script>
        const filenames = document.querySelectorAll('.file-name');

        function updateFilenameScroll(heading) {
            const text = heading.querySelector('.file-name-text');
            const overflow = Math.max(0, text.scrollWidth - heading.clientWidth);
            heading.style.setProperty('--scroll-distance', `-${overflow}px`);
            heading.style.setProperty('--scroll-duration', `${Math.max(4, overflow / 30 / 0.8)}s`);
            heading.classList.toggle('is-overflowing', overflow > 0);
        }

        const filenameObserver = new ResizeObserver(entries => {
            entries.forEach(entry => updateFilenameScroll(entry.target));
        });

        filenames.forEach(heading => {
            filenameObserver.observe(heading);
            updateFilenameScroll(heading);
        });

        document.fonts.ready.then(() => filenames.forEach(updateFilenameScroll));
    </script>
</body>
</html>