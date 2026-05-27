<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Auth guard: require login for all Admin pages
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

// Get user information from session (align with CashierHeader.php fallbacks)
$userName = 'Admin User';
if (!empty($_SESSION['user']['full_name'])) { $userName = $_SESSION['user']['full_name']; }
elseif (!empty($_SESSION['full_name'])) { $userName = $_SESSION['full_name']; }
elseif (!empty($_SESSION['username'])) { $userName = $_SESSION['username']; }
elseif (!empty($_SESSION['name'])) { $userName = $_SESSION['name']; }
elseif (!empty($_SESSION['user_name'])) { $userName = $_SESSION['user_name']; }

$userRole = 'Administrator';
if (!empty($_SESSION['user']['role'])) { $userRole = $_SESSION['user']['role']; }
elseif (!empty($_SESSION['role'])) { $userRole = $_SESSION['role']; }
elseif (!empty($_SESSION['user_role'])) { $userRole = $_SESSION['user_role']; }

$userEmail = !empty($_SESSION['user_email']) ? $_SESSION['user_email'] : 'admin@pharmacy.com';

// Check if current user is the pharmacy master account (always has full access)
$isPharmacyAccount = false;
$currentUsername = '';
if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
elseif (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }
$isPharmacyAccount = (strtolower($currentUsername) === 'pharmacy');

// Get initials from name
function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
$userInitials = getInitials($userName);
$roleLower = strtolower((string)$userRole);
// Pharmacy account always has admin access, regardless of role setting
$isAdmin = $isPharmacyAccount || ($roleLower === 'admin' || $roleLower === 'administrator');
$isCashier = ($roleLower === 'cashier' || $roleLower === 'staff' || $roleLower === 'employee');
// Expose for included pages
if (!isset($GLOBALS['isAdmin'])) { $GLOBALS['isAdmin'] = $isAdmin; }
if (!isset($GLOBALS['isCashier'])) { $GLOBALS['isCashier'] = $isCashier; }

// Get current page for active navigation
$currentPage = basename($_SERVER['PHP_SELF']);

// Determine the correct base path for navigation links
// This handles the fact that adminHeader.php is included from different directory levels
$scriptPath = $_SERVER['SCRIPT_NAME'];
$adminBasePath = '';

if (strpos($scriptPath, '/Admin/') !== false) {
    // We're in the Admin directory or a subdirectory
    $pathParts = explode('/Admin/', $scriptPath);
    $afterAdmin = $pathParts[1] ?? '';
    $depth = substr_count($afterAdmin, '/');
    
    // Build the relative path back to Admin root
    $adminBasePath = str_repeat('../', $depth);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pharmacy Management Dashboard</title>
<link rel="stylesheet" href="<?php echo $adminBasePath; ?>../Assets/css/tailwind.css">
<script src="<?php echo $adminBasePath; ?>../Assets/js/jquery-3.7.1.min.js"></script>
<style>
/* Updated Tooltip styles - positioned outside sidebar */
.tooltip {
    position: absolute;
    left: calc(100% + 10px); /* Position outside sidebar */
    top: 50%;
    transform: translateY(-50%);
    background: white;
    color: black;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    pointer-events: none;
}
.dropdown-tooltip::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 1rem;
    border-width: 6px 6px 6px 0;
    border-style: solid;
    border-color: transparent #1f2937 transparent transparent;
}

.tooltip::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 50%;
    transform: translateY(-50%);
    border-width: 6px 6px 6px 0;
    border-style: solid;
    border-color: transparent #1f2937 transparent transparent;
}

.sb-item {
    position: relative;
}

/* Show tooltip ONLY when sidebar is collapsed */
#sidebar.collapsed .sb-item:hover .tooltip {
    opacity: 1;
    visibility: visible;
}

/* Hide tooltip when sidebar is expanded (default state) */
.sb-item:hover .tooltip {
    opacity: 0;
    visibility: hidden;
}

/* Ensure sidebar doesn't clip tooltips */
#sidebar {
    overflow: visible !important;
}

#sidebar nav {
    overflow: visible !important;
}

/* Enhanced sidebar styles with shrink functionality */
.nav-item-icon {
  width: 1.25rem;
  height: 1.25rem;
  margin-right: 0.75rem;
  flex-shrink: 0;
}

.dropdown-arrow {
  transition: all 0.3s ease;
  margin-left: auto;
}

.dropdown-arrow.rotated {
  transform: rotate(180deg);
}

/* Sidebar shrink transitions */
#sidebar {
  transition: width 0.3s ease-in-out !important;
}

#sidebar.collapsed {
  width: 80px !important;
}

/* Text and element transitions */
#logo-text, #nav-text, .font-medium.text-gray-700 {
  transition: all 0.3s ease-in-out;
  overflow: hidden;
  white-space: nowrap;
}

.bg-red-500 {
  transition: all 0.3s ease-in-out;
}

/* Collapsed state styles */
#sidebar.collapsed .nav-item-icon {
  margin-right: 0;
  margin-left: auto;
  margin-right: auto;
}

#sidebar.collapsed .dropdown-arrow {
  display: none;
}
/* Hide nav text when sidebar is collapsed */
#sidebar.collapsed #nav-text {
  opacity: 0;
  width: 0;
  overflow: hidden;
  margin-left: 0;
  display: none;
}

