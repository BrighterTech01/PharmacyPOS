/**
 * QZ Tray Integration for Automatic Receipt Printing
 * 
 * Setup Instructions:
 * 1. Download and install QZ Tray from https://qz.io/download/
 * 2. Start QZ Tray on the POS computer
 * 3. Open QZ Tray and allow your website (localhost/pharmacy) in the certificate settings
 * 4. Ensure your POS58 printer is installed and set as default or named "POS58 Printer"
 * 
 * This script handles:
 * - Connecting to QZ Tray
 * - Generating ESC/POS commands for thermal receipts
 * - Sending raw print data to the printer silently
 */

// QZ Tray connection state
let qzConnected = false;
let qzConnecting = false;

/**
 * Initialize QZ Tray connection
 */
async function initQZTray() {
    if (qzConnecting) {
        return false;
    }
    
    if (qzConnected) {
        return true;
    }
    
    // Check if QZ Tray library is loaded
    if (typeof qz === 'undefined') {
        console.warn('QZ Tray library not loaded. Please ensure QZ Tray is installed and running.');
        return false;
    }
    
    // Store original console.warn outside try block for error handling
    let originalWarn = console.warn;
    let certificateWarningSuppressed = false;
    
    try {
        qzConnecting = true;
        
        // Suppress certificate warnings (they're often non-critical when connection succeeds)
        console.warn = function(...args) {
            // Suppress "Failed to get certificate" warnings - they're often false positives
            if (args.length > 0 && args[0] && typeof args[0] === 'string' && 
                (args[0].includes('certificate') || args[0].includes('Failed to get certificate'))) {
                certificateWarningSuppressed = true;
                // Don't log certificate warnings - connection can still succeed
                return;
            }
            originalWarn.apply(console, args);
        };
        
        // Check if QZ Tray is running
        if (!qz.websocket.isActive()) {
            try {
                await qz.websocket.connect();
            } catch (connectError) {
                // Restore original console.warn
                console.warn = originalWarn;
                
                // Handle certificate/trust issues
                if (connectError.message && (connectError.message.includes('certificate') || 
                    connectError.message.includes('Request blocked'))) {
                    console.warn('QZ Tray connection blocked. Please allow this website in QZ Tray certificate settings.');
                    throw new Error('QZ_TRAY_NOT_ALLOWED');
                }
                throw connectError;
            }
        }
        
        // Restore original console.warn after successful connection
        console.warn = originalWarn;
        
        // If we suppressed a certificate warning but connection succeeded, it's fine
        if (certificateWarningSuppressed) {
            console.log('QZ Tray connected successfully (certificate warning was non-critical)');
        } else {
            console.log('QZ Tray connected successfully');
        }
        
        qzConnected = true;
        qzConnecting = false;
        return true;
        
    } catch (error) {
        // Restore console.warn in case of error
        console.warn = originalWarn;
        
        qzConnecting = false;
        qzConnected = false;
        
        if (error.message === 'QZ_TRAY_NOT_ALLOWED') {
            console.warn('QZ Tray requires certificate approval. Please allow this site in QZ Tray settings.');
        } else {
            console.error('QZ Tray connection failed:', error);
        }
        return false;
    }
}

/**
 * Generate ESC/POS commands for receipt
 * @param {Object} receiptData - Receipt data from getReceiptData.php
 * @returns {Array} Array of ESC/POS command bytes/strings
 */
