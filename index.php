<?php
session_start();

// Password protection
$password = 'secret'; // Replace with your desired password
$is_authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// Logout mechanism
if (isset($_GET['logout'])) {
    unset($_SESSION['authenticated']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (!$is_authenticated) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === $password) {
            $_SESSION['authenticated'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = 'Incorrect password';
        }
    }

    // Display password prompt
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Protected</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="styles.css">
    </head>
    <body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Password Required</h5>
                    </div>
                    <div class="modal-body">
                        <form method="post">
                            <div class="mb-3">
                                <label for="password" class="form-label">Enter Password:</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
} else {
    // Rest of the original code
    $directory = __DIR__ . '/files';
    if (!file_exists($directory)) {
        mkdir($directory, 0755, true);
    }

    $directory = realpath($directory);
    if ($directory === false || !is_dir($directory)) {
        die("Invalid directory");
    }

    $files = glob($directory . '/*.md');

    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $latestFiles = array_slice($files, 0, 5);
    $olderFiles = array_slice($files, 5);

    $alert = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            if ($_POST['action'] === 'create' || $_POST['action'] === 'edit') {
                $date = date('Ymd');
                $filename = $date . '-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['filename']) . '.md';
                $filepath = $directory . '/' . $filename;
                
                if (strpos(realpath(dirname($filepath)), $directory) !== 0) {
                    die("Invalid file path");
                }
                
                $content = strip_tags($_POST['content']);
                file_put_contents($filepath, $content);
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'File ' . ($_POST['action'] === 'create' ? 'created' : 'updated') . ' successfully.'
                ];
            } elseif ($_POST['action'] === 'delete') {
                $filename = basename($_POST['filename']);
                $filepath = $directory . '/' . $filename;
                
                if (strpos(realpath(dirname($filepath)), $directory) !== 0) {
                    die("Invalid file path");
                }
                
                if (is_file($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) === 'md') {
                    unlink($filepath);
                    $_SESSION['alert'] = [
                        'type' => 'warning',
                        'message' => 'File deleted successfully.'
                    ];
                }
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $editFile = isset($_GET['edit']) ? basename($_GET['edit']) : null;
    if ($editFile !== null && strpos($editFile, '..') !== false) {
        $editFile = null;
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Display alert from session if it exists
    if (isset($_SESSION['alert'])) {
        $alertType = $_SESSION['alert']['type'];
        $alertMessage = $_SESSION['alert']['message'];
        $alert = "<div class='alert alert-{$alertType} alert-dismissible fade show' role='alert'>
                    {$alertMessage}
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                  </div>";
        unset($_SESSION['alert']);
    }

    function renderFileListItem($file) {
        $basename = basename($file);
        return "
        <li class='list-group-item d-flex justify-content-between align-items-center'>
            <div class='file-info' data-bs-toggle='modal' data-bs-target='#viewModal' data-file='" . htmlspecialchars($basename) . "'>
                <span class='file-name'>" . htmlspecialchars($basename) . "</span>
                <span class='file-date'>Last updated: " . date("F d, Y H:i", filemtime($file)) . "</span>
            </div>
            <div>
                <button class='btn btn-icon view-md' data-bs-toggle='modal' data-bs-target='#viewModal' data-file='" . htmlspecialchars($basename) . "' title='View'>
                    <i class='fas fa-eye'></i>
                </button>
                <a href='?edit=" . urlencode($basename) . "' class='btn btn-icon' title='Edit'>
                    <i class='fas fa-edit'></i>
                </a>
                <button class='btn btn-icon copy-url' data-url='" . htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/files/' . $basename) . "' title='Copy URL'>
                    <i class='fas fa-copy'></i>
                </button>
                <a href='view_html.php?file=" . urlencode($basename) . "' class='btn btn-icon' title='View as HTML'>
                    <i class='fas fa-code'></i>
                </a>
                <button class='btn btn-icon delete-file' data-bs-toggle='modal' data-bs-target='#deleteModal' data-file='" . htmlspecialchars($basename) . "' title='Delete'>
                    <i class='fas fa-trash'></i>
                </button>
            </div>
        </li>";
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Vincent's MarkDown</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="styles.css">
    </head>
    <body class="d-flex flex-column min-vh-100">
        <header class="py-2 sticky-header">
            <div class="container d-flex justify-content-between align-items-center">
                <a href="./" class="nav-brand">
                    <strong>VINCENT</strong><span>MARKDOWN</span>
                </a>
                <a href="?logout" class="btn btn-outline-dark btn-sm">Logout</a>
            </div>
        </header>

        <main class="flex-grow-1">
            <div class="container my-4">
                <?php echo $alert; ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><?php echo $editFile ? 'Edit File' : 'Create New File'; ?></h2>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="<?php echo $editFile ? 'edit' : 'create'; ?>">
                            <div class="mb-3">
                                <label for="filename" class="form-label">Filename:</label>
                                <input type="text" class="form-control" id="filename" name="filename" value="<?php echo $editFile ? substr(pathinfo($editFile, PATHINFO_FILENAME), 9) : ''; ?>" required pattern="[a-zA-Z0-9_-]+">
                            </div>
                            <div class="mb-3">
                                <textarea id="editor" name="content"><?php echo $editFile ? htmlspecialchars(file_get_contents($directory . '/' . $editFile)) : ''; ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo $editFile ? 'Update File' : 'Create File'; ?></button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Markdown Files</h2>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush mb-4">
                            <?php
                            foreach ($latestFiles as $file) {
                                echo renderFileListItem($file);
                            }
                            ?>
                        </ul>

                        <?php if (!empty($olderFiles)) : ?>
                            <div class="accordion" id="olderFilesAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="olderFilesHeading">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#olderFilesCollapse" aria-expanded="false" aria-controls="olderFilesCollapse">
                                            Older Files
                                        </button>
                                    </h2>
                                    <div id="olderFilesCollapse" class="accordion-collapse collapse" aria-labelledby="olderFilesHeading" data-bs-parent="#olderFilesAccordion">
                                        <div class="accordion-body p-0">
                                            <ul class="list-group list-group-flush">
                                                <?php
                                                foreach ($olderFiles as $file) {
                                                    echo renderFileListItem($file);
                                                }
                                                ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

        <footer class="bg-white py-3 mt-auto border-top">
            <div class="container text-left">
                <p class="mb-0 text-muted footer-text"><small><a href="https://vincentrozenberg.com"><strong>VINCENT</strong>ROZENBERG</a> &copy;<?php echo date('Y'); ?></small></p>
            </div>
        </footer>

        <!-- View Modal -->
        <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewModalLabel">View Markdown</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-end mb-3">
                            <a href="#" id="modalEditBtn" class="btn btn-icon me-2" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button id="modalCopyUrlBtn" class="btn btn-icon me-2" title="Copy URL">
                                <i class="fas fa-copy"></i>
                            </button>
                            <a href="#" id="modalViewHtmlBtn" class="btn btn-icon me-2" title="View as HTML">
                                <i class="fas fa-code"></i>
                            </a>
                            <button class="btn btn-icon delete-file" data-bs-toggle="modal" data-bs-target="#deleteModal" data-file="" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div id="viewModalBody">
                            <!-- Rendered Markdown content will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this file?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" id="deleteForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="filename" id="deleteFilename">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/2.0.3/marked.min.js"></script>
        <script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
        <script src="scripts.js"></script>
    </body>
    </html>
<?php
}
?>