/* Hide all dropdowns when sidebar is collapsed */
#sidebar.collapsed #categoryDropdown,
#sidebar.collapsed #productDropdown,
#sidebar.collapsed #supplierDropdown,
#sidebar.collapsed #inventoryDropdown,
#sidebar.collapsed #stockDropdown,
#sidebar.collapsed #salesDropdown,
#sidebar.collapsed #reportsDropdown {
  display: none !important;
}

/* Dropdown tooltip styles - hidden by default */
.dropdown-tooltip {
  position: absolute;
  left: calc(100% + 10px);
  top: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  z-index: 1000;
  min-width: 180px;
  padding: 0.5rem 0;
  display: none !important;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s, visibility 0.3s;
}

.dropdown-tooltip a {
  display: block;
  padding: 0.5rem 0.75rem;
  color: #374151;
  text-decoration: none;
  font-size: 0.875rem;
  transition: background-color 0.2s;
  border-radius: 0.25rem;
  margin: 0 0.25rem;
}

.dropdown-tooltip a:hover {
  background-color: #f3f4f6;
  color: #1d4ed8;
}

/* Show dropdown tooltip ONLY when sidebar is collapsed and hovering */
#sidebar.collapsed .sb-item:hover .dropdown-tooltip {
  display: block !important;
  opacity: 1;
  visibility: visible;
}

/* Ensure tooltips are hidden when sidebar is expanded */
#sidebar:not(.collapsed) .dropdown-tooltip {
  display: none !important;
  opacity: 0;
  visibility: hidden;
}

/* Center navigation items when collapsed */
#sidebar.collapsed .flex.items-center {
  justify-content: center !important;
}

/* Perfect centering for collapsed state */
#sidebar.collapsed .flex.justify-between {
  justify-content: center !important;
}

#sidebar.collapsed button.flex.justify-between {
  justify-content: center !important;
}

#sidebar.collapsed .ml-auto {
  display: none !important;
}

/* Hide text and badges when collapsed */
#sidebar.collapsed .font-medium.text-gray-700,
#sidebar.collapsed .bg-red-500,
#sidebar.collapsed #logo-text,
#sidebar.collapsed .section-header {
  opacity: 0;
  width: 0;
  overflow: hidden;
  margin-bottom: 0 !important;
  display: none;
}

/* Maintain proper spacing between sections when collapsed */
#sidebar.collapsed .px-0.mb-4 {
  margin-bottom: 1rem !important;
}

/* Perfect spacing between navigation items when collapsed */
#sidebar.collapsed .mb-0 {
  margin-bottom: 0.25rem !important;
}

/* Ensure consistent icon spacing */
#sidebar.collapsed .py-3 {
  padding-top: 0.75rem !important;
  padding-bottom: 0.75rem !important;
}

/* Perfect icon centering for collapsed state */
#sidebar.collapsed .w-5.h-5 {
  width: 1.25rem !important;
  height: 1.25rem !important;
}

/* Remove any extra margins when collapsed */
#sidebar.collapsed .ml-4 {
  margin-left: 0 !important;
}

#sidebar.collapsed .mx-2 {
  margin-left: 0.5rem !important;
  margin-right: 0.5rem !important;
}

/* Ensure proper logo centering */
#sidebar.collapsed .p-6 {
  padding: 1.5rem 0.5rem !important;
  justify-content: center !important;
}

/* Header styles */
.header-search {
  background-color: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 0.5rem 1rem;
  transition: all 0.2s ease;
}

.header-search:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.header-icon {
  width: 1.5rem;
  height: 1.5rem;
  color: #6b7280;
  transition: color 0.2s ease;
}

.header-icon:hover {
  color: #374151;
}

.user-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background-color: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Responsive design */
@media (max-width: 768px) {
  /* Mobile sidebar - hidden by default, slides in from left */
  #sidebar {
    position: fixed !important;
    top: 75px !important;
    left: 0 !important;
    width: 280px !important;
    height: calc(100vh - 75px) !important;
    z-index: 50 !important;
    transform: translateX(-100%) !important;
    transition: transform 0.3s ease-in-out !important;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1) !important;
    margin-top: 0 !important;
    padding-top: 0 !important;
  }
  
  /* Hide logo header on mobile */
  #sidebar .p-6.border-b {
    display: none !important;
  }
  
  /* Ensure sidebar doesn't overlap with header */
  #sidebar nav {
    margin-top: 0 !important;
    padding-top: 1rem !important;
  }
  
  /* Show sidebar when opened */
  #sidebar.show {
    transform: translateX(0) !important;
  }
  
  /* Mobile overlay */
  #overlay {
    backdrop-filter: blur(4px);
    background: rgba(0, 0, 0, 0.5);
    z-index: 20 !important; /* keep below sidebar (z-30) */
  }
  
  /* Mobile header - full width with logo like the image */
  header {
    width: 100% !important;
    margin: 0 !important;
    border-radius: 0 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 60 !important;
    background: white !important;
  }
  
  /* Mobile header container */
  .mobile-container {
    padding: 0 !important;
    background: white !important;
  }
  
  /* Mobile header content - compact like the image */
  header .px-6 {
    padding: 0.75rem 1rem !important;
    min-height: 56px !important;
  }
  
  /* Mobile main content - full width below header */
  main {
    width: 100% !important;
    margin-left: 0 !important;
    padding: 1rem !important;
    background: #f8fafc !important;
    min-height: calc(100vh - 56px) !important;
  }
  
  /* Show all text on mobile */
  #sidebar #logo-text,
  #sidebar #nav-text,
  #sidebar .font-medium {
    opacity: 1 !important;
    width: auto !important;
    display: inline-block !important;
  }
  
  /* Mobile navigation styling - larger touch targets */
  #sidebar nav {
    padding: 1rem !important;
    padding-top: 1rem !important;
    margin-top: 0 !important;
  }
  
  /* Mobile sidebar items - larger padding for touch */
  #sidebar .mx-2 {
    margin: 0.25rem 0 !important;
    padding: 0.875rem 1rem !important;
  }
  
  /* Mobile header spacing */
  header .space-x-4 {
    gap: 1rem;
  }
  
  /* Hide user role text on small screens */
  @media (max-width: 640px) {
    #userDropdownBtn .text-right {
      display: none;
    }
  }
  
  /* Very small screens */
  @media (max-width: 480px) {
    header .px-6 {
      padding: 0.75rem !important;
    }
    
    main {
      padding: 0.75rem !important;
    }
    
    #sidebar {
      width: 260px !important;
    }
  }
}

