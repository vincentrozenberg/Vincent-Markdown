<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$directory = __DIR__ . '/files';
$file = isset($_GET['file']) ? basename($_GET['file']) : null;

if (!$file || !is_file($directory . '/' . $file) || pathinfo($file, PATHINFO_EXTENSION) !== 'md') {
    die("Invalid file");
}

$filePath = $directory . '/' . $file;
$content = file_get_contents($filePath);

function parseMarkdown($text, $baseUrl, $directory) {
    // Normalize line breaks
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    
    // Split the text into lines
    $lines = explode("\n", $text);
    $parsed = [];
    $inList = false;
    $listBuffer = [];

    foreach ($lines as $line) {
        // Headers
        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
            $level = strlen($matches[1]);
            $parsed[] = "<h$level>" . trim($matches[2]) . "</h$level>";
        }
        // List items
        elseif (preg_match('/^(\s*[-*+])\s+(.+)$/', $line, $matches)) {
            if (!$inList) {
                $inList = true;
                $listBuffer[] = "<ul>";
            }
            $listBuffer[] = "<li>" . trim($matches[2]) . "</li>";
        }
        // End of list
        elseif ($inList && trim($line) === '') {
            $inList = false;
            $listBuffer[] = "</ul>";
            $parsed = array_merge($parsed, $listBuffer);
            $listBuffer = [];
            $parsed[] = ""; // Add an empty line after the list
        }
        // Paragraphs
        elseif (trim($line) !== '') {
            $parsed[] = "<p>" . $line . "</p>";
        }
        // Empty lines
        else {
            $parsed[] = "";
        }
    }

    // Close any open list
    if ($inList) {
        $listBuffer[] = "</ul>";
        $parsed = array_merge($parsed, $listBuffer);
    }

    $text = implode("\n", $parsed);

    // Image parsing (do this before link parsing)
    $text = preg_replace_callback('/!\[([^\]]*)\]\(([^\)]+)\)/', function($matches) use ($baseUrl, $directory) {
        $alt = $matches[1];
        $src = $matches[2];
        $isExternal = preg_match('/^https?:\/\//', $src);

        if ($isExternal) {
            $fullSrc = $src;
            $debug = "<!-- Debug: External image source: $fullSrc -->\n";
        } else {
            $fullSrc = $baseUrl . '/' . $src;
            $localPath = $directory . '/' . $src;
            $fileExists = file_exists($localPath);
            $debug = "<!-- Debug: Local image source: $fullSrc, File exists: " . ($fileExists ? 'Yes' : 'No') . " -->\n";
            
            if (!$fileExists) {
                return $debug . "<img src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=\" alt=\"$alt\" title=\"Image not found: $fullSrc\" style=\"border: 1px solid red;\">";
            }
        }

        return $debug . "<img src=\"$fullSrc\" alt=\"$alt\">";
    }, $text);

    // Inline formatting
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);

    // Link parsing (do this after image parsing)
    $text = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $text);

    // Remove empty paragraphs
    $text = preg_replace('/<p>\s*<\/p>/', '', $text);

    return $text;
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$parsedContent = parseMarkdown($content, $baseUrl, $directory);

$directUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($file); ?> - HTML View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .rendered-markdown img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="py-2 sticky-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="./" class="nav-brand">
                        <strong>VINCENT</strong><span>MARKDOWN</span>
                    </a>
                </div>
                <div class="col">
                    <div class="d-flex align-items-center">
                        <a href="<?php echo $directUrl; ?>" class="file-title me-2"><?php echo htmlspecialchars($file); ?></a>
                        <button class="btn btn-icon copy-url" data-url="<?php echo $directUrl; ?>" title="Copy URL">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <main class="flex-grow-1">
        <div class="container my-4">
            <div class="rendered-markdown">
                <?php echo $parsedContent; ?>
            </div>
        </div>
    </main>
    <footer class="bg-white py-3 mt-auto border-top">
        <div class="container text-left">
            <p class="mb-0 text-muted footer-text"><small><a href="https://vincentrozenberg.com"><strong>VINCENT</strong>ROZENBERG</a>  &copy;<?php echo date('Y'); ?></small></p>
        </div>
    </footer>

    <!-- Copy URL Modal -->
    <div class="modal fade" id="copyUrlModal" tabindex="-1" aria-labelledby="copyUrlModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="copyUrlModalLabel">URL Copied</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    The URL has been copied to your clipboard.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('.copy-url').addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                const copyUrlModal = new bootstrap.Modal(document.getElementById('copyUrlModal'));
                copyUrlModal.show();
            });
        });
    </script>
</body>
</html>