function generateESCPOSCommands(receiptData) {
    const commands = [];
    
    // ESC/POS command constants
    const ESC = '\x1B';
    const GS = '\x1D';
    const INIT = ESC + '@';           // Initialize printer
    const CENTER = ESC + 'a' + '\x01'; // Center align
    const LEFT = ESC + 'a' + '\x00';   // Left align
    const BOLD_ON = ESC + 'E' + '\x01'; // Bold on
    const BOLD_OFF = ESC + 'E' + '\x00'; // Bold off
    const DOUBLE_HEIGHT = GS + '!' + '\x10'; // Double height
    const NORMAL_SIZE = GS + '!' + '\x00';   // Normal size
    const CUT = GS + 'V' + '\x41' + '\x03';  // Partial cut
    
    // Initialize printer
    commands.push(INIT);
    
    // Store name and header (centered, double height, bold)
    commands.push(CENTER);
    commands.push(DOUBLE_HEIGHT);
    commands.push(BOLD_ON);
    commands.push('OCT PHARMACY\n');
    commands.push(BOLD_OFF);
    commands.push(NORMAL_SIZE);
    commands.push('--------------------------------\n');
    
    // Receipt info (left aligned)
    commands.push(LEFT);
    commands.push('Receipt #: ' + receiptData.receipt_number + '\n');
    commands.push('Date: ' + receiptData.date + ' ' + receiptData.time + '\n');
    commands.push('Cashier: ' + receiptData.cashier + '\n');
    commands.push('--------------------------------\n');
    
    // Items header - Improved layout for 48mm thermal printer
    commands.push(BOLD_ON);
    commands.push('# Item     Qty Price Total\n');
    commands.push(BOLD_OFF);
    commands.push('--------------------------------\n');
    
    // Items - Limit product names to fit 48mm width (very short names like "amox..")
    receiptData.items.forEach((item, index) => {
        const itemNum = (index + 1) + '.';
        // Limit brand name to 8 characters (6 chars + "..") for better fit on 48mm paper
        // Example: "amoxicillin" becomes "amox.."
        let brandName = item.brand_name || 'Item';
        if (brandName.length > 8) {
            brandName = brandName.substring(0, 6) + '..';
        }
        brandName = brandName.padEnd(8);
        
        const qty = String(item.quantity).padStart(3);
        const price = 'P' + parseFloat(item.price).toFixed(2).padStart(5);
        const total = 'P' + parseFloat(item.subtotal).toFixed(2).padStart(6);
        
        commands.push(itemNum + ' ' + brandName + ' ' + qty + ' ' + price + ' ' + total + '\n');
        
        // Generic name if different from brand (limit to 14 chars, show only if short)
        if (item.generic_name && item.generic_name.toLowerCase() !== item.brand_name.toLowerCase()) {
            let generic = item.generic_name;
            if (generic.length > 14) {
                generic = generic.substring(0, 12) + '..';
            }
            // Only show generic if it's not too long
            if (generic.length <= 20) {
                commands.push('   ' + generic + '\n');
            }
        }
    });
    
    commands.push('--------------------------------\n');
    
    // Totals - Improved layout
    commands.push('--------------------------------\n');
    const subtotalLabel = 'Subtotal:';
    const subtotalValue = 'P' + parseFloat(receiptData.subtotal).toFixed(2);
    commands.push(subtotalLabel.padEnd(20) + subtotalValue.padStart(12) + '\n');
    
    if (receiptData.discount_type && receiptData.discount_type !== 'None' && receiptData.discount_amount > 0) {
        const discountLabel = receiptData.discount_type + ' Disc:';
        const discountValue = '-P' + parseFloat(receiptData.discount_amount).toFixed(2);
        commands.push(discountLabel.padEnd(20) + discountValue.padStart(12) + '\n');
    }
    
    commands.push('--------------------------------\n');
    commands.push(BOLD_ON);
    const totalLabel = 'TOTAL:';
    const totalValue = 'P' + parseFloat(receiptData.total).toFixed(2);
    commands.push(totalLabel.padEnd(20) + totalValue.padStart(12) + '\n');
    commands.push(BOLD_OFF);
    
    commands.push('--------------------------------\n');
    commands.push('Payment: ' + (receiptData.payment_method || 'Cash') + '\n');
    // Always display customer name (limit to 20 chars for 48mm paper)
    // Handle empty, null, or undefined customer_name
    let customerName = 'Walk-in Customer';
    if (receiptData.customer_name && typeof receiptData.customer_name === 'string' && receiptData.customer_name.trim() !== '') {
        customerName = receiptData.customer_name.trim();
    }
    // Truncate if too long for 48mm paper
    if (customerName.length > 20) {
        commands.push('Customer: ' + customerName.substring(0, 18) + '..\n');
    } else {
        commands.push('Customer: ' + customerName + '\n');
    }
    if (receiptData.cash_received !== null && receiptData.cash_received !== undefined) {
        commands.push('Cash: P' + parseFloat(receiptData.cash_received).toFixed(2) + '\n');
    }
    commands.push('--------------------------------\n');
    // Always display change amount prominently (even if 0)
    commands.push(BOLD_ON);
    const changeLabel = 'CHANGE:';
    if (receiptData.change_amount !== null && receiptData.change_amount !== undefined) {
        const changeValue = 'P' + parseFloat(receiptData.change_amount).toFixed(2);
        commands.push(changeLabel.padEnd(20) + changeValue.padStart(12) + '\n');
    } else {
        const changeValue = 'P0.00';
        commands.push(changeLabel.padEnd(20) + changeValue.padStart(12) + '\n');
    }
    commands.push(BOLD_OFF);
    commands.push('--------------------------------\n');
    
    // Footer
    commands.push(CENTER);
    commands.push('Thank you for your purchase!\n');
    commands.push('Please come again!\n');
    commands.push('\n\n');
    
    // Cut paper
    commands.push(CUT);
    
    return commands;
}