/* Scrollbar styling */
nav {
  scrollbar-width: thin;
  scrollbar-color: #d1d5db transparent;
}

nav::-webkit-scrollbar {
  width: 4px;
}

nav::-webkit-scrollbar-track {
  background: transparent;
}

nav::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 2px;
}

nav::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

/* Smooth transitions for all interactive elements */
* {
  transition: all 0.2s ease;
}

/* Additional mobile optimizations */
@media (max-width: 1024px) {
  /* Ensure proper stacking */
  .flex.h-screen {
    flex-direction: column !important;
  }
  
  /* Mobile sidebar positioning */
  #sidebar {
    border-right: none !important;
    top: 75px !important;
    left: 0 !important;
    right: auto !important;
    bottom: 0 !important;
    height: calc(100vh - 75px) !important;
    position: fixed !important;
  }
  
  /* Mobile header positioning */
  header {
    position: sticky !important;
    top: 0 !important;
    z-index: 60 !important;
  }
  
  /* Ensure sidebar navigation starts from top */
  #sidebar nav {
    flex: 1 !important;
    display: flex !important;
    flex-direction: column !important;
  }
  
  /* Remove any top margin from first navigation item */
  #sidebar nav .px-0:first-child {
    margin-top: 0 !important;
    padding-top: 0 !important;
  }
}

/* Tablet optimizations */
@media (min-width: 769px) and (max-width: 1024px) {
  #sidebar {
    width: 250px !important;
    top: 0 !important;
    height: 100vh !important;
  }
}

/* Main content positioning */
@media (min-width: 768px) and (max-width: 1023px) {
  #appMain {
    margin-left: 250px !important;
    transition: margin-left 0.3s ease-in-out !important;
  }

  #sidebar.collapsed + #appMain {
    margin-left: 100px !important;
  }
}

@media (min-width: 1024px) {
  #appMain {
    margin-left: 0 !important;
    transition: margin-left 0.3s ease-in-out !important;
  }

  #sidebar.collapsed + #appMain {
    margin-left: 0 !important;
  }
}

