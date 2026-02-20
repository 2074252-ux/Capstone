<?php
session_start();
require 'db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = md5($_POST['password']); // Later: change to password_hash()

    $sql = "SELECT * FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_id'] = $user['id'];
        
        // Redirect based on role
        if ($user['role'] === 'employee') {
            header("Location: purchase.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    } else {
        $message = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Che-Che MiniMart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Full-page blurred background using your image */
        .bg-hero {
            position: fixed;
            inset: 0;
            background-image: url('images/loginbg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: -3;
        }
        /* blurred overlay */
        .bg-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            backdrop-filter: blur(10px) saturate(120%);
            -webkit-backdrop-filter: blur(10px) saturate(120%);
            background: rgba(15, 23, 42, 0.35); /* subtle dark tint */
            z-index: -2;
        }

        /* subtle vignette to focus the form */
        .bg-vignette {
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse at center, rgba(0,0,0,0.0) 0%, rgba(0,0,0,0.25) 100%);
            z-index: -1;
            pointer-events: none;
        }

        /* glass card */
        .glass-card {
            background: linear-gradient(180deg, rgba(255,255,255,0.75), rgba(255,255,255,0.65));
            border: 1px solid rgba(255,255,255,0.45);
            box-shadow: 0 10px 30px rgba(2,6,23,0.35);
            backdrop-filter: blur(8px) saturate(120%);
            -webkit-backdrop-filter: blur(8px) saturate(120%);
        }

        /* small logo circle */
        .logo-circle {
            width: 72px;
            height: 72px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #f59e0b, #fb923c);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
            box-shadow: 0 6px 18px rgba(251,146,60,0.25);
        }

        /* subtle input focus */
        input:focus {
            outline: none;
            box-shadow: 0 4px 18px rgba(99,102,241,0.12);
            border-color: rgba(99,102,241,0.9);
        }

        /* responsive adjustments */
        @media (min-width: 768px) {
            .form-wrapper { max-width: 520px; }
        }

    </style>
</head>
<body class="antialiased text-slate-800 min-h-screen flex items-center justify-center">

    <div class="bg-hero" aria-hidden="true"></div>
    <div class="bg-vignette" aria-hidden="true"></div>

    <main class="w-full px-6 py-12">
        <div class="mx-auto form-wrapper glass-card rounded-2xl p-8 md:p-12">
            <div class="flex items-center gap-4 mb-6">
               
                <div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Inventory Management System using Barcode Technology for Convenience Store</h1>
                    <p class="text-sm text-slate-600">Welcome back — please sign in to continue</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="p-3 mb-4 text-sm text-red-800 rounded-md bg-red-50 border border-red-100" role="alert">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="w-full p-3 border border-slate-200 rounded-lg bg-white/90 transition-colors"
                        required autofocus
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="w-full p-3 border border-slate-200 rounded-lg bg-white/90 transition-colors"
                        required
                    >
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="remember" class="h-4 w-4 text-amber-500 rounded" />
                        <span class="text-slate-600">Remember me</span>
                    </label>
                    <a href="#" class="text-amber-600 hover:underline">Forgot password?</a>
                </div>

                <button type="submit" class="w-full py-3 mt-1 text-center font-semibold text-white bg-amber-500 rounded-lg shadow hover:bg-amber-600 transition-colors">
                    Log in
                </button>
            </form>

            <p class="text-center text-sm text-slate-600 mt-6">Don't have an account? <a href="register.php" class="text-amber-600 hover:underline">Sign up</a></p>
        </div>
    </main>

</body>
</html>
