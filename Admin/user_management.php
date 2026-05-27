<?php 
// Start session if not started
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Check if user is logged in and is admin
$isAdmin = false;
$currentUsername = '';
if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
elseif (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }

// Pharmacy account always has admin access
$isPharmacyAccount = (strtolower($currentUsername) === 'pharmacy');

if (!empty($_SESSION['user']['role'])) { 
    $isAdmin = strtolower($_SESSION['user']['role']) === 'admin'; 
} elseif (!empty($_SESSION['role'])) { 
    $isAdmin = strtolower($_SESSION['role']) === 'admin'; 
}

// Pharmacy account always has full access
$isAdmin = $isPharmacyAccount || $isAdmin;

if (!$isAdmin) {
    header('Location: ../auth/login.php?error=unauthorized');
    exit();
}

// Include database connection
require_once '../dbConnection/connection.php';

// Fetch all users from database
$query = "SELECT user_id, firstname, lastname, contact, username, address, role, status, created_at FROM users ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
$users = [];

// Check if query was successful and has results
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

// Calculate statistics
$totalUsers = count($users);
$adminUsers = count(array_filter($users, function($u) { return $u['role'] === 'Admin'; }));
$cashierUsers = count(array_filter($users, function($u) { return $u['role'] === 'Cashier'; }));

include '../includes/adminHeader.php'; 
?>
<!-- Include Modal Handler -->
<?php include '../Modal/modalAlertHandling.php'; ?>
<?php
?>

