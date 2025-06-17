<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
$error = '';
$db = new Database();
$auth = new Auth($db->getConnection());
// Debug log
error_log("Login page accessed");
if ($auth->isLoggedIn()) {
    error_log("User already logged in, redirecting to dashboard");
    header("Location: /StatiCore/views/" . $_SESSION['role'] . "/dashboard.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Login attempt - POST request received");
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    error_log("Login attempt - Username: " . $username);
    if ($auth->login($username, $password)) {
        error_log("Login successful - Redirecting to dashboard");
        header("Location: /StatiCore/views/" . $_SESSION['role'] . "/dashboard.php");
        exit();
    } else {
        error_log("Login failed - Invalid credentials");
        $error = 'Username atau password salah';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - StatiCore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2c5282',
                        secondary: '#4299e1',
                        accent: '#f6ad55',
                        light: '#f7fafc'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .glass-panel {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            background: rgba(44, 82, 130, 0.8);
            border: 1px solid rgba(66, 153, 225, 0.3);
        }

        .floating-label {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-focus:focus+.floating-label,
        .input-filled+.floating-label {
            transform: translateY(-40px) scale(0.85);
            color: #63b3ed;
            font-weight: 500;
            background-color: rgba(255, 255, 255, 0.05);
            padding: 0 6px;
            border-radius: 4px;
        }

        .ripple {
            position: relative;
            overflow: hidden;
        }

        .ripple::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transition: width 0.6s, height 0.6s;
            transform: translate(-50%, -50%);
            z-index: 1;
        }

        .ripple:active::before {
            width: 300px;
            height: 300px;
        }

        .loader {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 3px solid #ffffff;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Improved Progress Bar Styles */
        .progress-container {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #2c5282, #4299e1, #63b3ed);
            border-radius: 2px;
            transition: width 0.3s ease;
            position: relative;
        }

        .progress-bar.indeterminate {
            width: 30%;
            animation: indeterminateProgress 1.5s ease-in-out infinite;
        }

        @keyframes indeterminateProgress {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(350%);
            }
        }

        .progress-shimmer {
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% {
                left: -100%;
            }

            100% {
                left: 100%;
            }
        }

        .error-slide {
            animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-fade {
            animation: successPulse 0.6s ease-out;
        }

        @keyframes successPulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .bg-gradient-staticore {
            background: linear-gradient(135deg, #2c5282 30%, #4299e1 100%);
        }

        /* Loading dots animation */
        .loading-dots::after {
            content: '';
            animation: dots 1.5s steps(4, end) infinite;
        }

        @keyframes dots {

            0%,
            20% {
                content: '';
            }

            40% {
                content: '.';
            }

            60% {
                content: '..';
            }

            80%,
            100% {
                content: '...';
            }
        }
    </style>
</head>

<body class="bg-gradient-staticore min-h-screen flex items-center justify-center p-4">
    <!-- Background Decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/4 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
    </div>

    <div class="w-full max-w-md relative z-10" data-aos="fade-up" data-aos-delay="100">
        <!-- Main Login Panel -->
        <div class="glass-panel rounded-3xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="text-center py-8 px-6 bg-white/10 border-b border-white/20">
                <div
                    class="w-16 h-16 bg-gradient-to-br from-primary to-secondary rounded-2xl mx-auto mb-4 flex items-center justify-center shadow-lg">
                    <img src="assets/logo/logo.png" alt="StatiCore Logo" class="w-10 h-10">
                </div>
                <h1 class="text-2xl font-bold text-white mb-2">StatiCore</h1>
                <p class="text-white/80 text-sm font-medium">Platform Pembelajaran Digital Statistika</p>
                <div class="w-16 h-1 bg-gradient-to-r from-secondary to-accent mx-auto mt-3 rounded-full"></div>
            </div>

            <!-- Form Container -->
            <div class="p-8">
                <!-- Error Alert -->
                <?php if ($error): ?>
                    <div
                        class="error-slide mb-6 bg-red-500/20 backdrop-blur-sm border border-red-400/30 rounded-xl p-4 text-red-100">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                            <span class="font-medium"><?php echo $error; ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form id="loginForm" method="POST" action="" class="space-y-6">
                    <!-- Username Field -->
                    <div class="relative">
                        <input type="text" id="username" name="username"
                            class="input-focus w-full px-4 py-4 bg-white/20 border border-white/30 rounded-xl text-white placeholder-transparent focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition-all duration-300 hover:shadow-md peer"
                            placeholder="Username" autocomplete="username" autocapitalize="off" required>
                        <label for="username"
                            class="floating-label absolute left-4 top-4 text-white/70 pointer-events-none select-none">
                            <i class="fas fa-user mr-2"></i>Username
                        </label>
                        <div class="absolute right-4 top-4 text-white/50">
                            <i class="fas fa-check hidden peer-valid:inline-block text-green-400"></i>
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="relative">
                        <input type="password" id="password" name="password"
                            class="input-focus w-full px-4 py-4 bg-white/20 border border-white/30 rounded-xl text-white placeholder-transparent focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition-all duration-300 hover:shadow-md peer"
                            placeholder="Password" autocomplete="current-password" required>
                        <label for="password"
                            class="floating-label absolute left-4 top-4 text-white/70 pointer-events-none select-none">
                            <i class="fas fa-lock mr-2"></i>Password
                        </label>
                        <button type="button" id="togglePassword"
                            class="absolute right-4 top-4 text-white/50 hover:text-white focus:outline-none transition-all duration-200 hover:scale-110">
                            <i id="eyeIcon" class="fas fa-eye"></i>
                        </button>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="loginBtn"
                        class="ripple w-full bg-gradient-to-r from-primary to-secondary hover:from-primary/90 hover:to-secondary/90 text-white font-semibold py-4 px-6 rounded-xl shadow-lg hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-secondary/50 active:scale-[0.98] relative overflow-hidden">
                        <span id="btnText" class="relative z-10 flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-3"></i>
                            Masuk ke StatiCore
                        </span>
                        <div id="btnLoader" class="hidden relative z-10 flex items-center justify-center">
                            <div class="loader mr-3"></div>
                            <span class="loading-dots">Memproses</span>
                        </div>
                    </button>
                </form>

                <!-- Progress Bar Container -->
                <div id="progressContainer" class="hidden mt-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white/70 text-sm">Memproses login</span>
                        <span id="progressPercent" class="text-white/70 text-sm">0%</span>
                    </div>
                    <div class="progress-container">
                        <div id="progressBar" class="progress-bar">
                            <div class="progress-shimmer"></div>
                        </div>
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="mt-6 text-center">
                    <p class="text-white/60 text-sm">
                        <i class="fas fa-shield-alt mr-2"></i>
                        Akses aman dengan enkripsi SSL
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center">
        </div>

        <!-- Back to Home Button -->
        <div class="mt-4 text-center">
            <a href="/StatiCore/index.php"
                class="inline-block bg-white/10 hover:bg-white/20 text-white font-medium py-2 px-6 rounded-xl transition-all duration-300 focus:outline-none backdrop-blur-sm border border-white/20">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Halaman Awal
            </a>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal"
        class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div
            class="bg-white/20 backdrop-blur-lg border border-white/30 rounded-2xl p-8 text-center max-w-sm mx-4 success-fade">
            <div class="w-16 h-16 bg-green-500 rounded-full mx-auto mb-4 flex items-center justify-center">
                <i class="fas fa-check text-white text-2xl"></i>
            </div>
            <h3 class="text-white text-xl font-semibold mb-2">Login Berhasil!</h3>
            <p class="text-white/80 text-sm mb-4">Mengarahkan ke dashboard...</p>
            <div class="progress-container">
                <div class="progress-bar indeterminate">
                    <div class="progress-shimmer"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true
        });

        // DOM Elements
        const loginForm = document.getElementById('loginForm');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        const eyeIcon = document.getElementById('eyeIcon');
        const loginBtn = document.getElementById('loginBtn');
        const btnText = document.getElementById('btnText');
        const btnLoader = document.getElementById('btnLoader');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');
        const successModal = document.getElementById('successModal');

        // Progress bar controller
        class ProgressController {
            constructor(progressBar, progressPercent) {
                this.progressBar = progressBar;
                this.progressPercent = progressPercent;
                this.currentProgress = 0;
                this.interval = null;
            }

            start() {
                this.currentProgress = 0;
                this.updateProgress(0);

                // Simulate faster, more responsive progress
                this.interval = setInterval(() => {
                    if (this.currentProgress < 50) {
                        this.currentProgress += Math.random() * 8 + 4; // Faster initial progress
                    } else if (this.currentProgress < 80) {
                        this.currentProgress += Math.random() * 4 + 2;
                    } else if (this.currentProgress < 90) {
                        this.currentProgress += Math.random() * 2 + 1;
                    } else if (this.currentProgress < 95) {
                        this.currentProgress += Math.random() * 1;
                    }

                    if (this.currentProgress > 95) {
                        this.currentProgress = 95; // Don't complete until we get response
                    }

                    this.updateProgress(this.currentProgress);
                }, 80); // Faster update interval
            }

            complete() {
                if (this.interval) {
                    clearInterval(this.interval);
                }
                this.currentProgress = 100;
                this.updateProgress(100);

                // Hide progress bar quickly after completion
                setTimeout(() => {
                    progressContainer.classList.add('hidden');
                }, 200);
            }

            reset() {
                if (this.interval) {
                    clearInterval(this.interval);
                }
                this.currentProgress = 0;
                this.updateProgress(0);
            }

            updateProgress(percent) {
                const roundedPercent = Math.round(percent);
                this.progressBar.style.width = `${roundedPercent}%`;
                this.progressPercent.textContent = `${roundedPercent}%`;
            }
        }

        const progressController = new ProgressController(progressBar, progressPercent);

        // Form validation
        function validateForm() {
            const username = usernameInput.value.trim();
            const password = passwordInput.value.trim();

            if (!username) {
                usernameInput.focus();
                showValidationError(usernameInput, 'Username tidak boleh kosong');
                return false;
            }

            if (!password) {
                passwordInput.focus();
                showValidationError(passwordInput, 'Password tidak boleh kosong');
                return false;
            }

            if (username.length < 3) {
                usernameInput.focus();
                showValidationError(usernameInput, 'Username minimal 3 karakter');
                return false;
            }

            return true;
        }

        function showValidationError(input, message) {
            // Remove existing error
            const existingError = input.parentNode.querySelector('.validation-error');
            if (existingError) {
                existingError.remove();
            }

            // Add error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'validation-error text-red-400 text-sm mt-1 error-slide';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i>${message}`;
            input.parentNode.appendChild(errorDiv);

            // Add error styling to input
            input.classList.add('border-red-400');

            // Remove error after 5 seconds
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.remove();
                }
                input.classList.remove('border-red-400');
            }, 5000);
        }

        // Floating Label Logic
        function handleFloatingLabel(input) {
            if (input.value.trim() !== '') {
                input.classList.add('input-filled');
            } else {
                input.classList.remove('input-filled');
            }
        }

        // Input Event Listeners
        usernameInput.addEventListener('input', function () {
            handleFloatingLabel(this);
            // Remove validation error on input
            const errorDiv = this.parentNode.querySelector('.validation-error');
            if (errorDiv) {
                errorDiv.remove();
                this.classList.remove('border-red-400');
            }
        });

        passwordInput.addEventListener('input', function () {
            handleFloatingLabel(this);
            // Remove validation error on input
            const errorDiv = this.parentNode.querySelector('.validation-error');
            if (errorDiv) {
                errorDiv.remove();
                this.classList.remove('border-red-400');
            }
        });

        // Password Visibility Toggle
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            if (type === 'password') {
                eyeIcon.className = 'fas fa-eye';
            } else {
                eyeIcon.className = 'fas fa-eye-slash';
            }

            // Animate icon change
            eyeIcon.style.transform = 'scale(0.8)';
            setTimeout(() => {
                eyeIcon.style.transform = 'scale(1)';
            }, 150);
        });

        // Form Submit Handler
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (!validateForm()) {
                // Shake animation for invalid form
                loginForm.style.animation = 'shake 0.5s ease-in-out';
                setTimeout(() => {
                    loginForm.style.animation = '';
                }, 500);
                return;
            }

            // Show loading state
            btnText.classList.add('hidden');
            btnLoader.classList.remove('hidden');
            loginBtn.disabled = true;
            progressContainer.classList.remove('hidden');

            // Start progress animation
            progressController.start();

            // Submit the actual form immediately
            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(response => response.text())
                .then(data => {
                    progressController.complete();

                    // Check if the response contains error message
                    if (data.includes('Username atau password salah')) {
                        // Failed login - reset immediately
                        btnText.classList.remove('hidden');
                        btnLoader.classList.add('hidden');
                        loginBtn.disabled = false;
                        progressController.reset();
                        progressContainer.classList.add('hidden');
                        this.submit(); // Submit the form normally to show PHP error
                    } else {
                        // Success - show success modal briefly then redirect
                        successModal.classList.remove('hidden');
                        // Quick redirect - let PHP handle it naturally
                        setTimeout(() => {
                            this.submit(); // Let PHP redirect handle the navigation
                        }, 400);
                    }
                })
                .catch(error => {
                    // Reset button state on error immediately
                    btnText.classList.remove('hidden');
                    btnLoader.classList.add('hidden');
                    loginBtn.disabled = false;
                    progressController.reset();
                    progressContainer.classList.add('hidden');
                    console.error('Error:', error);
                    this.submit(); // Fallback to normal form submission
                });
        });

        // Keyboard Navigation
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                const inputs = [usernameInput, passwordInput];
                const currentIndex = inputs.indexOf(document.activeElement);

                if (currentIndex < inputs.length - 1) {
                    inputs[currentIndex + 1].focus();
                } else {
                    loginBtn.focus();
                }
            }
        });

        // Auto-focus first input on page load
        window.addEventListener('load', function () {
            usernameInput.focus();
        });
    </script>
</body>

</html>