/* Enhanced hover effects for sidebar navigation */
.sb-item button:hover,
.sb-item a:hover {
  transform: translateX(2px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.sb-item button:active,
.sb-item a:active {
  transform: translateX(0);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

/* Improved dropdown button styling */
.sb-item button {
  position: relative;
  overflow: hidden;
}

.sb-item button::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.5s;
}

.sb-item button:hover::before {
  left: 100%;
}

/* Enhanced dropdown tooltip positioning */
.dropdown-tooltip {
  transform: translateY(-10px);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

#sidebar.collapsed .sb-item:hover .dropdown-tooltip {
  transform: translateY(0);
}

/* Improved spacing and alignment */
.sb-item {
  margin-bottom: 0.5rem;
}

.sb-item:last-child {
  margin-bottom: 0;
}

/* Better visual hierarchy */
.nav-item-icon {
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
}

/* Enhanced active state */
.sb-item button.bg-blue-50,
.sb-item a.bg-blue-50 {
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
  border-left: 3px solid #3b82f6;
}
</style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex h-screen">
  <!-- Mobile overlay -->
  <div id="overlay" class="fixed inset-0 bg-black opacity-50 z-20 hidden md:hidden" onclick="toggleSidebar()"></div>

  <!-- Sidebar - Starts expanded (280px) -->
  <aside id="sidebar" class="fixed md:relative z-30 h-screen lg:h-auto lg:max-h-screen bg-white flex flex-col shadow-lg border-r border-gray-200 rounded-l-2xl transform -translate-x-full md:translate-x-0 transition-all duration-300 ease-in-out" style="width: 280px;">
    <!-- Logo Header (Desktop only - hidden on mobile) -->
    <div class="p-6 hidden md:flex items-center border-b border-gray-100">
      <span class="text-gray-900 font-bold text-2xl flex-shrink-0">OCT</span>
      <div id="logo-text" class="ml-2 transition-all duration-300 ease-in-out overflow-hidden">
        <h1 class="text-xl font-bold text-gray-900 whitespace-nowrap">Pharmacy</h1>
      </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 py-4 overflow-y-auto">
      
      <!-- Main Navigation Section -->
      <div class="px-0 mb-4">
        <!-- Dashboard -->
        <?php if ($isAdmin): ?>
        <div class="sb-item">
          <a href="<?php echo $adminBasePath; ?>Dashboard.php" class="flex items-center py-3 px-4 mx-2 <?php echo ($currentPage == 'Dashboard.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'; ?> transition-all duration-200 rounded-md group">
            <svg class="nav-item-icon <?php echo ($currentPage == 'Dashboard.php') ? 'text-blue-600' : 'text-gray-600 group-hover:text-gray-900'; ?> w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            <span id="nav-text" class="ml-3 font-medium transition-all duration-300 overflow-hidden whitespace-nowrap">Dashboard</span>
            <span class="tooltip">Dashboard</span>
          </a>
        </div>
        <?php endif; ?>

      <?php if ($isAdmin): ?>
        <!-- Users -->
        <div class="sb-item">
          <a href="<?php echo $adminBasePath; ?>user_management.php" class="flex items-center py-3 px-4 mx-2 <?php echo ($currentPage == 'user_management.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'; ?> transition-all duration-200 rounded-md group">
            <svg class="nav-item-icon <?php echo ($currentPage == 'user_management.php') ? 'text-blue-600' : 'text-gray-600 group-hover:text-gray-900'; ?> w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            <span id="nav-text" class="ml-3 font-medium transition-all duration-300 overflow-hidden whitespace-nowrap">Users</span>
            <span class="tooltip">Users</span>
          </a>
        </div>
      <?php endif; ?>

        <!-- Categories -->
        <div class="sb-item mb-0">
          <button onclick="toggleDropdown('categoryDropdown')" class="flex justify-between items-center py-3 px-4 mx-2 <?php echo (in_array($currentPage, ['categories.php', 'unitType.php', 'packageType.php'])) ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'; ?> transition-all duration-200 rounded-md group" style="width: 90%;">
            <div class="flex items-center">
              <svg class="nav-item-icon <?php echo (in_array($currentPage, ['categories.php', 'unitType.php', 'packageType.php'])) ? 'text-blue-600' : 'text-gray-600 group-hover:text-gray-900'; ?> w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
              </svg>
              <span id="nav-text" class="ml-3 font-medium transition-all duration-300 overflow-hidden whitespace-nowrap">Categories</span>
            </div>
            <svg id="categoryDropdownArrow" class="dropdown-arrow w-4 h-4 text-gray-400 transition-all duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
          <div id="categoryDropdown" class="ml-4 mt-1 space-y-1 transition-all duration-300 ease-in-out hidden">
            <a href="<?php echo $adminBasePath; ?>category_management/categories.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'categories.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Categories</span>
            </a>
            <a href="<?php echo $adminBasePath; ?>category_management/unitType.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'unitType.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Unit Types</span>
            </a>
            <a href="<?php echo $adminBasePath; ?>category_management/packageType.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'packageType.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Package Types</span>
            </a>
          </div>
          <div class="dropdown-tooltip">
            <a href="<?php echo $adminBasePath; ?>category_management/categories.php">🏷️ Categories</a>
            <a href="<?php echo $adminBasePath; ?>category_management/unitType.php">📏 Unit Types</a>
            <a href="<?php echo $adminBasePath; ?>category_management/packageType.php">📦 Package Types</a>
          </div>
        </div>

        <!-- Products -->
        <div class="sb-item mb-0">
          <button onclick="toggleDropdown('productDropdown')" class="flex justify-between items-center py-3 px-4 mx-2 <?php echo (in_array($currentPage, ['products.php', 'suppliers.php', 'batches.php', 'deliveries.php'])) ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'; ?> transition-all duration-200 rounded-md group" style="width: 90%;">
            <div class="flex items-center">
              <svg class="nav-item-icon <?php echo (in_array($currentPage, ['products.php', 'suppliers.php', 'batches.php', 'deliveries.php'])) ? 'text-blue-600' : 'text-gray-600 group-hover:text-gray-900'; ?> w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
              </svg>
              <span id="nav-text" class="ml-3 font-medium transition-all duration-300 overflow-hidden whitespace-nowrap">Products</span>
            </div>
            <svg id="productDropdownArrow" class="dropdown-arrow w-4 h-4 text-gray-400 transition-all duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
          <div id="productDropdown" class="ml-4 mt-1 space-y-1 transition-all duration-300 ease-in-out hidden">
          <a href="<?php echo $adminBasePath; ?>product_management/suppliers.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'suppliers.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Suppliers</span>
            </a> 
          
          <a href="<?php echo $adminBasePath; ?>product_management/products.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'products.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">All Products</span>
            </a>
      
       
            <a href="<?php echo $adminBasePath; ?>product_management/deliveries.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'deliveries.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Deliveries</span>
            </a>
      
          </div>
          <div class="dropdown-tooltip">
          <a href="<?php echo $adminBasePath; ?>product_management/suppliers.php">🏢 Suppliers</a>
            <a href="<?php echo $adminBasePath; ?>product_management/products.php">📦 Products</a>
            
            <a href="<?php echo $adminBasePath; ?>product_management/deliveries.php">🚚 Deliveries</a>
         
          </div>
        </div>

        <!-- Inventory -->
        <div class="sb-item mb-0">
          <button onclick="toggleDropdown('inventoryDropdown')" class="flex justify-between items-center py-3 px-4 mx-2 <?php echo (in_array($currentPage, ['storageInventory.php', 'pharmacyInventory.php'])) ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'; ?> transition-all duration-200 rounded-md group" style="width: 90%;">
            <div class="flex items-center">
              <svg class="nav-item-icon <?php echo (in_array($currentPage, ['storageInventory.php', 'pharmacyInventory.php'])) ? 'text-blue-600' : 'text-gray-600 group-hover:text-gray-900'; ?> w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
              </svg>
              <span id="nav-text" class="ml-3 font-medium transition-all duration-300 overflow-hidden whitespace-nowrap">Inventory</span>
            </div>
            <svg id="inventoryDropdownArrow" class="dropdown-arrow w-4 h-4 text-gray-400 transition-all duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
          <div id="inventoryDropdown" class="ml-4 mt-1 space-y-1 transition-all duration-300 ease-in-out hidden">
            <a href="<?php echo $adminBasePath; ?>inventory_management/storageInventory.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'storageInventory.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Storage Inventory</span>
            </a>
            <a href="<?php echo $adminBasePath; ?>inventory_management/pharmacyInventory.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'pharmacyInventory.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Pharmacy Inventory</span>
            </a>
          </div>
          <div class="dropdown-tooltip">
            <a href="<?php echo $adminBasePath; ?>inventory_management/storageInventory.php">📦 Storage Inventory</a>
            <a href="<?php echo $adminBasePath; ?>inventory_management/pharmacyInventory.php">💊 Pharmacy Inventory</a>
          </div>
        </div>

        <!-- Stock Management -->
        <div class="sb-item mb-0">
          <button onclick="toggleDropdown('stockDropdown')" class="flex justify-between items-center py-3 px-4 mx-2 <?php echo (in_array($currentPage, ['Stockcard.php', 'stock_MovementLog.php', 'stockTransfer.php'])) ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'; ?> transition-all duration-200 rounded-md group" style="width: 90%;">
            <div class="flex items-center">
              <svg class="nav-item-icon <?php echo (in_array($currentPage, ['Stockcard.php', 'stock_MovementLog.php', 'stockTransfer.php'])) ? 'text-blue-600' : 'text-gray-600 group-hover:text-gray-900'; ?> w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <span id="nav-text" class="ml-3 font-medium transition-all duration-300 overflow-hidden whitespace-nowrap">Stock Management</span>
            </div>
            <svg id="stockDropdownArrow" class="dropdown-arrow w-4 h-4 text-gray-400 transition-all duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
          <div id="stockDropdown" class="ml-4 mt-1 space-y-1 transition-all duration-300 ease-in-out hidden">
            <a href="<?php echo $adminBasePath; ?>stockcard_management/Stockcard.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'Stockcard.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Stock Cards</span>
            </a>
            <a href="<?php echo $adminBasePath; ?>stockcard_management/stockTransfer.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'stockTransfer.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Stock Transfer</span>
            </a>
            <?php if ($isAdmin): ?>
            <a href="<?php echo $adminBasePath; ?>stockcard_management/stock_MovementLog.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'stock_MovementLog.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Movement Log</span>
            </a>
            <?php endif; ?>
          </div>
          <div class="dropdown-tooltip">
            <a href="<?php echo $adminBasePath; ?>stockcard_management/Stockcard.php">📋 Stock Cards</a>
            <a href="<?php echo $adminBasePath; ?>stockcard_management/stockTransfer.php">🔄 Stock Transfer</a>
            <?php if ($isAdmin): ?>
            <a href="<?php echo $adminBasePath; ?>stockcard_management/stock_MovementLog.php">📊 Movement Log</a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Sales -->
        <?php if ($isAdmin): ?>
        <div class="sb-item mb-0">
          <button onclick="toggleDropdown('salesDropdown')" class="flex justify-between items-center py-3 px-4 mx-2 <?php echo ($currentPage == 'sales_transactions.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'; ?> transition-all duration-200 rounded-md group" style="width: 90%;">
            <div class="flex items-center">
              <svg class="nav-item-icon <?php echo ($currentPage == 'sales_transactions.php') ? 'text-blue-600' : 'text-gray-600 group-hover:text-gray-900'; ?> w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
              </svg>
              <span id="nav-text" class="ml-3 font-medium transition-all duration-300 overflow-hidden whitespace-nowrap">Sales</span>
            </div>
            <svg id="salesDropdownArrow" class="dropdown-arrow w-4 h-4 text-gray-400 transition-all duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
          <div id="salesDropdown" class="ml-4 mt-1 space-y-1 transition-all duration-300 ease-in-out hidden">
            <a href="<?php echo $adminBasePath; ?>sales_management/sales_transactions.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'sales_transactions.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Sales Transactions</span>
            </a>
            <a href="<?php echo $adminBasePath; ?>sales_management/sales_summary.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'sales_summary.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Sales Summary</span>
            </a>
          </div>
          <div class="dropdown-tooltip">
            <a href="<?php echo $adminBasePath; ?>sales_management/sales_transactions.php">🛒 Sales Transactions</a>
            <a href="<?php echo $adminBasePath; ?>sales_management/sales_summary.php">💵 Sales Summary</a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Reports & Analytics -->
        <div class="sb-item mb-0">
          <button onclick="toggleDropdown('reportsDropdown')" class="flex justify-between items-center py-3 px-4 mx-2 <?php echo (in_array($currentPage, ['stock_reports.php', 'sales_reports.php', 'expiry_reports.php', 'inventory_analytics.php'])) ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'; ?> transition-all duration-200 rounded-md group" style="width: 90%;">
            <div class="flex items-center">
              <svg class="nav-item-icon <?php echo (in_array($currentPage, ['stock_reports.php', 'sales_reports.php', 'expiry_reports.php', 'inventory_analytics.php'])) ? 'text-blue-600' : 'text-gray-600 group-hover:text-gray-900'; ?> w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
              <span id="nav-text" class="ml-3 font-medium transition-all duration-300 overflow-hidden whitespace-nowrap">Reports</span>
            </div>
            <svg id="reportsDropdownArrow" class="dropdown-arrow w-4 h-4 text-gray-400 transition-all duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
          <div id="reportsDropdown" class="ml-4 mt-1 space-y-1 transition-all duration-300 ease-in-out hidden">
            <a href="<?php echo $adminBasePath; ?>reports_analytics/stock_reports.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'stock_reports.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Stock Reports</span>
            </a>
            <a href="<?php echo $adminBasePath; ?>reports_analytics/sales_reports.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'sales_reports.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Sales Reports</span>
            </a>
            <a href="<?php echo $adminBasePath; ?>reports_analytics/expiry_reports.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'expiry_reports.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Expiry Reports</span>
            </a>
            <a href="<?php echo $adminBasePath; ?>reports_analytics/batch_inventory_report.php" class="flex items-center py-2 px-4 mx-2 text-sm <?php echo ($currentPage == 'batch_inventory_report.php') ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?> rounded-md">
              <span class="transition-all duration-300 overflow-hidden whitespace-nowrap">Batch Inventory Report</span>
            </a>
          </div>
          <div class="dropdown-tooltip">
            <a href="<?php echo $adminBasePath; ?>reports_analytics/stock_reports.php">📊 Stock Reports</a>
            <a href="<?php echo $adminBasePath; ?>reports_analytics/sales_reports.php">📈 Sales Reports</a>
            <a href="<?php echo $adminBasePath; ?>reports_analytics/expiry_reports.php">⏰ Expiry Reports</a>
            <a href="<?php echo $adminBasePath; ?>reports_analytics/batch_inventory_report.php">📦 Batch Inventory Report</a>
          </div>
        </div>
      </div>

    </nav>
  </aside>

  <!-- Main Content Area -->
  <main id="appMain" class="flex-1 overflow-y-auto bg-gray-50 h-screen lg:h-auto">
    <!-- Floating Header Bar -->
    <div class="sticky top-0 z-50 pt-2 px-4 pb-2 bg-gray-50 md:pt-2 md:px-4 md:pb-2 mobile-container">
      <header class="bg-white rounded-md shadow-lg border border-gray-100 max-w-full mx-auto">
        <div class="px-6 flex items-center justify-between">
        <!-- Left Side -->
        <div class="flex items-center space-x-4">
            <!-- Hamburger Menu Icon (Desktop: Blue, Mobile: with Logo & Close) -->
            <div class="flex items-center space-x-3">
              <!-- Hamburger Button -->
              <button id="hamburgerBtn" class="text-blue-600 hover:bg-blue-50 rounded-md p-2 transition-all duration-200" onclick="toggleSidebar()">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
              </button>
              
              <!-- Mobile Logo (visible only on mobile) - styled like the image -->
              <div class="flex items-center md:hidden">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-blue-500 flex items-center justify-center mr-2">
                  <span class="text-white font-bold text-sm">OCT</span>
                </div>
                <span class="text-gray-900 font-semibold text-lg">Pharmacy</span>
              </div>
            </div>
            
            <!-- Search Bar with Rounded Ends -->
            
          </div>

          <!-- Right Side -->
          <div class="flex items-center space-x-3">
            <!-- Desktop User Profile (hidden on mobile) -->
            <div class="relative hidden md:block">
              <button id="userDropdownBtn" class="flex items-center space-x-3 hover:bg-gray-50 px-4 py-2 rounded-md transition-all duration-200">
                <div class="text-right">
                  <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($userName); ?></p>
                  <p class="text-xs text-gray-500"><?php echo htmlspecialchars($userRole); ?></p>
                </div>
                <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-blue-200 bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                  <span class="text-white font-bold text-lg"><?php echo $userInitials; ?></span>
                </div>
                <svg id="dropdownArrow" class="w-4 h-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </button>
                
          
              <!-- User Dropdown Menu -->
              <div id="userDropdown" class="absolute top-full right-0 mt-2 bg-white rounded-md shadow-xl border border-gray-200 hidden overflow-hidden w-56 z-50">
                <div class="px-4 py-3 border-b border-gray-200">
                  <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($userName); ?></p>
          
                </div>
         
                <div class=" border-gray-200 my-2"></div>
                <a href="/pharmacy/Staff/posManage.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                  <svg class="w-4 h-4 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                  </svg>
                  POS (Cashier)
                </a>
                <a href="/pharmacy/auth/logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors">
                  <svg class="w-4 h-4 mr-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                  Logout
                </a>
              </div>
            </div>
            
            <!-- Mobile User Dropdown (visible only on mobile) -->
            <div class="relative md:hidden">
              <button id="userDropdownBtnMobile" class="flex items-center space-x-3 hover:bg-gray-50 px-4 py-2 rounded-md transition-all duration-200">
                <div class="text-right">
                  <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($userName); ?></p>
                  <p class="text-xs text-gray-500"><?php echo htmlspecialchars($userRole); ?></p>
                </div>
                <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-blue-200 bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                  <span class="text-white font-bold text-lg"><?php echo htmlspecialchars($userInitials); ?></span>
                </div>
                <svg id="dropdownArrowMobile" class="w-4 h-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </button>
              <div id="userDropdownMobile" class="absolute top-full right-0 mt-2 bg-white rounded-md shadow-xl border border-gray-200 hidden overflow-hidden w-56 z-50">
                <div class="px-4 py-3 border-b border-gray-200">
                  <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($userName); ?></p>
                  <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($userEmail); ?></p>
                </div>
                <a href="/pharmacy/Staff/posManage.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                  <svg class="w-4 h-4 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                  </svg>
                  POS (Cashier)
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
      </div>

    <!-- Main Content Starts Here -->
    
    <script>
    // Sidebar toggle with shrink functionality
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('overlay');
      const logoText = document.getElementById('logo-text');
      const navTexts = document.querySelectorAll('#nav-text, .font-medium.text-gray-700');
      const newBadges = document.querySelectorAll('.bg-red-500');
      const dropdownArrows = document.querySelectorAll('.dropdown-arrow');
      const salesDropdown = document.getElementById('salesDropdown');
      const reportsDropdown = document.getElementById('reportsDropdown');
      const sectionHeaders = document.querySelectorAll('.section-header');
      const isMobile = window.innerWidth < 768;

      if (isMobile) {
        const willShow = !sidebar.classList.contains('show');
        sidebar.classList.toggle('show');
        // Ensure transform toggles for reliability with Tailwind utilities
        if (willShow) {
          sidebar.classList.remove('-translate-x-full');
          sidebar.classList.add('translate-x-0');
        } else {
          sidebar.classList.add('-translate-x-full');
          sidebar.classList.remove('translate-x-0');
        }
        overlay.classList.toggle('hidden', !willShow ? true : false);
        updateHamburgerIcon(willShow);
        document.body.style.overflow = willShow ? 'hidden' : 'auto';
        return;
      }

      const isCollapsed = sidebar.classList.contains('collapsed');

      if (isCollapsed) {
        sidebar.style.width = '280px';
        sidebar.classList.remove('collapsed');

        setTimeout(() => {
          if (logoText) {
            logoText.style.width = 'auto';
            logoText.style.opacity = '1';
            logoText.style.display = 'block';
          }
        }, 150);

        navTexts.forEach((text, index) => {
          setTimeout(() => {
            text.style.width = 'auto';
            text.style.opacity = '1';
            text.style.display = 'inline-block';
          }, 150 + (index * 50));
        });

        setTimeout(() => {
          newBadges.forEach(badge => {
            badge.style.opacity = '1';
            badge.style.width = 'auto';
          });
        }, 200);

        setTimeout(() => {
          dropdownArrows.forEach(arrow => {
            arrow.style.opacity = '1';
            arrow.style.width = 'auto';
          });
        }, 250);

        setTimeout(() => {
          sectionHeaders.forEach(header => {
            header.style.opacity = '1';
            header.style.width = 'auto';
            header.style.display = 'block';
          });
        }, 200);
      } else {
        sidebar.style.width = '80px';
        sidebar.classList.add('collapsed');

        if (salesDropdown) {
          salesDropdown.style.opacity = '0';
          salesDropdown.style.height = '0';
        }
        if (reportsDropdown) {
          reportsDropdown.style.opacity = '0';
          reportsDropdown.style.height = '0';
        }

        dropdownArrows.forEach(arrow => {
          arrow.style.opacity = '0';
          arrow.style.width = '0';
        });

        newBadges.forEach(badge => {
          badge.style.opacity = '0';
          badge.style.width = '0';
        });

        sectionHeaders.forEach(header => {
          header.style.opacity = '0';
          header.style.width = '0';
          header.style.display = 'none';
        });

        navTexts.forEach((text, index) => {
          setTimeout(() => {
            text.style.width = '0';
            text.style.opacity = '0';
            text.style.display = 'none';
          }, index * 30);
        });

        setTimeout(() => {
          if (logoText) {
            logoText.style.width = '0';
            logoText.style.opacity = '0';
            logoText.style.display = 'none';
          }
        }, 100);
      }
    }

    function updateHamburgerIcon(isOpen) {
      const hamburgerBtn = document.getElementById('hamburgerBtn');
      if (!hamburgerBtn) return;
      hamburgerBtn.innerHTML = isOpen
        ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>'
        : '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>';
    }

    document.addEventListener('DOMContentLoaded', function() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('overlay');
      if (!sidebar) return;

      if (window.innerWidth >= 768) {
        sidebar.classList.remove('collapsed');
        sidebar.style.width = window.innerWidth >= 1024 ? '280px' : '250px';
        if (overlay) {
          overlay.classList.add('hidden');
        }
      } else {
        sidebar.style.width = '280px';
      }
    });

    // Dropdown toggle for sidebar navigation
    function toggleDropdown(dropdownId) {
      const dropdown = document.getElementById(dropdownId);
      const arrow = document.getElementById(dropdownId + 'Arrow');
      const allDropdowns = ['categoryDropdown', 'productDropdown', 'inventoryDropdown', 'stockDropdown', 'salesDropdown', 'reportsDropdown'];
      
      allDropdowns.forEach(id => {
        if (id !== dropdownId) {
          const d = document.getElementById(id);
          const a = document.getElementById(id + 'Arrow');
          if (d && a) {
            d.classList.add('hidden');
            a.classList.remove('rotated');
          }
        }
      });
      
      if (dropdown && arrow) {
        dropdown.classList.toggle('hidden');
        arrow.classList.toggle('rotated');
      }
    }

    // Auto-open dropdown if on a related page
    document.addEventListener('DOMContentLoaded', function() {
      const currentPage = '<?php echo $currentPage; ?>';
      
      // Auto-open sales dropdown if on sales page
      if (currentPage === 'salesManage.php') {
        const salesDropdown = document.getElementById('salesDropdown');
        const salesArrow = document.getElementById('salesDropdownArrow');
        if (salesDropdown && salesArrow) {
          salesDropdown.classList.remove('hidden');
          salesArrow.classList.add('rotated');
        }
      }
      
      // Auto-close sidebar on mobile when clicking navigation links
      const navLinks = document.querySelectorAll('#sidebar a[href]');
      navLinks.forEach(link => {
        link.addEventListener('click', function() {
          if (window.innerWidth < 768) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.remove('show');
            overlay.classList.add('hidden');
            document.body.style.overflow = 'auto';
            updateHamburgerIcon(false);
          }
        });
      });
      
      // Handle window resize - auto-hide sidebar when switching to desktop
      window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        if (window.innerWidth >= 768) {
          sidebar.classList.remove('show');
          overlay.classList.add('hidden');
          document.body.style.overflow = 'auto';
          updateHamburgerIcon(false);
        } else {
          // Keep mobile sidebar hidden by default on resize
          sidebar.classList.remove('show');
          sidebar.classList.add('-translate-x-full');
          sidebar.classList.remove('translate-x-0');
          overlay.classList.add('hidden');
          document.body.style.overflow = 'auto';
          updateHamburgerIcon(false);
        }
      });

      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('overlay');
      if (window.innerWidth < 768) {
        // Start hidden on mobile
        sidebar.classList.remove('show');
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
        updateHamburgerIcon(false);
      } else {
        sidebar.classList.remove('show');
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
        updateHamburgerIcon(false);
      }
    });

    // User dropdown in header
    document.addEventListener('DOMContentLoaded', function() {
      const btn = document.getElementById('userDropdownBtn');
      const menu = document.getElementById('userDropdown');
      const arrow = document.getElementById('dropdownArrow');
      
      if (btn && menu && arrow) {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          menu.classList.toggle('hidden');
          arrow.classList.toggle('rotate-180');
        });
        
        document.addEventListener('click', (e) => {
          if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.add('hidden');
            arrow.classList.remove('rotate-180');
          }
        });
        
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            menu.classList.add('hidden');
            arrow.classList.remove('rotate-180');
          }
        });
      }

      // Close navigation dropdowns when clicking outside
      document.addEventListener('click', function(e) {
        const dropdownIds = ['categoryDropdown', 'productDropdown', 'inventoryDropdown', 'stockDropdown', 'salesDropdown', 'reportsDropdown'];
        dropdownIds.forEach(dropdownId => {
          const dropdown = document.getElementById(dropdownId);
          const button = dropdown?.previousElementSibling;
          if (dropdown && button && !button.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
            const arrow = document.getElementById(dropdownId + 'Arrow');
            if (arrow) arrow.classList.remove('rotated');
          }
        });
      });
    });
    // Mobile user dropdown
    document.addEventListener('DOMContentLoaded', function() {
      const btnM = document.getElementById('userDropdownBtnMobile');
      const menuM = document.getElementById('userDropdownMobile');
      const arrowM = document.getElementById('dropdownArrowMobile');
      if (btnM && menuM && arrowM) {
        btnM.addEventListener('click', function(e) {
          e.stopPropagation();
          menuM.classList.toggle('hidden');
          arrowM.classList.toggle('rotate-180');
        });
        document.addEventListener('click', function(e) {
          if (!btnM.contains(e.target) && !menuM.contains(e.target)) {
            menuM.classList.add('hidden');
            arrowM.classList.remove('rotate-180');
          }
        });
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape') {
            menuM.classList.add('hidden');
            arrowM.classList.remove('rotate-180');
          }
        });
      }
    });
    </script>