<!-- User Management Content -->
  <div class="relative p-6 space-y-6 isolate">

  <!-- Quick Filter Buttons -->
  <div class="mb-6 flex items-center gap-3">
    <div class="flex items-center">
      <svg class="w-5 h-5 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
      </svg>
      <span class="text-sm font-semibold text-gray-700">Quick Filter:</span>
    </div>
    <button onclick="quickFilterUsers('all')" id="filterAll" class="quick-filter-btn active px-4 py-2 rounded-lg font-semibold text-sm transition-all">
      All Users
    </button>
    <button onclick="quickFilterUsers('Admin')" id="filterAdmin" class="quick-filter-btn px-4 py-2 rounded-lg font-semibold text-sm transition-all">
      <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
      </svg>
      Admin Only
    </button>
    <button onclick="quickFilterUsers('Cashier')" id="filterCashier" class="quick-filter-btn px-4 py-2 rounded-lg font-semibold text-sm transition-all">
      <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
      </svg>
      Cashier Only
    </button>
  </div>

  <!-- Users Table -->
  <div class="relative bg-white rounded-2xl shadow-lg p-6 border-2 border-indigo-100 hover:border-indigo-300 transition-all duration-300 hover:shadow-xl overflow-hidden group">
    <div class="absolute top-0 right-0 w-40 h-40 bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-full -mr-20 -mt-20 opacity-50"></div>
        <div class="relative z-10">
      <!-- Header Row -->
          <div class="flex items-center justify-between mb-6">
            <div>
              <div class="flex items-center mb-2">
            <div class="w-12 h-12 bg-gradient-to-br from-indigo-400 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg mr-3 group-hover:scale-110 transition-transform">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                  </svg>
                </div>
            <h3 class="text-xl font-bold text-gray-900">Users List</h3>
              </div>
          <p class="text-sm text-gray-600">Manage system users and roles</p>
            </div>
        <button onclick="openAddUserModal()" class="flex items-center px-4 py-2 bg-indigo-500 text-white text-sm font-semibold rounded-lg hover:bg-indigo-600 transition-colors shadow-md hover:shadow-lg">
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
          Add User
        </button>
        </div>

      <!-- Search Bar Row -->
      <div class="mb-4">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                  </svg>
                </div>
          <input 
            type="text" 
            id="searchInput" 
            placeholder="Search by name, contact, username, address, role, or status..." 
            class="w-full pl-10 pr-4 py-3 border-2 border-indigo-200 rounded-lg focus:border-indigo-500 focus:outline-none transition-colors"
            onkeyup="filterUsers()"
          >
            </div>
          </div>
        </div>

    <div class="overflow-x-auto">
      <style>
        .users-table-scroll {
          max-height: 640px; /* Approximately 10 table rows */
          overflow-y: auto;
        }
        .users-table-scroll::-webkit-scrollbar {
          width: 8px;
        }
        .users-table-scroll::-webkit-scrollbar-track {
          background: #f1f1f1;
          border-radius: 10px;
        }
        .users-table-scroll::-webkit-scrollbar-thumb {
          background: #888;
          border-radius: 10px;
        }
        .users-table-scroll::-webkit-scrollbar-thumb:hover {
          background: #555;
        }
      </style>
      <div class="users-table-scroll">
      <table class="w-full">
        <thead class="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-indigo-200">
          <tr>
            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">User ID</th>
            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Name</th>
            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Contact</th>
            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Username</th>
            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Address</th>
            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Role</th>
            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Status</th>
            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-700">Created At</th>
            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-gray-700">Actions</th>
          </tr>
        </thead>
        <tbody id="usersTableBody" class="bg-white divide-y divide-gray-200">
          <?php if (count($users) > 0): ?>
            <?php foreach ($users as $user): ?>
              <?php 
                $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
                $initials = strtoupper(substr($user['firstname'] ?? $user['username'], 0, 1) . substr($user['lastname'] ?? '', 0, 1));
                if (empty($initials)) {
                  $initials = strtoupper(substr($user['username'], 0, 1));
                }
              ?>
              <tr class="hover:bg-gray-50 transition-colors" 
                  data-user-id="<?php echo $user['user_id']; ?>"
                  data-firstname="<?php echo htmlspecialchars($user['firstname'] ?? ''); ?>"
                  data-lastname="<?php echo htmlspecialchars($user['lastname'] ?? ''); ?>"
                  data-contact="<?php echo htmlspecialchars($user['contact'] ?? ''); ?>"
                  data-username="<?php echo htmlspecialchars($user['username']); ?>"
                  data-address="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                  data-role="<?php echo htmlspecialchars($user['role']); ?>"
                  data-status="<?php echo htmlspecialchars($user['status'] ?? 'Inactive'); ?>">
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?php echo $user['role'] === 'Admin' ? 'bg-purple-100 text-purple-800' : 'bg-pink-100 text-pink-800'; ?>">
                    #<?php echo $user['user_id']; ?>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full <?php echo $user['role'] === 'Admin' ? 'bg-purple-500' : 'bg-pink-500'; ?> flex items-center justify-center text-white font-bold mr-3">
                      <?php echo $initials; ?>
                    </div>
                    <div>
                      <span class="font-semibold text-gray-900 block"><?php echo htmlspecialchars($fullName ?: $user['username']); ?></span>
                      <?php if (!empty($fullName)): ?>
                        <span class="text-xs text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  <?php echo !empty($user['contact']) ? htmlspecialchars($user['contact']) : 'N/A'; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                  <?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?php echo $user['role'] === 'Admin' ? 'bg-gradient-to-r from-purple-500 to-purple-600 text-white' : 'bg-gradient-to-r from-pink-500 to-pink-600 text-white'; ?>">
                    <?php echo $user['role']; ?>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?php echo $user['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                    <?php echo $user['status']; ?>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                  <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <button onclick="openEditUserModal(<?php echo $user['user_id']; ?>)" class="inline-flex items-center px-3 py-1 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors mr-2">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                  </button>
                  <?php 
                    $displayName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
                    if (empty($displayName)) {
                        $displayName = $user['username'];
                    }
                  ?>
                  <button onclick="openDeleteUserModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($displayName, ENT_QUOTES); ?>')" class="inline-flex items-center px-3 py-1 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                <div class="flex flex-col items-center">
                  <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                  <p class="font-semibold">No users found</p>
              </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>

<!-- Add User Modal -->
<?php include '../Modal/modalUser.php'; ?>
      </div>

</main>

</div> <!-- Close flex container -->

<style>
.quick-filter-btn {
  border: 2px solid #e5e7eb;
  background-color: white;
  color: #6b7280;
  text-decoration: none;
}

.quick-filter-btn:hover {
  border-color: #c7d2fe;
  background-color: #f5f3ff;
  color: #4f46e5;
  transform: translateY(-2px);
  box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
}

.quick-filter-btn.active {
  background: linear-gradient(to right, #6366f1, #8b5cf6);
  color: white;
  border-color: transparent;
  box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.4);
}

.quick-filter-btn.active:hover {
  background: linear-gradient(to right, #4f46e5, #7c3aed);
  transform: translateY(-2px);
}
</style>

<script src="../Assets/Javascript/errorAndsuccessHander.js"></script>
<script src="../Assets/Javascript/user.js?v=20251104"></script>
<script>
// Initialize modal handlers
const successMessages = {
    'user_added': 'User added successfully!',
    'user_updated': 'User updated successfully!',
    'user_deleted': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const name = urlParams.get('name') || 'User';
        return "User '" + name + "' deleted successfully!";
    },
    'user_deactivated': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const name = urlParams.get('name') || 'User';
        return "User '" + name + "' was deactivated (has transactions).";
    }
};

