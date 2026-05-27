<?php

if (!function_exists('generateXinyeThermalReceiptHTML')) {
    /**
     * Generate receipt HTML optimized for Xinye/Xprinter thermal printer drivers.
     *
     * @param array $sale_data   Sale metadata (expects sale_id, created_at, cashier_name, total_amount, payment_method, etc.)
     * @param array $items       Array of line items (brand_name, generic_name, quantity, price, subtotal)
     * @param array $options     Additional display options: customer_name, subtotal, tax_amount, total_amount,
     *                           cash_received, change_amount, payment_method
     */
    function generateXinyeThermalReceiptHTML(array $sale_data, array $items, array $options = []): string
    {
        $defaults = [
            'customer_name'  => 'Walk-in Customer',
            'subtotal'       => null,
            'tax_amount'     => 0,
            'total_amount'   => $sale_data['total_amount'] ?? null,
            'cash_received'  => null,
            'change_amount'  => null,
            'payment_method' => $sale_data['payment_method'] ?? null,
            'discount_type'  => $sale_data['discount_type'] ?? 'None',
            'discount_amount' => isset($sale_data['discount_amount']) ? floatval($sale_data['discount_amount']) : 0,
        ];

        $context = array_merge($defaults, $options);

        $date = isset($sale_data['created_at']) ? date('m/d/Y', strtotime($sale_data['created_at'])) : date('m/d/Y');
        $time = isset($sale_data['created_at']) ? date('h:i A', strtotime($sale_data['created_at'])) : date('h:i A');

        $items_html = '';
        $calculatedSubtotal = 0.0;
        $item_count = 0;

        $formatQuantity = static function ($value) {
            if ($value === null) {
                return '0';
            }
            $value = floatval($value);
            if (abs($value - round($value)) < 0.0001) {
                return (string) intval(round($value));
            }
            return number_format($value, 2);
        };

        foreach ($items as $item) {
            $item_count++;
            // Limit product name length for better receipt layout - very short names for thermal printer
            // Example: "amoxicillin" becomes "amox.."
            $brand = htmlspecialchars($item['brand_name'] ?? 'Item', ENT_QUOTES, 'UTF-8');
            if (strlen($brand) > 8) {
                $brand = substr($brand, 0, 6) . '..';
            }
            
            $generic = htmlspecialchars($item['generic_name'] ?? '', ENT_QUOTES, 'UTF-8');
            if (strlen($generic) > 14) {
                $generic = substr($generic, 0, 12) . '..';
            }
            
            $qty_display = $formatQuantity($item['quantity'] ?? 0);
            $price_display = number_format(floatval($item['price'] ?? 0), 2);
            $line_total = number_format(floatval($item['subtotal'] ?? 0), 2);

            $calculatedSubtotal += floatval($item['subtotal'] ?? 0);

            $items_html .= "\n            <tr class='item-row'>\n                <td class='item-index'>{$item_count}.</td>\n                <td class='item-name'>{$brand}</td>\n                <td class='item-qty'>{$qty_display}</td>\n                <td class='item-price'>₱{$price_display}</td>\n                <td class='item-total'>₱{$line_total}</td>\n            </tr>";

            // Show generic name only if different from brand and not too long
            if ($generic !== '' && strcasecmp($generic, $brand) !== 0 && strlen($generic) <= 20) {
                $items_html .= "\n            <tr class='item-desc-row'>\n                <td></td>\n                <td class='item-desc' colspan='4'>{$generic}</td>\n            </tr>";
            }
        }

        if ($context['subtotal'] === null) {
            $context['subtotal'] = $calculatedSubtotal;
        }

        if ($context['total_amount'] === null) {
            $context['total_amount'] = $context['subtotal'] + $context['tax_amount'];
        }

        // Ensure customer name is always set and not empty
        $customerNameValue = !empty(trim($context['customer_name'] ?? '')) ? trim($context['customer_name']) : 'Walk-in Customer';
        $customerName = htmlspecialchars($customerNameValue, ENT_QUOTES, 'UTF-8');
        $cashierName = htmlspecialchars($sale_data['cashier_name'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
        $paymentMethod = $context['payment_method'] ? htmlspecialchars($context['payment_method'], ENT_QUOTES, 'UTF-8') : null;

        $subtotalDisplay = number_format(floatval($context['subtotal']), 2);
        $taxDisplay = number_format(floatval($context['tax_amount']), 2);
        $totalDisplay = number_format(floatval($context['total_amount']), 2);
        $cashReceivedDisplay = $context['cash_received'] !== null ? number_format(floatval($context['cash_received']), 2) : null;
        // Always show change amount (even if 0)
        $changeDisplay = $context['change_amount'] !== null ? number_format(floatval($context['change_amount']), 2) : '0.00';

        // Use receipt_number if available, otherwise fallback to sale_id
        $receiptNumber = htmlspecialchars($sale_data['receipt_number'] ?? $sale_data['sale_id'] ?? '000000', ENT_QUOTES, 'UTF-8');
        $barcodeValue = str_pad(preg_replace('/\D/', '', $sale_data['sale_id'] ?? ''), 6, '0', STR_PAD_LEFT);

        return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='ISO-8859-1'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Receipt #{$receiptNumber}</title>
        <style>
            /* Xinye/Xprinter Thermal Printer Specific Styles */
            :root {
                --receipt-base: 11px;
                --receipt-small: 10px;
                --receipt-xs: 9px;
                --receipt-title: 13px;
                --receipt-heading: 12px;
                --receipt-spacing: 8px;
            }

            @media print {
                body { 
                    margin: 0 !important; 
                    padding: 0 !important; 
                    font-family: 'Lucida Console', 'Courier New', monospace !important;
                    font-size: 14px !important;
                    font-weight: normal !important;
                    line-height: 1.3 !important;
                    -webkit-print-color-adjust: exact !important;
                    color-adjust: exact !important;
                    background: white !important;
                    color: black !important;
                    width: 48mm !important;
                    height: auto !important;
                }
                .no-print { display: none !important; }
                .receipt-container { 
                    width: 48mm !important; 
                    margin: 0 auto !important; 
                    padding: 1mm 2mm !important; 
                    background: white !important;
                    box-shadow: none !important;
                    border: none !important;
                    border-radius: 0 !important;
                    page-break-after: avoid !important;
                    page-break-inside: avoid !important;
                    font-size: 14px !important;
                    line-height: 1.3 !important;
                }
                @page { 
                    margin: 0 !important; 
                    size: auto !important;
                    padding: 0 !important;
                    size: 48mm 210mm !important;
                }
                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                    box-shadow: none !important;
                    text-shadow: none !important;
                    text-transform: none !important;
                }
                .text-center { text-align: center !important; }
                .text-right { text-align: right !important; }
                .text-left { text-align: left !important; }
                .text-bold { font-weight: bold !important; }
                .text-large { font-size: 22px !important; }
            }
            
            @media screen {
                body { 
                    background: #f5f5f5; 
                    display: flex; 
                    justify-content: center; 
                    align-items: flex-start; 
                    min-height: 100vh;
                    padding: 20px;
                    box-sizing: border-box;
                    font-family: 'Courier New', monospace;
                }
                .receipt-container { 
                    background: white; 
                    box-shadow: 0 0 20px rgba(0,0,0,0.1); 
                    border-radius: 8px; 
                    padding: 20px; 
                    width: min(92vw, 420px);
                    font-family: 'Courier New', monospace;
                    transition: transform 0.25s ease, box-shadow 0.25s ease;
                }
                .receipt-container:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 18px 35px rgba(15, 23, 42, 0.18);
                }
            }
            
            .receipt-container { 
                width: min(92vw, 420px); 
                line-height: 1.3;
                font-family: 'Lucida Console', 'Courier New', monospace;
                font-size: var(--receipt-base);
                font-weight: normal;
            }
            
            .header { 
                text-align: center;
                margin-bottom: var(--receipt-spacing);
                border-bottom: 1px dashed #000;
                padding-bottom: calc(var(--receipt-spacing) * 0.8);
            }
            
            .company-name { 
                font-size: var(--receipt-heading);
                font-weight: bold;
                margin-bottom: calc(var(--receipt-spacing) * 0.35);
                text-transform: uppercase;
            }
            
            .company-address { 
                font-size: var(--receipt-small); 
                margin-bottom: calc(var(--receipt-spacing) * 0.3);
            }
            
            .receipt-title { 
                font-size: var(--receipt-title); 
                margin: calc(var(--receipt-spacing) * 0.7) 0;
                font-weight: bold;
                text-transform: uppercase;
            }
            
            .transaction-info { 
                font-size: var(--receipt-small); 
                margin: calc(var(--receipt-spacing) * 0.5) 0;
                text-align: left;
                border-bottom: 1px dashed #000;
                padding-bottom: calc(var(--receipt-spacing) * 0.85);
                line-height: 1.4;
            }
            
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin: calc(var(--receipt-spacing) * 0.6) 0;
                font-size: var(--receipt-base);
                table-layout: fixed;
            }
            .items-table thead th {
                text-transform: uppercase;
                font-weight: bold;
                border-bottom: 1px solid #000;
                padding-bottom: 3px;
                font-size: 10px;
            }
            .item-row td {
                padding: 1px 0;
                vertical-align: top;
            }
            .item-index {
                width: 6%;
                text-align: left;
            }
            .item-name {
                width: 38%;
                padding-right: 2px;
                word-wrap: break-word;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 100px;
                font-size: 11px;
            }
            .item-qty {
                width: 10%;
                text-align: center;
                padding-right: 2px;
                font-variant-numeric: tabular-nums;
            }
            .item-price {
                width: 20%;
                text-align: right;
                padding-right: 2px;
                font-variant-numeric: tabular-nums;
            }
            .item-total {
                width: 22%;
                text-align: right;
                font-weight: bold;
                font-variant-numeric: tabular-nums;
            }
            .item-desc-row td {
                padding: 0 0 1px 0;
            }
            .item-desc {
                font-size: 9px;
                color: #555;
                font-style: italic;
                padding-left: 8%;
            }
            
            .totals-section {
                border-top: 1px solid #000;
                margin: calc(var(--receipt-spacing) * 0.9) 0;
                padding: calc(var(--receipt-spacing) * 0.7) 0;
                font-size: var(--receipt-base);
            }

            .total-line {
                display: flex;
                justify-content: space-between;
                margin: calc(var(--receipt-spacing) * 0.35) 0;
                padding: calc(var(--receipt-spacing) * 0.35) 0;
                gap: 0.75rem;
            }

            .total-line span:last-child {
                display: inline-block;
                max-width: 160px;
                text-align: right;
                transform-origin: right center;
            }

            .fits-inline span:last-child {
                transform: scale(1);
            }
            
            .total-line-bold {
                font-weight: bold;
                border-top: 1px dashed #000;
                padding-top: calc(var(--receipt-spacing) * 0.6);
                margin-top: calc(var(--receipt-spacing) * 0.45);
            }
            
            .footer { 
                border-top: 1px solid #000; 
                padding: calc(var(--receipt-spacing) * 0.8); 
                margin-top: calc(var(--receipt-spacing) * 1); 
                font-size: var(--receipt-base); 
                text-align: center;
                line-height: 1.4;
            }
            
            .barcode { 
                margin: 3px 0; 
                text-align: center;
                font-family: 'Courier New', monospace;
                font-size: var(--receipt-base);
                letter-spacing: 2px;
                font-weight: bold;
            }
            
            .print-btn { 
                background: #4CAF50; 
                color: white; 
                border: none; 
                padding: 10px 20px; 
                border-radius: 4px; 
                cursor: pointer; 
                font-size: 14px; 
                margin: 10px auto; 
                display: block;
                font-family: Arial, sans-serif;
            }
            
            .divider {
                border-top: 1px dashed #000;
                margin: 3px 0;
            }
            
            .divider-bold {
                border-top: 2px solid #000;
                margin: 4px 0;
            }
        </style>
    </head>
    <body>
        <div class='receipt-container'>
            <div class='header'>
                <div class='company-name'>OCT PHARMACY</div>
                <div class='company-address'>Purok 4 Isidro D. Tan, Tangub City</div>
                <div class='receipt-title'>OFFICIAL RECEIPT</div>
            </div>
            
            <div class='transaction-info'>
                <div>Date: $date &nbsp;&nbsp; Time: $time</div>
                <div>Receipt#: $receiptNumber</div>
                <div>Cashier: $cashierName</div>
                <div>Customer: $customerName</div>
                " . ($paymentMethod ? "<div>Payment Method: $paymentMethod</div>" : '') . "
            </div>
            
            <table class='items-table'>
                <thead>
                    <tr>
                        <th class='item-index'>#</th>
                        <th class='item-name'>Item</th>
                        <th class='item-qty'>Qty</th>
                        <th class='item-price'>Price</th>
                        <th class='item-total'>Total</th>
                    </tr>
                </thead>
                <tbody>
                    $items_html
                </tbody>
            </table>
            
            <div class='divider-bold'></div>
            
            <div class='totals-section'>
                <div class='total-line'>
                    <span>Subtotal:</span>
                    <span>₱$subtotalDisplay</span>
                </div>
                " . (isset($context['discount_type']) && $context['discount_type'] !== 'None' && isset($context['discount_amount']) && $context['discount_amount'] > 0 ? "<div class='total-line'><span>Discount ({$context['discount_type']}):</span><span class='text-red-600'>-₱" . number_format(floatval($context['discount_amount']), 2) . "</span></div>" : '') . "
                <div class='total-line'>
                    <span>Tax:</span>
                    <span>₱$taxDisplay</span>
                </div>
                <div class='total-line total-line-bold'>
                    <span>TOTAL:</span>
                    <span>₱$totalDisplay</span>
                </div>
                " . ($cashReceivedDisplay !== null ? "<div class='total-line'><span>Cash Received:</span><span>₱$cashReceivedDisplay</span></div>" : '') . "
                <div class='total-line total-line-bold' style='font-size: 1.1em; color: #059669;'>
                    <span>CHANGE:</span>
                    <span>₱$changeDisplay</span>
                </div>
            </div>
            
            <div class='barcode'>
                *$barcodeValue*
            </div>
            
            <div class='footer'>
                <div>Thank you for your purchase!</div>
                <div>Open Daily • 7:00 AM – 9:00 PM</div>
                <div>Follow-up? Call or message us anytime.</div>
                <div>** OFFICIAL RECEIPT **</div>
            </div>
            
            <button class='print-btn no-print' onclick='window.print()'>🖨️ Print Receipt</button>
        </div>
        
        <script>
            // Enhanced print for Xinye/Xprinter driver compatibility with auto-submit
            window.onload = function() {
                setTimeout(function() {
                    if (window.location.search.indexOf('autoprint') !== -1) {
                        setTimeout(function() {
                            window.print();
                            
                            // Function to click print button in print preview
                            function clickPrintButton() {
                                try {
                                    // Try multiple selectors to find the print button
                                    var printPreview = document.querySelector('print-preview-app');
                                    if (printPreview && printPreview.shadowRoot) {
                                        // Try to find the print button in the button strip
                                        var buttonStrip = printPreview.shadowRoot.querySelector('print-preview-button-strip');
                                        if (buttonStrip && buttonStrip.shadowRoot) {
                                            var printButton = buttonStrip.shadowRoot.querySelector('cr-button.action-button');
                                            if (printButton) {
                                                printButton.click();
                                                return true;
                                            }
                                        }
                                        
                                        // Alternative: try direct query
                                        var printButton = printPreview.shadowRoot.querySelector('print-preview-button-strip cr-button.action-button');
                                        if (printButton) {
                                            printButton.click();
                                            return true;
                                        }
                                        
                                        // Try to find by text content
                                        var allButtons = printPreview.shadowRoot.querySelectorAll('cr-button');
                                        for (var i = 0; i < allButtons.length; i++) {
                                            if (allButtons[i].textContent && allButtons[i].textContent.trim() === 'Print') {
                                                allButtons[i].click();
                                                return true;
                                            }
                                        }
                                    }
                                    return false;
                                } catch (e) {
                                    console.log('Click print button error:', e);
                                    return false;
                                }
                            }
                            
                            // Try to click print button multiple times with increasing delays
                            var attempts = [800, 1200, 1800, 2500, 3500];
                            attempts.forEach(function(delay, index) {
                                setTimeout(function() {
                                    if (clickPrintButton()) {
                                        console.log('Print button clicked successfully on attempt ' + (index + 1));
                                    } else if (index === attempts.length - 1) {
                                        console.log('Could not find print button after all attempts');
                                    }
                                }, delay);
                            });
                            
                            // Also try Enter key as fallback
                            setTimeout(function() {
                                try {
                                    var enterEvent = new KeyboardEvent('keydown', {
                                        key: 'Enter',
                                        code: 'Enter',
                                        keyCode: 13,
                                        which: 13,
                                        bubbles: true,
                                        cancelable: true
                                    });
                                    window.dispatchEvent(enterEvent);
                                    document.dispatchEvent(enterEvent);
                                } catch (e) {
                                    console.log('Enter key simulation failed:', e);
                                }
                            }, 1000);
                            
                            // Close window after printing
                            setTimeout(function() {
                                if (window.opener) {
                                    window.close();
                                }
                            }, 5000);
                        }, 500);
                    }
                }, 300);
            };

            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
                
                // Auto-confirm print dialog with Enter key
                if (e.key === 'Enter' && document.querySelector('print-preview-app')) {
                    setTimeout(function() {
                        try {
                            var printPreview = document.querySelector('print-preview-app');
                            if (printPreview && printPreview.shadowRoot) {
                                var buttonStrip = printPreview.shadowRoot.querySelector('print-preview-button-strip');
                                if (buttonStrip && buttonStrip.shadowRoot) {
                                    var printButton = buttonStrip.shadowRoot.querySelector('cr-button.action-button');
                                    if (printButton) {
                                        printButton.click();
                                        return;
                                    }
                                }
                                var printButton = printPreview.shadowRoot.querySelector('print-preview-button-strip cr-button.action-button');
                                if (printButton) {
                                    printButton.click();
                                }
                            }
                        } catch (e) {
                            console.log('Enter key handler failed:', e);
                        }
                    }, 100);
                }
            });

            function forcePrint() {
                var printWindow = window.open('', '_blank');
                printWindow.document.write(document.documentElement.outerHTML);
                printWindow.document.close();
                printWindow.print();
                printWindow.close();
            }

            function adjustTotals() {
                var totals = document.querySelectorAll('.total-line span:last-child');
                totals.forEach(function(el) {
                    el.style.transform = 'scale(1)';
                    el.parentElement.classList.add('fits-inline');
                    el.style.removeProperty('transform');

                    var maxWidth = 160;
                    var width = el.getBoundingClientRect().width;
                    if (width > maxWidth) {
                        var scale = Math.max(0.6, maxWidth / width);
                        el.style.transform = 'scale(' + scale + ')';
                        el.parentElement.classList.remove('fits-inline');
                    }
                });
            }

            window.addEventListener('load', adjustTotals);
            window.addEventListener('resize', adjustTotals);
        </script>
    </body>
    </html>
    ";
    }
}

