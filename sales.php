<?php
// POS58 Thermal Receipt Printer System
if ($_POST['action'] == 'print_receipt') {
    $amount = $_POST['amount'];
    $item = $_POST['item'] ?? 'Product Sale';
    $quantity = $_POST['quantity'] ?? 1;
    
    // Calculate total
    $total = $amount * $quantity;
    
    // Print to POS58 printer
    $result = printToPOS58($item, $quantity, $amount, $total);
    
    if ($result) {
        $message = "✅ Sale completed! Receipt printed on POS58 printer.";
    } else {
        $message = "❌ Print failed! Check POS58 printer connection.";
    }
}

function printToPOS58($item, $quantity, $price, $total) {
    // ESC/POS commands for POS58 thermal printer
    $commands = "";
    
    // Initialize printer
    $commands .= "\x1B\x40"; // Reset printer
    
    // Center align and large text for header
    $commands .= "\x1B\x61\x01"; // Center align
    $commands .= "\x1D\x21\x11"; // Double height and width
    $commands .= "SALES RECEIPT\n";
    $commands .= "\x1D\x21\x00"; // Normal text
    $commands .= "================\n";
    
    // Left align for items
    $commands .= "\x1B\x61\x00"; // Left align
    
    // Date and time
    $commands .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $commands .= "----------------\n";
    
    // Item details
    $commands .= "Item: " . substr($item, 0, 16) . "\n";
    $commands .= "Qty: " . $quantity . " x $" . number_format($price, 2) . "\n";
    $commands .= "----------------\n";
    
    // Total - emphasized
    $commands .= "\x1B\x61\x02"; // Right align
    $commands .= "\x1D\x21\x01"; // Bold
    $commands .= "TOTAL: $" . number_format($total, 2) . "\n";
    $commands .= "\x1D\x21\x00"; // Normal text
    
    // Footer
    $commands .= "\x1B\x61\x01"; // Center align
    $commands .= "Thank you!\n";
    $commands .= "www.yourstore.com\n";
    
    // Cut paper (partial cut)
    $commands .= "\x1B\x69"; // Full cut: \x1B\x6D
    $commands .= "\n\n\n"; // Feed some lines
    
    // Send to printer
    return sendToPrinter($commands);
}

function sendToPrinter($commands) {
    // Method 1: Direct to USB printer (Windows)
    $printer_port = "USB001"; // Common USB port for POS printers
    
    try {
        // Method 1A: Using Windows print command
        $filename = 'pos58_print_' . time() . '.prn';
        file_put_contents($filename, $commands);
        
        // Print raw ESC/POS commands
        $result = copy($filename, "\\\\localhost\\" . $printer_port);
        unlink($filename);
        
        if ($result) {
            return true;
        }
    } catch (Exception $e) {
        // Method failed
    }
    
    try {
        // Method 2: Using direct file write to LPT port
        $fp = fopen("LPT1:", "w");
        if ($fp) {
            fwrite($fp, $commands);
            fclose($fp);
            return true;
        }
    } catch (Exception $e) {
        // Method failed
    }
    
    try {
        // Method 3: Using COM port (if connected via serial)
        $fp = fopen("COM3:", "w");
        if ($fp) {
            fwrite($fp, $commands);
            fclose($fp);
            return true;
        }
    } catch (Exception $e) {
        // Method failed
    }
    
    return false;
}

function detectPOS58Printer() {
    $common_ports = ['USB001', 'USB002', 'LPT1', 'LPT2', 'COM1', 'COM2', 'COM3'];
    $detected = [];
    
    foreach ($common_ports as $port) {
        if (@fopen($port, 'w')) {
            $detected[] = $port;
            fclose($port);
        }
    }
    
    return $detected;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>POS58 Sales System - Auto Print</title>
    <style>
        body { font-family: 'Courier New', monospace; margin: 20px; background: #f5f5f5; }
        .container { max-width: 400px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input, select { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; }
        input:focus { border-color: #4CAF50; outline: none; }
        button { background: #4CAF50; color: white; padding: 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #45a049; }
        .message { padding: 15px; margin: 15px 0; border-radius: 5px; text-align: center; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .printer-info { background: #e9f7fe; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .receipt-preview { background: white; border: 1px dashed #ccc; padding: 15px; font-family: 'Courier New', monospace; font-size: 12px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="text-align: center; color: #333;">🧾 POS58 Sales System</h1>
        <p style="text-align: center; color: #666;">Thermal Receipt Auto-Print</p>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="print_receipt">
            
            <div class="form-group">
                <label for="item">Item Name:</label>
                <input type="text" id="item" name="item" value="Product Sale" required>
            </div>
            
            <div class="form-group">
                <label for="quantity">Quantity:</label>
                <input type="number" id="quantity" name="quantity" value="1" min="1" required>
            </div>
            
            <div class="form-group">
                <label for="amount">Unit Price ($):</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
            </div>
            
            <button type="submit">🖨️ PRINT RECEIPT</button>
        </form>
        
        <div class="printer-info">
            <h3>🔧 POS58 Printer Setup:</h3>
            <p><strong>Detected Ports:</strong><br>
            <?php
            $ports = detectPOS58Printer();
            if (!empty($ports)) {
                echo implode(', ', $ports);
            } else {
                echo 'No ports detected. Make sure POS58 printer is installed.';
            }
            ?>
            </p>
            <p><strong>Setup Instructions:</strong></p>
            <ol style="font-size: 12px; padding-left: 20px;">
                <li>Connect POS58 via USB</li>
                <li>Windows should auto-install driver</li>
                <li>Printer should appear as "USB001" or similar</li>
                <li>Test with a sale above</li>
            </ol>
        </div>
        
        <!-- Receipt Preview -->
        <div class="receipt-preview">
            <div style="text-align: center; font-weight: bold;">SALES RECEIPT</div>
            <div style="text-align: center;">================</div>
            <div>Date: <?php echo date('Y-m-d H:i:s'); ?></div>
            <div>----------------</div>
            <div>Item: Product Sale</div>
            <div>Qty: 1 x $0.00</div>
            <div>----------------</div>
            <div style="text-align: right; font-weight: bold;">TOTAL: $0.00</div>
            <div style="text-align: center;">Thank you!</div>
        </div>
    </div>
</body>
</html>