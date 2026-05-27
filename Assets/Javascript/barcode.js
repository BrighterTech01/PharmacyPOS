
// Physical Scanner Support: buffer fast keystrokes, finalize on Enter, show last scanned code
(function() {
  let scanBuffer = '';
  let scanTimer = null;
  const SCAN_TIMEOUT_MS = 300; // allow more time between chars to avoid splitting (00 issue)

  function isModalOpen(modalId) {
    const el = document.getElementById(modalId);
    return el && !el.classList.contains('hidden');
  }

  function setInputValue(inputId, value) {
    const input = document.getElementById(inputId);
    if (input) {
      input.value = value;
    }
  }

  function showLastScanOverlay(code) {
    let overlay = document.getElementById('lastScanOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'lastScanOverlay';
      overlay.className = 'fixed top-4 right-4 z-50 bg-black bg-opacity-80 text-white px-4 py-2 rounded-lg shadow-lg text-sm font-mono';
      document.body.appendChild(overlay);
    }
    overlay.textContent = 'Scanned: ' + code;
    overlay.style.opacity = '1';
    // Fade out after 2 seconds
    setTimeout(() => {
      if (overlay) overlay.style.opacity = '0.0';
    }, 2000);
  }

  function normalizeBarcode(code) {
    if (!code) return '';
    // Trim whitespace
    let out = code.trim();
    // Handle scanners that prepend 00 to EAN-13 → length 15
    if (out.length === 15 && out.startsWith('00')) {
      out = out.slice(2);
    }
    // Remove leading zeros if the entire code would otherwise be just zeros
    if (/^0+$/.test(out)) {
      return '';
    }
    // If starts with 0s and longer than 2, strip leading zeros to reduce to canonical form
    out = out.replace(/^0+(?!$)/, '');
    return out;
  }

  function handleScannerKeypress(e) {
    // Accumulate printable characters; finalize on Enter
    if (scanTimer) {
      clearTimeout(scanTimer);
    }

    if (e.key === 'Enter') {
      e.preventDefault();
      const raw = scanBuffer.trim();
      const code = normalizeBarcode(raw);
      // Ignore too-short or invalid codes (common ghost reads like "00")
      if (code && code.length >= 6) {
        // Show on-screen
        showLastScanOverlay(code);

        // If barcode modals are open (barcodes module)
        if (isModalOpen('addBarcodeModal')) {
          setInputValue('addBarcodeValue', code);
        } else if (isModalOpen('editBarcodeModal')) {
          setInputValue('editBarcodeValue', code);
        }

        // If product modals are open (products module)
        if (isModalOpen('addProductModal')) {
          setInputValue('addBarcode', code);
          const input = document.getElementById('addBarcode');
          if (input) { input.focus(); input.select(); }
        } else if (isModalOpen('editProductModal')) {
          setInputValue('editBarcode', code);
          const input = document.getElementById('editBarcode');
          if (input) { input.focus(); input.select(); }
        }
      }
      scanBuffer = '';
      return;
    }

    if (e.key && e.key.length === 1) {
      scanBuffer += e.key;
      // Clear buffer if typing pauses (likely not a scanner)
      scanTimer = setTimeout(() => {
        scanBuffer = '';
      }, SCAN_TIMEOUT_MS);
    }
  }

  // Attach global listener for physical scanners
  document.addEventListener('keypress', handleScannerKeypress);
})();