if (defined('PRINT_RECEIPT_FUNCTIONS_ONLY')) {
    return;
}

session_start();
include_once '../dbConnection/connection.php';

$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

// Check if we have receipt HTML in session (but regenerate to ensure latest format)
// Note: Session cache is disabled to ensure latest receipt format with short product names
// if (isset($_SESSION['last_receipt_html']) && $_SESSION['last_sale_id'] == $sale_id) {
//     echo $_SESSION['last_receipt_html'];
//     unset($_SESSION['last_receipt_html']);
//     unset($_SESSION['last_sale_id']);
//     exit;
// }

    // Fallback: generate receipt from database if session data is missing
    if ($sale_id > 0) {
        // Get sale details with product information - using updated sales table structure
        $query = "
            SELECT 
                s.sale_id,
                s.receipt_number,
                s.user_id,
                s.total_amount,
                s.final_amount,
                s.payment_method,
                s.is_manual_sale,
                s.customer_name,
                s.cash_recieving,
                s.change_amount,
                s.discount_type,
                s.discount_amount,
                s.created_at,
                u.username as cashier_name,
                p.brand_name,
                p.generic_name,
                COALESCE(sd.quantity_packages, 0) + COALESCE(sd.quantity_units, 0) as quantity,
                sd.unit_price as price,
                sd.subtotal
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN sales_details sd ON s.sale_id = sd.sale_id
            LEFT JOIN deliveries d ON sd.delivery_id = d.delivery_id
            LEFT JOIN products p ON d.product_id = p.product_id
            WHERE s.sale_id = ?
            ORDER BY sd.detail_id ASC
        ";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $sale_data = [];
    $items = [];
    $customer_name = 'Walk-in Customer'; // Will be updated from database if available
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (empty($sale_data)) {
            // Get customer_name from database, handle empty strings and null values
            $db_customer_name = !empty(trim($row['customer_name'] ?? '')) ? trim($row['customer_name']) : 'Walk-in Customer';
            
            $sale_data = [
                'sale_id' => $row['sale_id'],
                'receipt_number' => $row['receipt_number'] ?? '',
                'user_id' => $row['user_id'] ?? 0,
                'total_amount' => $row['total_amount'] ?? 0,
                'final_amount' => $row['final_amount'] ?? $row['total_amount'] ?? 0,
                'payment_method' => $row['payment_method'] ?? 'Cash',
                'is_manual_sale' => $row['is_manual_sale'] ?? 0,
                'customer_name' => $db_customer_name,
                'cash_received' => isset($row['cash_recieving']) ? floatval($row['cash_recieving']) : null,
                'change_amount' => isset($row['change_amount']) ? floatval($row['change_amount']) : null,
                'discount_type' => $row['discount_type'] ?? 'None',
                'discount_amount' => isset($row['discount_amount']) ? floatval($row['discount_amount']) : 0,
                'created_at' => $row['created_at'],
                'cashier_name' => $row['cashier_name'] ?? 'Cashier'
            ];
            // Update customer_name variable for use in receipt generation
            $customer_name = $db_customer_name;
        }
        
        $items[] = [
            'brand_name' => $row['brand_name'],
            'generic_name' => $row['generic_name'],
            'quantity' => $row['quantity'],
            'price' => $row['price'],
            'subtotal' => $row['subtotal']
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    if (!empty($sale_data)) {
        // Use customer name from database if available (handle empty strings)
        if (!empty(trim($sale_data['customer_name'] ?? ''))) {
            $customer_name = trim($sale_data['customer_name']);
        } else {
            $customer_name = 'Walk-in Customer';
        }
        
        // Calculate tax and subtotal
        // Use final_amount if available (after discount), otherwise use total_amount
        $final_total = $sale_data['final_amount'] ?? $sale_data['total_amount'] ?? 0;
        $tax_rate = 0.12;
        $total_amount = $final_total;
        $subtotal = $total_amount / (1 + $tax_rate);
        $tax_amount = $total_amount - $subtotal;
        
        // If discount exists, adjust subtotal
        if (!empty($sale_data['discount_amount']) && $sale_data['discount_amount'] > 0) {
            $subtotal = $subtotal + $sale_data['discount_amount']; // Add discount back to get original subtotal
        }
        
        // Generate receipt HTML
        echo generateXinyeThermalReceiptHTML(
            $sale_data,
            $items,
            [
                'customer_name'  => $customer_name,
                'subtotal'       => $subtotal,
                'tax_amount'     => $tax_amount,
                'total_amount'   => $total_amount,
                'cash_received'  => $sale_data['cash_received'] ?? null,
                'change_amount'  => $sale_data['change_amount'] ?? null,
                'discount_type'  => $sale_data['discount_type'] ?? 'None',
                'discount_amount' => $sale_data['discount_amount'] ?? 0,
            ]
        );
    } else {
        echo "<p>Receipt not found for sale ID: $sale_id</p>";
    }
} else {
    echo "<p>Invalid sale ID</p>";
}
?>