<!-- Add User Modal -->
<div id="addUserModal" class="fixed inset-0 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Add New User</h3>
    </div>
    <form id="addUserForm" method="POST" action="../Controllers/userManageController.php" class="p-6">
      <input type="hidden" name="add_user" value="1">
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
            <input type="text" name="firstname" id="addFirstname" required autocomplete="given-name" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
            <input type="text" name="lastname" id="addLastname" required autocomplete="family-name" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Contact</label>
          <input type="number" name="contact" id="addContact" autocomplete="tel" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors" placeholder="Enter contact number">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
          <input type="text" name="username" id="addUsername" required autocomplete="username" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
          <input type="text" name="address" id="addAddress" autocomplete="street-address" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
          <input type="password" name="password" id="addPassword" required minlength="6" autocomplete="new-password" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
          <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Role <span class="text-red-500">*</span></label>
          <select name="role" id="addRole" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
            <option value="">Select Role</option>
            <option value="Admin">Admin</option>
            <option value="Cashier">Cashier</option>
          </select>
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" class="flex-1 bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition-colors font-semibold">
          Add User
        </button>
        <button type="button" onclick="closeAddUserModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Edit User</h3>
    </div>
    <form id="editUserForm" method="POST" action="../Controllers/userManageController.php" class="p-6">
      <input type="hidden" name="update_user" value="1">
      <input type="hidden" name="user_id" id="editUserId">
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
            <input type="text" name="firstname" id="editFirstname" required autocomplete="given-name" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
            <input type="text" name="lastname" id="editLastname" required autocomplete="family-name" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Contact</label>
          <input type="number" name="contact" id="editContact" autocomplete="tel" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors" placeholder="Enter contact number">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
          <input type="text" name="username" id="editUsername" required autocomplete="username" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
          <input type="text" name="address" id="editAddress" autocomplete="street-address" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">New Password (leave blank to keep current)</label>
          <input type="password" name="password" id="editPassword" minlength="6" autocomplete="new-password" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Role <span class="text-red-500">*</span></label>
            <select name="role" id="editRole" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
              <option value="Admin">Admin</option>
              <option value="Cashier">Cashier</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
            <select name="status" id="editStatus" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 transition-colors font-semibold">
          Update User
        </button>
        <button type="button" onclick="closeEditUserModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteUserModal" class="fixed inset-0 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Delete User</h3>
    </div>
    <form id="deleteUserForm" method="POST" action="../Controllers/userManageController.php" class="p-6">
      <input type="hidden" name="delete_user" value="1">
      <input type="hidden" name="user_id" id="deleteUserId">
      <p class="text-gray-700 mb-6">Are you sure you want to delete <strong id="deleteUserName"></strong>? This action cannot be undone.</p>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 transition-colors font-semibold">
          Delete
        </button>
        <button type="button" onclick="closeDeleteUserModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>