const errorMessages = {
    'username_exists': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const username = urlParams.get('username') || '';
        return "Username '" + username + "' already exists. Please choose a different username.";
    },
    'validation': function() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('msg') || 'Validation error occurred.';
    },
    'add_failed': function() {
        const urlParams = new URLSearchParams(window.location.search);
        return 'Failed to add user. ' + (urlParams.get('msg') || '');
    },
    'update_failed': function() {
        const urlParams = new URLSearchParams(window.location.search);
        return 'Failed to update user. ' + (urlParams.get('msg') || '');
    },
    'delete_failed': function() {
        const urlParams = new URLSearchParams(window.location.search);
        return 'Failed to delete user. ' + (urlParams.get('msg') || '');
    },
    'cannot_delete_master': 'Cannot delete the master admin account.',
    'user_not_found': 'User not found.',
    'invalid_id': 'Invalid user ID.',
    'unauthorized': 'You are not authorized to perform this action.'
};

initModalHandlers(successMessages, errorMessages);

// Handle user_deleted success with name parameter
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('success') === 'user_deleted') {
    const name = urlParams.get('name') || 'User';
    showSuccessModal('Success', "User '" + name + "' deleted successfully!");
}

// Handle user_deactivated success with name parameter
if (urlParams.get('success') === 'user_deactivated') {
    const name = urlParams.get('name') || 'User';
    showSuccessModal('Success', "User '" + name + "' was deactivated because they have sales/transactions.");
}

// Handle username_exists error with username parameter
if (urlParams.get('error') === 'username_exists') {
    const username = urlParams.get('username') || '';
    showErrorModal('Error', "Username '" + username + "' already exists. Please choose a different username.");
}

// Handle validation, add_failed, update_failed, delete_failed errors with msg parameter
const errorTypes = ['validation', 'add_failed', 'update_failed', 'delete_failed'];
errorTypes.forEach(errorType => {
    if (urlParams.get('error') === errorType) {
        const msg = urlParams.get('msg') || '';
        let message = '';
        switch(errorType) {
            case 'validation':
                message = msg || 'Validation error occurred.';
                break;
            case 'add_failed':
                message = 'Failed to add user. ' + msg;
                break;
            case 'update_failed':
                message = 'Failed to update user. ' + msg;
                break;
            case 'delete_failed':
                message = 'Failed to delete user. ' + msg;
                break;
        }
        showErrorModal('Error', message);
    }
});
</script>
</body>
</html>