/**
 * Print receipt using QZ Tray
 * @param {number} saleId - Sale ID to print
 * @returns {Promise<Object>} Result object with success/error
 */
async function printReceiptWithQZTray(saleId) {
    try {
        // Initialize QZ Tray connection
        const connected = await initQZTray();
        if (!connected) {
            // Provide specific error message
            const errorMsg = 'QZ Tray connection failed. Please:\n' +
                           '1. Ensure QZ Tray is installed and running\n' +
                           '2. Allow this website in QZ Tray certificate settings\n' +
                           '3. Right-click QZ Tray icon → Certificate → Allow this site';
            throw new Error(errorMsg);
        }
        
        // Fetch receipt data
        const response = await fetch(`../Controllers/getReceiptData.php?sale_id=${saleId}`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Failed to fetch receipt data`);
        }
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to get receipt data');
        }
        
        // Generate ESC/POS commands
        const escposCommands = generateESCPOSCommands(data.receipt);
        
        // Get printer name (try to detect POS58 printer or use default)
        let printerName = 'POS58 Printer'; // Default printer name
        
        // Try to get default printer or detect POS58
        try {
            const printers = await qz.printers.find();
            if (printers && printers.length > 0) {
                const pos58Printer = printers.find(p => 
                    p && (
                        p.toLowerCase().includes('pos58') || 
                        p.toLowerCase().includes('xprinter') ||
                        p.toLowerCase().includes('xinye') ||
                        p.toLowerCase().includes('xp-58')
                    )
                );
                if (pos58Printer) {
                    printerName = pos58Printer;
                } else {
                    printerName = printers[0]; // Use first available printer
                }
            }
        } catch (e) {
            // Silently fail - will use default printer name
            console.log('Using default printer name:', printerName);
        }
        
        // Create printer config
        const config = qz.configs.create(printerName);
        
        // Print the receipt
        await qz.print(config, escposCommands);
        
        console.log('Receipt printed successfully via QZ Tray');
        
        return {
            success: true,
            message: 'Receipt printed successfully!',
            printer: printerName
        };
        
    } catch (error) {
        console.error('QZ Tray print error:', error);
        
        // Disconnect on error
        if (qzConnected && typeof qz !== 'undefined' && qz.websocket && qz.websocket.isActive()) {
            try {
                await qz.websocket.disconnect();
            } catch (e) {
                // Ignore disconnect errors
            }
            qzConnected = false;
        }
        
        // Provide helpful error messages
        let errorMessage = 'Failed to print receipt';
        if (error.message && error.message.includes('certificate')) {
            errorMessage = 'QZ Tray certificate not approved. Please allow this website in QZ Tray settings.';
        } else if (error.message && error.message.includes('Request blocked')) {
            errorMessage = 'QZ Tray connection blocked. Please allow this website in QZ Tray certificate settings.';
        } else if (error.message) {
            errorMessage = error.message;
        }
        
        return {
            success: false,
            error: errorMessage
        };
    }
}

/**
 * Check if QZ Tray is available
 * @returns {boolean}
 */
function isQZTrayAvailable() {
    return typeof qz !== 'undefined' && typeof qz.websocket !== 'undefined';
}

// Expose functions globally
window.printReceiptWithQZTray = printReceiptWithQZTray;
window.isQZTrayAvailable = isQZTrayAvailable;
window.initQZTray = initQZTray;