// Core modal + form logic (restored)
(function() {
const API_URL = '../../Controllers/manageBarcodeController.php';
let deleteBarcodeId = null;

document.addEventListener('DOMContentLoaded', function() {
  setupFormHandlers();
  initializeBatchesData();
});

function initializeBatchesData() {
  // Ensure batches data is available
  if (window.allBatches && Array.isArray(window.allBatches)) {
    // Batches data loaded successfully
  } else {
    // Batches data not available - this is normal if no batches exist
  }
}

function setupFormHandlers() {
    const addForm = document.getElementById('addBarcodeForm');
    if (addForm) {
      addForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const batchId = document.getElementById('addBatch').value;
    const data = {
      product_id: parseInt(document.getElementById('addProduct').value),
      batch_id: batchId ? parseInt(batchId) : null,
      barcode_value: document.getElementById('addBarcodeValue').value,
      unit_id: parseInt(document.getElementById('addUnit').value)
    };
    try {
      const response = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const result = await response.json();
      if (response.ok) {
        alert('Barcode added successfully');
        window.location.reload();
      } else {
        alert(result.error || 'Failed to add barcode');
      }
        } catch (err) {
          console.error('Error adding barcode:', err);
      alert('Failed to add barcode');
    }
  });
    }
  
    const editForm = document.getElementById('editBarcodeForm');
    if (editForm) {
      editForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const barcodeId = document.getElementById('editBarcodeId').value;
    const batchId = document.getElementById('editBatch').value;
    const data = {
      product_id: parseInt(document.getElementById('editProduct').value),
      batch_id: batchId ? parseInt(batchId) : null,
      barcode_value: document.getElementById('editBarcodeValue').value,
      unit_id: parseInt(document.getElementById('editUnit').value)
    };
    try {
      const response = await fetch(`${API_URL}?id=${barcodeId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const result = await response.json();
      if (response.ok) {
        alert('Barcode updated successfully');
        window.location.reload();
      } else {
        alert(result.error || 'Failed to update barcode');
      }
        } catch (err) {
          console.error('Error updating barcode:', err);
      alert('Failed to update barcode');
    }
  });
}
  }

  // Expose helper functions globally (used by onclick in HTML)
  window.updateBatchesDropdown = function(modalType) {
    const productSelect = document.getElementById(modalType + 'Product');
    const batchSelect = document.getElementById(modalType + 'Batch');
    const selectedProductId = parseInt(productSelect.value);
    
    batchSelect.innerHTML = '<option value="">Select Batch</option>';
    
    // Check if batches data is available
    if (!window.allBatches || !Array.isArray(window.allBatches)) {
      return;
    }
    
    if (selectedProductId) {
      const productBatches = window.allBatches.filter(b => b.product_id == selectedProductId);
      productBatches.forEach(batch => {
        const option = document.createElement('option');
        option.value = batch.batch_id;
        option.textContent = batch.batch_no;
        batchSelect.appendChild(option);
      });
    }
  };

  window.filterBarcodes = function() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const table = document.getElementById('barcodesTableBody');
    const rows = table.getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      const barcodeValue = row.cells[1]?.textContent.toLowerCase() || '';
      const productName = row.cells[2]?.textContent.toLowerCase() || '';
      row.style.display = (barcodeValue.includes(searchInput) || productName.includes(searchInput)) ? '' : 'none';
    }
  };

  window.openAddBarcodeModal = function() {
    const modal = document.getElementById('addBarcodeModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const form = document.getElementById('addBarcodeForm');
    if (form) form.reset();
    setTimeout(() => {
      const input = document.getElementById('addBarcodeValue');
      if (input) input.focus();
    }, 100);
  };

  window.closeAddBarcodeModal = function() {
    const modal = document.getElementById('addBarcodeModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  };

  window.openEditBarcodeModal = function(barcode) {
  document.getElementById('editBarcodeId').value = barcode.barcode_id;
  document.getElementById('editBarcodeValue').value = barcode.barcode_value;
  document.getElementById('editProduct').value = barcode.product_id;
  document.getElementById('editUnit').value = barcode.unit_id;
    window.updateBatchesDropdown('edit');
  setTimeout(() => {
    document.getElementById('editBatch').value = barcode.batch_id || '';
  }, 100);
    const modal = document.getElementById('editBarcodeModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
      const input = document.getElementById('editBarcodeValue');
      if (input) { input.focus(); input.select(); }
    }, 150);
  };

  window.closeEditBarcodeModal = function() {
    const modal = document.getElementById('editBarcodeModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  };

  window.openDeleteBarcodeModal = function(barcodeId, barcodeValue) {
  deleteBarcodeId = barcodeId;
  document.getElementById('deleteBarcodeName').textContent = barcodeValue;
    const modal = document.getElementById('deleteBarcodeModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  };

  window.closeDeleteBarcodeModal = function() {
  deleteBarcodeId = null;
    const modal = document.getElementById('deleteBarcodeModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  };

  window.confirmDeleteBarcode = async function() {
  if (!deleteBarcodeId) return;
  try {
      const response = await fetch(`${API_URL}?id=${deleteBarcodeId}`, { method: 'DELETE' });
    const result = await response.json();
    if (response.ok) {
      alert('Barcode deleted successfully');
      window.location.reload();
    } else {
      alert(result.error || 'Failed to delete barcode');
    }
    } catch (err) {
      console.error('Error deleting barcode:', err);
    alert('Failed to delete barcode');
  }
  };
})();
