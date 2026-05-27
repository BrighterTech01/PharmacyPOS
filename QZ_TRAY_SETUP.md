# QZ Tray Setup Guide for Automatic Receipt Printing

This guide will help you set up **QZ Tray** for automatic, silent receipt printing on your POS system.

## What is QZ Tray?

QZ Tray is a small desktop application that runs on your POS computer and allows your web browser to send print jobs directly to printers without showing print dialogs. It's perfect for POS systems that need automatic receipt printing.

## Installation Steps

### Step 1: Download and Install QZ Tray

1. Go to **https://qz.io/download/**
2. Download the **QZ Tray** installer for Windows
3. Run the installer and follow the setup wizard
4. **Important:** During installation, make sure to check "Start QZ Tray on Windows startup" so it runs automatically

### Step 2: Start QZ Tray

1. After installation, QZ Tray should start automatically
2. Look for the QZ Tray icon in your system tray (bottom-right corner of Windows)
3. If it's not running, search for "QZ Tray" in Windows Start menu and launch it

### Step 3: Allow Your Website in QZ Tray

1. **Right-click** the QZ Tray icon in the system tray
2. Select **"Certificate"** or **"Settings"**
3. In the certificate settings, you'll see a list of websites
4. Find your pharmacy website URL (e.g., `http://localhost/pharmacy` or your domain)
5. Click **"Allow"** or **"Trust"** for your website
6. You may need to refresh your browser page after allowing

### Step 4: Verify Printer Setup

1. Make sure your **POS58 Printer** is installed in Windows
2. Open **Windows Settings** → **Devices** → **Printers & scanners**
3. Verify your POS58 printer is listed and set as **Default** (optional but recommended)
4. Test print from Notepad to ensure the printer works

### Step 5: Add QZ Tray Scripts to Your POS Page

The QZ Tray integration has been added to your `posManage.php` file. You need to include the QZ Tray library script.

**Add this before the closing `</body>` tag in `Staff/posManage.php`:**

```html
<!-- QZ Tray Library -->
<script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2/qz-tray.min.js"></script>
<!-- QZ Tray Print Functions -->
<script src="../Assets/Javascript/qzTrayPrint.js"></script>
```

## How It Works

1. **After a successful sale**, the system automatically calls `printReceipt(saleId)`
2. The function checks if QZ Tray is available
3. If QZ Tray is running and connected:
   - Fetches receipt data from the server
   - Generates ESC/POS commands for the thermal printer
   - Sends the print job directly to the printer **silently** (no dialog)
4. If QZ Tray is not available, it falls back to browser print (shows dialog)

## Testing

1. Make a test sale in your POS system
2. Click "Print Receipt" after the transaction
3. The receipt should print automatically without any dialog
4. Check the browser console (F12) for any error messages

## Troubleshooting

### QZ Tray Not Connecting

- **Check if QZ Tray is running:** Look for the icon in system tray
- **Check browser console:** Open F12 and look for connection errors
- **Verify certificate:** Make sure your website is allowed in QZ Tray settings
- **Check firewall:** Windows Firewall might be blocking QZ Tray

### Printer Not Found

- **Check printer name:** The script looks for "POS58 Printer" by default
- **Update printer name:** If your printer has a different name, edit `qzTrayPrint.js` and change the `printerName` variable
- **List available printers:** QZ Tray will try to auto-detect POS58 printers

### Receipt Format Issues

- **Check ESC/POS commands:** The receipt format is defined in `generateESCPOSCommands()` function
- **Adjust width:** Thermal printers are typically 48mm (about 32 characters per line)
- **Test print:** Make a test sale and check the printed receipt format

### Fallback to Browser Print

If QZ Tray is not available, the system will automatically fall back to browser print (which shows a dialog). This ensures printing always works, even if QZ Tray is not installed.

## Advanced Configuration

### Custom Printer Name

Edit `Assets/Javascript/qzTrayPrint.js` and modify the `printerName` variable:

```javascript
let printerName = 'Your Printer Name Here';
```

### Custom Receipt Format

Edit the `generateESCPOSCommands()` function in `qzTrayPrint.js` to customize:
- Store name and header
- Item formatting
- Totals layout
- Footer messages

### Multiple Printers

QZ Tray can handle multiple printers. The script will automatically detect and use the first POS58-compatible printer it finds.

## Security Notes

- QZ Tray requires explicit permission for each website
- Only allow trusted websites in QZ Tray certificate settings
- QZ Tray only works on the local machine (localhost) or trusted domains

## Support

- **QZ Tray Documentation:** https://qz.io/docs/
- **QZ Tray Community:** https://qz.io/support/
- **ESC/POS Commands:** https://reference.epson-biz.com/modules/ref_escpos/

## Benefits of QZ Tray

✅ **Silent Printing** - No print dialogs  
✅ **Raw ESC/POS Control** - Full control over thermal printer features  
✅ **Reliable** - Works consistently across browsers  
✅ **Easy Setup** - Simple installation and configuration  
✅ **Fallback Support** - Automatically falls back to browser print if unavailable  

---

**Note:** QZ Tray must be installed and running on each POS computer where you want automatic printing.

