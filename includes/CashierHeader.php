<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Include database connection for status updates
// Use __DIR__ to get the current file's directory and build path relative to project root
require_once __DIR__ . '/../dbConnection/connection.php';

// Check session timeout (30 minutes = 1800 seconds)
$sessionTimeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    // Destroy expired session
    session_unset();
    session_destroy();
    session_start();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// Auth guard: require login for Staff/Cashier pages
$__isLoggedIn = false;
$currentUserId = null;
if (!empty($_SESSION['user'])) { $__isLoggedIn = true; $currentUserId = isset($_SESSION['user']['user_id']) ? $_SESSION['user']['user_id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null); }
elseif (!empty($_SESSION['user_id'])) { $__isLoggedIn = true; $currentUserId = $_SESSION['user_id']; }
elseif (!empty($_SESSION['username'])) { $__isLoggedIn = true; }
elseif (!empty($_SESSION['full_name'])) { $__isLoggedIn = true; }
elseif (!empty($_SESSION['name'])) { $__isLoggedIn = true; }
elseif (!empty($_SESSION['role'])) { $__isLoggedIn = true; }

// If account was deactivated while logged in, force logout (DB-backed users only)
if ($__isLoggedIn && !empty($currentUserId)) {
    $uid = (int)$currentUserId;
    if ($uid > 0) {
        $st = @mysqli_query($conn, "SELECT status FROM users WHERE user_id = $uid LIMIT 1");
        if ($st && ($row = mysqli_fetch_assoc($st))) {
            $statusLower = strtolower((string)($row['status'] ?? ''));
            if ($statusLower !== '' && $statusLower !== 'active') {
                @mysqli_free_result($st);
                @mysqli_close($conn);
                session_unset();
                session_destroy();
                header('Location: /pharmacy/auth/login.php?error=inactive_account');
                exit();
            }
        }
        if ($st instanceof mysqli_result) { @mysqli_free_result($st); }
    }
}

// If not logged in, redirect to login
if (!$__isLoggedIn) {
    @mysqli_close($conn);
    header('Location: /pharmacy/auth/login.php');
    exit();
}
$cashierName = 'Cashier';
if (!empty($_SESSION['user']['full_name'])) { $cashierName = $_SESSION['user']['full_name']; }
elseif (!empty($_SESSION['full_name'])) { $cashierName = $_SESSION['full_name']; }
elseif (!empty($_SESSION['username'])) { $cashierName = $_SESSION['username']; }
elseif (!empty($_SESSION['name'])) { $cashierName = $_SESSION['name']; }

// Get user role for conditional redirect
$userRole = 'Cashier';
$currentUsername = '';
if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
elseif (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }

// Pharmacy account always has admin access
$isPharmacyAccount = (strtolower($currentUsername) === 'pharmacy');

if (!empty($_SESSION['user']['role'])) { $userRole = $_SESSION['user']['role']; }
elseif (!empty($_SESSION['role'])) { $userRole = $_SESSION['role']; }
elseif (!empty($_SESSION['user_role'])) { $userRole = $_SESSION['user_role']; }
$roleLower = strtolower((string)$userRole);
$isAdmin = $isPharmacyAccount || ($roleLower === 'admin' || $roleLower === 'administrator');
$isCashier = ($roleLower === 'cashier' || $roleLower === 'staff' || $roleLower === 'employee');

// Determine admin dashboard link destination based on role
$adminDashboardLink = $isAdmin ? '/pharmacy/Admin/Dashboard.php' : '/pharmacy/Admin/category_management/categories.php';
$adminDashboardLabel = $isAdmin ? 'Admin Dashboard' : 'Categories';

function cashier_initials($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $ini = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $ini .= strtoupper(mb_substr($p, 0, 1));
        if (strlen($ini) >= 2) break;
    }
    return $ini ?: 'U';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - MediCare Pharmacy</title>
    <link rel="stylesheet" href="../Assets/css/tailwind.css">
    <script src="../Assets/js/jquery-3.7.1.min.js"></script>
    <style>
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Product card hover animation */
        .product-card {
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        /* Pulse animation for notifications */
        @keyframes pulse-border {
            0%, 100% {
                border-color: rgba(59, 130, 246, 0.5);
            }
            50% {
                border-color: rgba(59, 130, 246, 1);
            }
        }
        
        /* Smooth fade-in animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased min-h-screen">
    <style>
        .sb-item.active { color: #1d4ed8; background: #eff6ff; }
        .sb-item { transition: background .15s ease, color .15s ease; }
    </style>
    <!-- Shared Sidebar (desktop/tablet) -->
    <aside class="hidden md:flex fixed left-0 top-0 h-screen w-14 lg:w-16 bg-white border-r border-gray-200 shadow-sm z-40">
        <nav class="flex flex-col items-center justify-start w-full p-2">
            <a href="/pharmacy/Staff/Dashboard.php" title="OCT" aria-label="OCT" class="w-12 h-12 rounded-xl mb-2 flex items-center justify-center font-extrabold text-white bg-gradient-to-br from-emerald-500 to-teal-600 shadow">OCT</a>
            <a href="/pharmacy/Staff/Dashboard.php" data-key="dashboard" title="Dashboard" aria-label="Dashboard" class="w-12 h-12 rounded-xl flex items-center justify-center text-gray-600 hover:bg-gray-100 sb-item">🏠</a>
            <a href="/pharmacy/Staff/posManage.php" data-key="pos" title="New Sale / POS" aria-label="New Sale / POS" class="w-12 h-12 rounded-xl flex items-center justify-center text-gray-600 hover:bg-gray-100 mt-2 sb-item">🛒</a>
            <a href="/pharmacy/Staff/inventory.php" data-key="products" title="Available Products (read-only)" aria-label="Products" class="w-12 h-12 rounded-xl flex items-center justify-center text-gray-600 hover:bg-gray-100 mt-2 sb-item">📦</a>
          
            <div class="mt-auto flex-1"></div>
        </nav>
    </aside>
 

    <!-- Global Top Header -->
    <header class="w-full bg-white border-b border-gray-200 sticky top-0 z-30 md:pl-16">
        <div class="mx-auto max-w-7xl px-4 lg:px-6 2xl:px-8 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3 text-gray-900">
                <button id="mobileMenuBtn" class="md:hidden w-9 h-9 rounded-lg border border-gray-200 bg-white flex items-center justify-center"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                <span class="text-2xl">💊</span>
                <div class="leading-tight">
                    <p class="font-bold">Pharmacy</p>
                    <p class="text-xs text-gray-500">Point of Sale</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <button id="userMenuBtn" class="flex items-center gap-2 px-2 py-1.5 rounded-lg border border-gray-200 bg-white shadow-sm hover:bg-gray-50">
                        <div class="w-8 h-8 rounded-full overflow-hidden border-2 border-blue-200 bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                            <span class="text-white text-sm font-bold"><?php echo cashier_initials($cashierName); ?></span>
                        </div>
                        <span class="hidden sm:inline text-sm font-semibold text-gray-700 max-w-[160px] truncate"><?php echo htmlspecialchars($cashierName); ?></span>
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="userMenu" class="hidden absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-50">
                        <div class="px-3 py-2 text-xs text-gray-500">Signed in as</div>
                        <div class="px-3 pb-2 text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($cashierName); ?></div>
                        <div class="h-px bg-gray-200 my-1"></div>
                        <a href="<?php echo $adminDashboardLink; ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            <?php echo htmlspecialchars($adminDashboardLabel); ?>
                        </a>
                        <a href="/pharmacy/auth/logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4 mr-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile sidebar drawer -->
    <div id="mobileSidebar" class="fixed inset-0 z-40 md:hidden hidden">
        <div id="mobileOverlay" class="absolute inset-0 bg-black/40"></div>
        <div class="absolute left-0 top-0 h-full w-64 bg-white border-r border-gray-200 shadow-xl p-3">
            <nav class="flex flex-col gap-1">
                <div class="flex items-center gap-2 px-2 py-3 font-extrabold text-white rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600">OCT</div>
                <a href="/pharmacy/Staff/Dashboard.php" class="px-2 py-2 rounded-lg hover:bg-gray-100">🏠 Dashboard</a>
                <a href="/pharmacy/Staff/posManage.php" class="px-2 py-2 rounded-lg hover:bg-gray-100">🛒 New Sale / POS</a>
                <a href="/pharmacy/Staff/salesHistory.php" class="px-2 py-2 rounded-lg hover:bg-gray-100">📜 Sales History</a>
                <a href="/pharmacy/Staff/inventory.php" class="px-2 py-2 rounded-lg hover:bg-gray-100">📦 Inventory</a>

          
            </nav>
        </div>
    </div>

    <script>
        (function(){
            try{
                var btn = document.getElementById('userMenuBtn');
                var menu = document.getElementById('userMenu');
                if (!btn || !menu) return;
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    menu.classList.toggle('hidden');
                });
                document.addEventListener('click', function(){ menu.classList.add('hidden'); });
            }catch(e){}
        })();
        (function(){
            try{
                var openBtn = document.getElementById('mobileMenuBtn');
                var drawer = document.getElementById('mobileSidebar');
                var overlay = document.getElementById('mobileOverlay');
                if (!openBtn || !drawer) return;
                function close(){ drawer.classList.add('hidden'); }
                function open(){ drawer.classList.remove('hidden'); }
                openBtn.addEventListener('click', function(e){ e.stopPropagation(); open(); });
                if (overlay) overlay.addEventListener('click', close);
                document.addEventListener('keydown', function(e){ if (e.key==='Escape') close(); });
            }catch(e){}
        })();
        
    </script>
