
<?php
require 'auth.php';
check_login();

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['access_key'])) {
        if (verify_access_key($_POST['access_key'])) {
            $success = "Access granted for 30 minutes";
            header("Refresh: 1"); // Reload page to show content
        } else {
            $error = "Invalid access key";
        }
    }
}

$show_content = !is_page_restricted() || has_temporary_access();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restricted Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .blur-content {
            filter: blur(10px);
            pointer-events: none;
            user-select: none;
        }
    </style>
</head>

<body class="bg-slate-50">
    <?php include 'sidebar.php'; ?>

    <div class="ml-64 p-8">
        <?php if (!$show_content): ?>
            <!-- Restricted Access Modal -->
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full">
                    <h2 class="text-2xl font-bold mb-4">🔒 Restricted Page</h2>
                    <p class="text-slate-900 mb-6">Please enter the access key provided by the owner to view this content.</p>
                    
                    <?php if ($error): ?>
                        <div class="bg-red-50 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="bg-green-50 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Access Key</label>
                            <input type="password" name="access_key" 
                                   class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500" 
                                   required>
                        </div>
                        <button type="submit" 
                                class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                            Verify Access
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Page Content -->
        <div class="<?= $show_content ? '' : 'blur-content' ?>">
            <!-- Original page content goes here -->
        </div>
    </div>
</body>
</html>