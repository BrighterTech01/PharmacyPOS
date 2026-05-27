// Simple JavaScript for filtering and modal handling (no AJAX)

let deleteUserId = null;
let currentFilter = 'all';

// Load on page ready
document.addEventListener('DOMContentLoaded', function() {
    // Nothing needed here - forms submit traditionally
});

// Quick Filter Users by Role
function quickFilterUsers(filterType) {
    currentFilter = filterType;
    const table = document.getElementById('usersTableBody');
    const rows = table.getElementsByTagName('tr');
    
    // Update button states
    document.querySelectorAll('.quick-filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (filterType === 'all') {
        document.getElementById('filterAll').classList.add('active');
    } else if (filterType === 'Admin') {
        document.getElementById('filterAdmin').classList.add('active');
    } else if (filterType === 'Cashier') {
        document.getElementById('filterCashier').classList.add('active');
    }
    
    // Filter table rows (role is in column index 5)
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const role = row.cells[5]?.textContent.trim() || '';
        
        if (filterType === 'all') {
            row.style.display = '';
        } else {
            if (role === filterType) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }
    
    // Clear search input when using quick filter
    document.getElementById('searchInput').value = '';
}

// Filter Users Function (Search)
function filterUsers() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const table = document.getElementById('usersTableBody');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const name = row.cells[1]?.textContent.toLowerCase() || '';
        const contact = row.cells[2]?.textContent.toLowerCase() || '';
        const username = row.cells[3]?.textContent.toLowerCase() || '';
        const address = row.cells[4]?.textContent.toLowerCase() || '';
        const role = row.cells[5]?.textContent.toLowerCase() || '';
        const status = row.cells[6]?.textContent.toLowerCase() || '';
        
        // Apply both search filter and quick filter
        const matchesSearch = name.includes(searchInput) || contact.includes(searchInput) || 
                              username.includes(searchInput) || address.includes(searchInput) || 
                              role.includes(searchInput) || status.includes(searchInput);
        const roleText = row.cells[5]?.textContent.trim() || '';
        const matchesQuickFilter = currentFilter === 'all' || roleText === currentFilter;
        
        if (matchesSearch && matchesQuickFilter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

// Modal Functions
function openAddUserModal() {
    document.getElementById('addUserModal').classList.remove('hidden');
    document.getElementById('addUserModal').classList.add('flex');
    document.getElementById('addUserForm').reset();
}

function closeAddUserModal() {
    document.getElementById('addUserModal').classList.add('hidden');
    document.getElementById('addUserModal').classList.remove('flex');
}

function openEditUserModal(userId) {
    // Get user data from the row's data attributes
    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (row) {
        // Populate form fields from row data attributes
        document.getElementById('editUserId').value = userId;
        document.getElementById('editFirstname').value = row.getAttribute('data-firstname') || '';
        document.getElementById('editLastname').value = row.getAttribute('data-lastname') || '';
        document.getElementById('editContact').value = row.getAttribute('data-contact') || '';
        document.getElementById('editUsername').value = row.getAttribute('data-username') || '';
        document.getElementById('editAddress').value = row.getAttribute('data-address') || '';
        document.getElementById('editRole').value = row.getAttribute('data-role') || 'Cashier';
        document.getElementById('editStatus').value = row.getAttribute('data-status') || 'Inactive';
        document.getElementById('editPassword').value = ''; // Clear password field
        
        document.getElementById('editUserModal').classList.remove('hidden');
        document.getElementById('editUserModal').classList.add('flex');
    } else {
        alert('User data not found');
    }
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
    document.getElementById('editUserModal').classList.remove('flex');
}

function openDeleteUserModal(userId, userName) {
    deleteUserId = userId;
    const deleteNameElement = document.getElementById('deleteUserName');
    if (deleteNameElement) {
        deleteNameElement.textContent = userName || 'this user';
    }
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserModal').classList.remove('hidden');
    document.getElementById('deleteUserModal').classList.add('flex');
}

function closeDeleteUserModal() {
    deleteUserId = null;
    document.getElementById('deleteUserModal').classList.add('hidden');
    document.getElementById('deleteUserModal').classList.remove('flex');
}

// Remove old confirmDeleteUser function since we're using form submission now

// Expose functions to global scope for onclick handlers
window.openAddUserModal = openAddUserModal;
window.closeAddUserModal = closeAddUserModal;
window.openEditUserModal = openEditUserModal;
window.closeEditUserModal = closeEditUserModal;
window.openDeleteUserModal = openDeleteUserModal;
window.closeDeleteUserModal = closeDeleteUserModal;
window.quickFilterUsers = quickFilterUsers;
window.filterUsers = filterUsers;

