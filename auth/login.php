<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCT PHARMACY Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { color-scheme: light; }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }

        /* ===== Animations ===== */
        @keyframes blob {0%,100%{transform:translate(0,0) scale(1);}25%{transform:translate(20px,-50px) scale(1.1);}50%{transform:translate(-20px,20px) scale(0.9);}75%{transform:translate(50px,50px) scale(1.05);}}
        @keyframes float-slow {0%,100%{transform:translateY(0) translateX(0) rotate(45deg);}25%{transform:translateY(-30px) translateX(15px) rotate(50deg);}50%{transform:translateY(-15px) translateX(-15px) rotate(40deg);}75%{transform:translateY(-40px) translateX(8px) rotate(55deg);}}
        @keyframes float-medium {0%,100%{transform:translateY(0) translateX(0) rotate(-12deg);}33%{transform:translateY(-25px) translateX(20px) rotate(-8deg);}66%{transform:translateY(-12px) translateX(-12px) rotate(-16deg);}}
        @keyframes float-fast {0%,100%{transform:translateY(0) translateX(0) rotate(90deg);}50%{transform:translateY(-20px) translateX(10px) rotate(95deg);}}
        @keyframes float-gentle {0%,100%{transform:translateY(0);}50%{transform:translateY(-10px);}}
        @keyframes pulse-gentle {0%,100%{opacity:1; transform:scale(1);}50%{opacity:0.8; transform:scale(1.05);}}
        @keyframes bounce-gentle {0%,100%{transform:translateY(0);}50%{transform:translateY(-8px);}}
        @keyframes spin-slow {from{transform:rotate(0deg);} to{transform:rotate(360deg);}}
        @keyframes spin-slow-reverse {from{transform:rotate(360deg);} to{transform:rotate(0deg);}}
        @keyframes pulse-glow {0%,100%{opacity:0.15; filter: drop-shadow(0 0 20px currentColor);}50%{opacity:0.25; filter: drop-shadow(0 0 40px currentColor);}}
        @keyframes twinkle {0%,100%{opacity:0.4; transform:scale(1) rotate(0deg);}50%{opacity:1; transform:scale(1.2) rotate(180deg);}}

        .animate-blob{animation:blob 7s ease-in-out infinite;}
        .animate-float-slow{animation:float-slow 8s ease-in-out infinite;}
        .animate-float-medium{animation:float-medium 6s ease-in-out infinite;}
        .animate-float-fast{animation:float-fast 4s ease-in-out infinite;}
        .animate-float-gentle{animation:float-gentle 3s ease-in-out infinite;}
        .animate-pulse-gentle{animation:pulse-gentle 3s ease-in-out infinite;}
        .animate-bounce-gentle{animation:bounce-gentle 2s ease-in-out infinite;}
        .animate-spin-slow{animation:spin-slow 20s linear infinite;}
        .animate-spin-slow-reverse{animation:spin-slow-reverse 20s linear infinite;}
        .animate-pulse-glow{animation:pulse-glow 4s ease-in-out infinite;}
        .animate-twinkle{animation:twinkle 3s ease-in-out infinite;}

        /* Icons & toggles */
        .input-icon {
            position: absolute;
            inset-inline-start: 0.75rem;
            inset-block-start: 50%;
            width: 1.25rem;
            height: 1.25rem;
            color: #94a3b8;
            transform: translateY(-50%);
            pointer-events: none;
            transition: color 0.3s ease;
        }
        .input-group:focus-within .input-icon { color: #0284c7; }

        /* Toast messages */
        .message-toast {
            animation: slideIn 0.3s ease-out, slideOut 0.3s ease-in 2.7s;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            min-width: 300px;
            max-width: 400px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
        }
        .message-toast::before {
            content: '';
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
        }
        .message-toast.bg-green-500::before { content: '✓'; color: #fff; font-weight: 700; display: grid; place-items: center; }
        .message-toast.bg-red-500::before { content: '✕'; color: #fff; font-weight: 700; display: grid; place-items: center; }
        
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
        @media (max-width: 640px) {
            .message-toast {
                top: 1rem;
                right: 1rem;
                left: 1rem;
                min-width: auto;
                max-width: none;
            }
            @keyframes slideIn {
                from { transform: translateY(-100px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateY(0); opacity: 1; }
                to { transform: translateY(-100px); opacity: 0; }
            }
        }

        /* Smooth login mode: pause heavy animations */
        body.is-logging-in .animate-blob,
        body.is-logging-in .animate-float-slow,
        body.is-logging-in .animate-float-medium,
        body.is-logging-in .animate-float-fast,
        body.is-logging-in .animate-float-gentle,
        body.is-logging-in .animate-pulse-gentle,
        body.is-logging-in .animate-bounce-gentle,
        body.is-logging-in .animate-spin-slow,
        body.is-logging-in .animate-spin-slow-reverse,
        body.is-logging-in .animate-pulse-glow,
        body.is-logging-in .animate-twinkle {
            animation: none !important;
        }
    </style>
</head>
<body class="min-h-screen relative flex items-center justify-center p-4 overflow-hidden bg-gradient-to-br from-white via-blue-50 to-cyan-50">
    <!-- Animated Gradient Orbs -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-0 -left-4 w-96 h-96 bg-blue-400/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob"></div>
        <div class="absolute top-0 -right-4 w-96 h-96 bg-cyan-400/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-96 h-96 bg-purple-400/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-4000"></div>
        <div class="absolute bottom-0 right-20 w-96 h-96 bg-pink-400/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-6000"></div>
    </div>

    <!-- Floating Pills -->
    <div class="absolute top-20 left-[10%] w-20 h-10 bg-gradient-to-r from-red-400 to-pink-400 rounded-full opacity-40 blur-[1px] rotate-45 animate-float-slow shadow-[0_0_30px_rgba(244,63,94,0.4)]"></div>
    <div class="absolute top-40 right-[15%] w-24 h-12 bg-gradient-to-r from-blue-400 to-cyan-400 rounded-full opacity-40 blur-[1px] -rotate-12 animate-float-medium shadow-[0_0_30px_rgba(59,130,246,0.4)]"></div>
    <div class="absolute bottom-32 left-[25%] w-16 h-8 bg-gradient-to-r from-green-400 to-emerald-400 rounded-full opacity-40 blur-[1px] rotate-90 animate-float-fast shadow-[0_0_30px_rgba(16,185,129,0.4)]"></div>
    <div class="absolute bottom-20 right-[30%] w-18 h-9 bg-gradient-to-r from-purple-400 to-violet-400 rounded-full opacity-40 blur-[1px] -rotate-45 animate-float-slow shadow-[0_0_30px_rgba(139,92,246,0.4)]"></div>
    <div class="absolute top-[50%] left-[15%] w-14 h-7 bg-gradient-to-r from-yellow-400 to-orange-400 rounded-full opacity-40 blur-[1px] rotate-12 animate-float-medium shadow-[0_0_30px_rgba(251,146,60,0.4)]"></div>

    <!-- DNA Helix -->
    <div class="absolute top-[15%] left-[5%] opacity-10 animate-spin-slow">
        <svg class="w-32 h-32 text-cyan-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v20m6-18.5c-1.5 1.5-4 4-6 6s-4.5 4.5-6 6"/></svg>
    </div>
    <div class="absolute bottom-[15%] right-[8%] opacity-10 animate-spin-slow-reverse">
        <svg class="w-28 h-28 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v20m6-18.5c-1.5 1.5-4 4-6 6s-4.5 4.5-6 6"/></svg>
    </div>

    <!-- Floating Medical Icons -->
    <div class="absolute top-[12%] right-[12%] opacity-10 animate-pulse-glow">
        <svg class="w-20 h-20 text-blue-500 drop-shadow-[0_0_15px_rgba(59,130,246,0.5)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12"/></svg>
    </div>
    <div class="absolute bottom-[12%] left-[8%] opacity-10 animate-pulse-glow animation-delay-2000">
        <svg class="w-24 h-24 text-cyan-500 drop-shadow-[0_0_15px_rgba(6,182,212,0.5)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6a2 2 0 012-2h2a2 2 0 012 2v13M5 12h14"/></svg>
    </div>
    <div class="absolute top-[50%] left-[85%] opacity-10 animate-pulse-glow animation-delay-4000">
        <svg class="w-16 h-16 text-purple-500 drop-shadow-[0_0_15px_rgba(139,92,246,0.5)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
    </div>

    <!-- Sparkles -->
    <div class="absolute top-[25%] left-[40%] opacity-50 animate-twinkle">
        <svg class="w-6 h-6 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M10 1l2.39 6.98H19l-5.45 3.95L15.82 19 10 14.02 4.18 19l2.27-7.07L1 7.98h6.61L10 1z"/></svg>
    </div>
    <div class="absolute top-[60%] right-[35%] opacity-50 animate-twinkle animation-delay-1000">
        <svg class="w-8 h-8 text-pink-400" fill="currentColor" viewBox="0 0 20 20"><path d="M10 1l2.39 6.98H19l-5.45 3.95L15.82 19 10 14.02 4.18 19l2.27-7.07L1 7.98h6.61L10 1z"/></svg>
    </div>

    <!-- Main Glassmorphic Card -->
    <div class="relative z-10 w-full max-w-md bg-white/90 border border-blue-200/50 rounded-xl shadow-2xl backdrop-blur-xl p-8 overflow-hidden">
        <!-- Title & Lifting Character -->
        <div class="text-center mb-6">
            <div class="relative inline-flex flex-col items-center gap-1">
                <div class="relative flex items-center justify-center">
                    <div class="relative z-20 flex items-center justify-center bg-transparent rounded-[2rem] shadow-[0_12px_35px_rgba(59,130,246,0.25)]">
                        <span class="px-4 py-1 text-3xl font-extrabold uppercase tracking-[0.45em] text-blue-600 drop-shadow-[0_0_18px_rgba(59,130,246,0.35)]">OCT Pharmacy</span>
                    </div>
                    <span class="absolute -bottom-3 z-30 flex h-5 w-24 items-center justify-between px-3">
                        <span class="h-2 w-4 rounded-full bg-gradient-to-r from-blue-500 to-cyan-400 shadow-[0_0_10px_rgba(59,130,246,0.45)]"></span>
                        <span class="h-2 w-4 rounded-full bg-gradient-to-r from-blue-500 to-cyan-400 shadow-[0_0_10px_rgba(59,130,246,0.45)]"></span>
                    </span>
                </div>
                <img src="https://static.vecteezy.com/system/resources/previews/024/346/369/original/3d-happy-cartoon-boy-on-transparent-background-generative-ai-png.png" alt="OCT Pharmacy" class="relative -mt-5 h-32 object-contain drop-shadow-[0_16px_35px_rgba(59,130,246,0.35)] pointer-events-none select-none">
            </div>
                </div>

        <!-- Login Section -->
        <div id="login-section" class="space-y-6">

            <form id="loginForm" class="space-y-4">
                <div class="space-y-2">
                    <label for="username" class="block text-sm font-semibold text-slate-700 text-left">Username</label>
                    <div class="relative input-group">
                        <input type="text" id="username" name="username" placeholder="your.username" required
                               class="w-full h-12 pl-12 pr-4 rounded-lg border-2 border-blue-200 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-cyan-400 focus:bg-blue-50/50 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="input-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                    </div>
                        </div>

                <div class="space-y-2">
                    <label for="password" class="block text-sm font-semibold text-slate-700 text-left">Password</label>
                    <div class="relative input-group">
                        <input type="password" id="password" name="password" placeholder="••••••••" required
                               class="w-full h-12 pl-12 pr-12 rounded-lg border-2 border-blue-200 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-cyan-400 focus:bg-blue-50/50 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="input-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        <button type="button" onclick="toggleLoginPassword()"
                                class="absolute inset-y-0 right-2 flex items-center justify-center px-3 text-slate-400 hover:text-sky-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor" id="eyeIcon">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                    </div>

                <button type="submit" class="w-full h-14 bg-gradient-to-r from-blue-600 via-cyan-500 to-teal-500 text-white font-bold rounded-lg shadow-lg hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                    <span>Sign In</span>
                    <svg class="w-5 h-5 text-white animate-pulse-gentle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </form>

            
                </div>

                <!-- Forgot Password Section -->
        <div id="forgot-section" class="hidden text-center space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Forgot Password?</h2>
                <p class="text-sm text-slate-600 mt-2">Please contact your administrator to reset your password.</p>
            </div>
            <button type="button" onclick="showLogin()" class="w-full h-14 bg-slate-800 text-white font-semibold rounded-lg shadow-lg hover:bg-slate-700 transition-colors">
                Back to Login
            </button>
        </div>
    </div>


    <script>
        const API_BASE_URL = '../Controllers/loginApiController.php';

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const signInButton = this.querySelector('button[type="submit"]');

            if (!username || !password) {
                showError('Please fill in all fields.');
                return;
            }

            try {
                // Smooth state: pause heavy animations and disable button
                document.body.classList.add('is-logging-in');
                if (signInButton) {
                    signInButton.setAttribute('disabled', 'disabled');
                    signInButton.classList.add('opacity-70', 'cursor-not-allowed');
                }

                const response = await fetch(API_BASE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });

                const result = await response.json();
                
                if (result.success) {
                    showSuccess('Login successful!');
                    const role = (result.user && result.user.role) ? String(result.user.role).toLowerCase() : '';
                    
                    // Auto-redirect based on role
                    if (role === 'admin' || role === 'administrator') {
                        window.location.replace('../Admin/Dashboard.php');
                    } else if (role === 'cashier' || role === 'staff' || role === 'employee') {
                        window.location.replace('../Staff/Dashboard.php');
                    } else {
                        // Fallback: redirect to admin dashboard
                        window.location.replace('../Admin/Dashboard.php');
                    }
                } else {
                    showError(result.error || 'Invalid credentials.');
                    // Re-enable on error
                    document.body.classList.remove('is-logging-in');
                    if (signInButton) {
                        signInButton.removeAttribute('disabled');
                        signInButton.classList.remove('opacity-70', 'cursor-not-allowed');
                    }
                }
            } catch (error) {
                showError('Network error: ' + error.message);
                document.body.classList.remove('is-logging-in');
                if (signInButton) {
                    signInButton.removeAttribute('disabled');
                    signInButton.classList.remove('opacity-70', 'cursor-not-allowed');
                }
            }
        });

        function showLogin() {
            document.getElementById('forgot-section').classList.add('hidden');
            document.getElementById('login-section').classList.remove('hidden');
        }

        function showForgot() {
            document.getElementById('login-section').classList.add('hidden');
            document.getElementById('forgot-section').classList.remove('hidden');
        }

        function toggleLoginPassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.906 1.25L3.707 2.293zM14.95 6.05a3 3 0 114.242 4.242L9.243 4.757 14.95 6.05z" clip-rule="evenodd"/>
                    <path d="M1.05 6.05l2.122 2.122A10.014 10.014 0 00.458 10c1.274 4.057 5.064 7 9.542 7 2.358 0 4.516-.867 6.168-2.304l2.122 2.122a1 1 0 101.414-1.414l-14-14a1 1 0 00-1.414 1.414l-2.122 2.122zM6.172 8.172L10 12l-3.828-3.828z"/>
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                `;
            }
        }

        function showSuccess(message) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message-toast fixed top-4 right-4 bg-green-500 text-white px-5 py-3 rounded-lg shadow-lg z-50 font-medium text-sm';
            messageDiv.textContent = message;
            document.body.appendChild(messageDiv);
            setTimeout(() => messageDiv.remove(), 3000);
        }

        function showError(message) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message-toast fixed top-4 right-4 bg-red-500 text-white px-5 py-3 rounded-lg shadow-lg z-50 font-medium text-sm';
            messageDiv.textContent = message;
            document.body.appendChild(messageDiv);
            setTimeout(() => messageDiv.remove(), 3000);
        }

        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const response = await fetch(API_BASE_URL);
                const result = await response.json();
                
                if (result.logged_in) {
                    const role = (result.user && result.user.role) ? String(result.user.role).toLowerCase() : '';
                    
                    // Auto-redirect based on role
                    if (role === 'admin' || role === 'administrator') {
                        window.location.replace('../Admin/Dashboard.php');
                    } else if (role === 'cashier' || role === 'staff' || role === 'employee') {
                        window.location.replace('../Staff/Dashboard.php');
                    } else {
                        // Fallback: redirect to admin dashboard
                        window.location.replace('../Admin/Dashboard.php');
                    }
                }
            } catch (error) {
                console.log('Could not check login status:', error.message);
            }
        });
    </script>
</body>
</html>
