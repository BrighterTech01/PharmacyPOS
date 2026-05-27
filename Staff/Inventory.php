<?php
require_once '../dbConnection/connection.php';
include '../includes/CashierHeader.php';

$inventoryRows = [];
$stats = [
    'total_products' => 0,
    'total_packages' => 0,
    'total_units' => 0,
    'available_products' => 0,
    'low_stock_products' => 0,
    'expiring_products' => 0,
];

$today = new DateTime('today');

if (isset($conn) && $conn instanceof mysqli) {
    $sql = "
        SELECT
            delivery_id,
            product_id,
            brand_name,
            generic_name,
            strength,
            barcode,
            category_name,
            batch_no,
            expiry_date,
            manufacturing_date,
            delivery_date,
            fifo_fefo_priority,
            cost_per_package,
            cost_per_unit,
            selling_price_package,
            selling_price_unit,
            storage_packages_balance,
            pharmacy_units_balance,
            availability_status
        FROM view_products_for_sale
    ";

    $result = @mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $inventoryRows[] = $row;

            $packages = (int)($row['storage_packages_balance'] ?? 0);
            $units = (int)($row['pharmacy_units_balance'] ?? 0);

            $stats['total_products']++;
            $stats['total_packages'] += $packages;
            $stats['total_units'] += $units;

            $status = strtolower((string)($row['availability_status'] ?? ''));
            if ($status === 'available') {
                $stats['available_products']++;
            }
            if (str_contains($status, 'low')) {
                $stats['low_stock_products']++;
            }

            if (!empty($row['expiry_date'])) {
                $expiry = DateTime::createFromFormat('Y-m-d', $row['expiry_date']);
                if ($expiry instanceof DateTime) {
                    $diff = $today->diff($expiry);
                    if (!$diff->invert && $diff->days <= 30) {
                        $stats['expiring_products']++;
                    }
                }
            }
        }
        mysqli_free_result($result);
    }
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatMoney($value, $decimals = 2): string {
    return '₱' . number_format((float)$value, $decimals);
}

function formatNumber($value): string {
    return number_format((float)$value);
}

function formatDateDisplay(?string $date, ?DateTime $today = null): string {
    if (empty($date)) {
        return '—';
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt) {
        return '—';
    }

    $label = $dt->format('M j, Y');
    if ($today instanceof DateTime) {
        $diff = $today->diff($dt);
        if ($diff->invert) {
            $label .= ' • expired ' . $diff->days . 'd ago';
        } else {
            $label .= ' • in ' . $diff->days . 'd';
        }
    }
    return $label;
}
?>

<main class="w-full overflow-y-auto p-4 sm:p-6 md:p-8 lg:p-10 md:pl-20 bg-gray-50 min-h-screen">
        <style>
        .inventory-card { background: white; border-radius: 1.5rem; border: 1px solid rgba(226,232,240,0.7); box-shadow: 0 20px 45px -25px rgba(15,23,42,0.35); }
        .inventory-metric { border-radius: 1.25rem; padding: 1.25rem 1.5rem; background: linear-gradient(145deg, rgba(255,255,255,0.96), rgba(241,245,249,0.9)); border: 1px solid rgba(226,232,240,0.7); box-shadow: 0 18px 40px -25px rgba(15,23,42,0.45); }
        .inventory-metric h3 { font-size: 0.75rem; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; margin-bottom: 0.6rem; }
        .inventory-metric strong { font-size: 2rem; color: #0f172a; }
        .inventory-tabs { display: inline-flex; gap: 0.35rem; background: rgba(241,245,249,0.8); border-radius: 0.9rem; padding: 0.35rem; }
        .inventory-tab { font-size: 0.85rem; font-weight: 600; padding: 0.4rem 1rem; border-radius: 0.6rem; color: #334155; cursor: pointer; transition: all 0.2s ease; user-select: none; }
        .inventory-tab.active { background: white; box-shadow: 0 8px 16px -12px rgba(15,23,42,0.55); color: #111827; }
        .inventory-search { position: relative; width: 100%; min-width: 240px; }
        .inventory-search input { width: 100%; border: 1px solid rgba(203,213,225,0.8); border-radius: 0.8rem; padding: 0.7rem 1rem; padding-right: 2.75rem; background: rgba(255,255,255,0.95); font-size: 0.95rem; transition: border 0.2s ease, box-shadow 0.2s ease; }
        .inventory-search input:focus { outline: none; border-color: rgba(59,130,246,0.6); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .inventory-search svg { position: absolute; top: 50%; right: 0.9rem; transform: translateY(-50%); width: 1.15rem; height: 1.15rem; color: #64748b; }
        .inventory-table-wrapper { overflow-x: auto; padding-bottom: 0.75rem; }
        .inventory-table-scroll { max-height: none; }
        .inventory-table-scroll.has-scroll { max-height: 540px; overflow-y: auto; }
        .inventory-table { width: 100%; min-width: 960px; border-collapse: separate; border-spacing: 0; }
        .inventory-table thead th { background: rgba(241,245,249,0.9); padding: 0.85rem 0.95rem; font-size: 0.72rem; font-weight: 700; color: #1f2937; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid rgba(226,232,240,0.8); }
        .inventory-table tbody tr { transition: background 0.15s ease; }
        .inventory-table tbody tr:hover { background: rgba(248,250,252,0.65); }
        .inventory-table td { padding: 0.95rem; border-bottom: 1px solid rgba(226,232,240,0.6); vertical-align: top; font-size: 0.92rem; }
        .inventory-status { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .inventory-status.available { background: rgba(34,197,94,0.18); color: #047857; }
        .inventory-status.low { background: rgba(251,191,36,0.22); color: #92400e; }
        .inventory-status.out { background: rgba(248,113,113,0.22); color: #b91c1c; }
        .inventory-status.unknown { background: rgba(148,163,184,0.22); color: #475569; }
            .scroll-slim::-webkit-scrollbar { width: 8px; height: 8px; }
            .scroll-slim::-webkit-scrollbar-thumb { background: rgba(107,114,128,.35); border-radius: 8px; }
            .scroll-slim { scrollbar-width: thin; scrollbar-color: rgba(107,114,128,.35) transparent; }
        </style>

    <section class="max-w-7xl mx-auto space-y-8">
        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wide">Inventory for Sale</p>
                <h1 class="text-3xl font-bold text-gray-900">Pharmacy Floor Inventory</h1>
                <p class="text-sm text-gray-500 mt-1">Snapshot of products currently available for sale from <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">view_products_for_sale</code>.</p>
                </div>
        </header>

        <div class="grid gap-4 md:gap-6 md:grid-cols-2 lg:grid-cols-4">
            <article class="inventory-metric">
                <h3>Total Products Listed</h3>
                <strong><?= formatNumber($stats['total_products']); ?></strong>
                <p class="text-xs text-gray-500 mt-1">Distinct product/batch entries ready for dispensing.</p>
            </article>
            <article class="inventory-metric">
                <h3>Total Packages Available</h3>
                <strong><?= formatNumber($stats['total_packages']); ?></strong>
                <p class="text-xs text-gray-500 mt-1">Remaining package balance across storage.</p>
            </article>
            <article class="inventory-metric">
                <h3>Total Units Available</h3>
                <strong><?= formatNumber($stats['total_units']); ?></strong>
                <p class="text-xs text-gray-500 mt-1">Units on the pharmacy floor ready for sale.</p>
            </article>
            <article class="inventory-metric">
                <h3>Alerts</h3>
                <strong><?= formatNumber($stats['low_stock_products']); ?></strong>
                <p class="text-xs text-gray-500 mt-1">Low-stock batches. <?= formatNumber($stats['expiring_products']); ?> expiring soon.</p>
            </article>
            </div>

        <section class="inventory-card">
            <div class="flex flex-col gap-6 lg:gap-0 lg:flex-row lg:items-center lg:justify-between border-b border-gray-200/70 px-6 py-6">
                    <div>
                    <h2 class="text-xl font-semibold text-gray-900">Live Inventory Details</h2>
                    <p class="text-sm text-gray-500">Filter and search through currently available batches.</p>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="inventory-tabs" role="tablist">
                        <button class="inventory-tab active" data-tab="all" type="button">All</button>
                        <button class="inventory-tab" data-tab="available" type="button">Available</button>
                        <button class="inventory-tab" data-tab="low" type="button">Low Stock</button>
                        <button class="inventory-tab" data-tab="expiring" type="button">Expiring Soon</button>
                    </div>
                    <div class="inventory-search">
                        <input id="inventorySearch" type="search" placeholder="Search product, batch, barcode...">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 105.25 5.25a7.5 7.5 0 0011.4 11.4z" />
                        </svg>
                    </div>
                    </div>
                </div>

            <div class="inventory-table-wrapper scroll-slim px-3 md:px-6">
                <div id="inventoryTableViewport" class="inventory-table-scroll scroll-slim">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Batch / Barcode</th>
                            <th>Category</th>
                            <th>Expiry</th>
                            <th>Balances</th>
                            <th>Pricing</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryBody">
                    <?php if (empty($inventoryRows)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">No inventory records found in <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">view_products_for_sale</code>.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventoryRows as $row): ?>
                            <?php
                                $statusRaw = strtolower((string)($row['availability_status'] ?? ''));
                                $statusClass = 'unknown';
                                if (str_contains($statusRaw, 'avail')) { $statusClass = 'available'; }
                                elseif (str_contains($statusRaw, 'low')) { $statusClass = 'low'; }
                                elseif (str_contains($statusRaw, 'out')) { $statusClass = 'out'; }

                                $expiryDisplay = formatDateDisplay($row['expiry_date'] ?? null, $today);
                                $isExpiringSoon = false;
                                if (!empty($row['expiry_date'])) {
                                    $expiryDate = DateTime::createFromFormat('Y-m-d', $row['expiry_date']);
                                    if ($expiryDate instanceof DateTime) {
                                        $diff = $today->diff($expiryDate);
                                        $isExpiringSoon = !$diff->invert && $diff->days <= 30;
                                    }
                                }
                            ?>
                            <tr class="inventory-row" data-status="<?= h($statusClass); ?>" data-expiring="<?= $isExpiringSoon ? 'true' : 'false'; ?>">
                                <td>
                                    <div class="font-semibold text-gray-900 truncate" title="<?= h($row['brand_name'] ?? ''); ?>"><?= h($row['brand_name'] ?? ''); ?></div>
                                    <div class="text-xs text-gray-500 truncate" title="<?= h($row['generic_name'] ?? ''); ?>"><?= h($row['generic_name'] ?? ''); ?><?= !empty($row['strength']) ? ' • ' . h($row['strength']) : ''; ?></div>
                                    <div class="text-[11px] text-gray-400 mt-1">Delivery ID: <?= h($row['delivery_id'] ?? '—'); ?></div>
                                </td>
                                <td>
                                    <div class="text-sm font-medium text-gray-800">Batch: <?= h($row['batch_no'] ?? '—'); ?></div>
                                    <div class="text-xs text-gray-500">Barcode: <?= h($row['barcode'] ?? '—'); ?></div>
                                    <div class="text-xs text-gray-500">Priority: <?= h($row['fifo_fefo_priority'] ?? '—'); ?></div>
                                </td>
                                <td>
                                    <div class="text-sm text-gray-800"><?= h($row['category_name'] ?? '—'); ?></div>
                                    <div class="text-xs text-gray-500">Product ID: <?= h($row['product_id'] ?? '—'); ?></div>
                                </td>
                                <td>
                                    <div class="text-sm text-gray-800"><?= h($expiryDisplay); ?></div>
                                    <div class="text-xs text-gray-500">Manufactured: <?= h(formatDateDisplay($row['manufacturing_date'] ?? null)); ?></div>
                                    <div class="text-xs text-gray-500">Delivered: <?= h(formatDateDisplay($row['delivery_date'] ?? null)); ?></div>
                                </td>
                                <td>
                                    <div class="text-sm text-gray-900">Packages: <?= formatNumber($row['storage_packages_balance'] ?? 0); ?></div>
                                    <div class="text-xs text-gray-500">Units: <?= formatNumber($row['pharmacy_units_balance'] ?? 0); ?></div>
                                </td>
                                <td>
                                    <div class="text-sm text-gray-900">Pkg: <?= formatMoney($row['selling_price_package'] ?? 0); ?></div>
                                    <div class="text-xs text-gray-500">Unit: <?= formatMoney($row['selling_price_unit'] ?? 0); ?></div>
                                    <div class="text-xs text-gray-400 mt-1">Cost / unit: <?= formatMoney($row['cost_per_unit'] ?? 0); ?></div>
                                </td>
                                <td>
                                    <span class="inventory-status <?= h($statusClass); ?>"><?= h($row['availability_status'] ?? 'Unknown'); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    </table>
                </div>
                </div>

            <footer class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-t border-gray-200/70 px-6 py-4 text-sm text-gray-600">
                <span id="inventoryCount">Showing <?= formatNumber($stats['total_products']); ?> listings</span>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="px-4 py-2 rounded-lg bg-gray-900 text-white text-xs font-semibold uppercase tracking-wide shadow hover:shadow-lg transition" onclick="location.reload()">Refresh</button>
                </div>
            </footer>
        </section>
            </section>

        <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('inventorySearch');
        const rows = Array.from(document.querySelectorAll('.inventory-row'));
        const tabButtons = Array.from(document.querySelectorAll('.inventory-tab'));
        const countLabel = document.getElementById('inventoryCount');
        const tableViewport = document.getElementById('inventoryTableViewport');

        let activeTab = 'all';
        let filteredRows = [...rows];

        function toggleTableScroll(visibleCount) {
            if (!tableViewport) return;
            if (visibleCount > 10) {
                tableViewport.classList.add('has-scroll');
            } else {
                tableViewport.classList.remove('has-scroll');
            }
        }

        function normalize(value) {
            return (value || '').toString().toLowerCase();
        }

        function applyFilters() {
            const query = normalize(searchInput ? searchInput.value : '');
            filteredRows = [];
            let visibleCount = 0;

            rows.forEach(row => {
                const matchesSearch = !query || normalize(row.textContent).includes(query);
                const status = row.dataset.status || 'unknown';
                const isExpiring = row.dataset.expiring === 'true';

                let matchesTab = true;
                if (activeTab === 'available') {
                    matchesTab = status === 'available';
                } else if (activeTab === 'low') {
                    matchesTab = status === 'low';
                } else if (activeTab === 'expiring') {
                    matchesTab = isExpiring;
                }

                if (matchesSearch && matchesTab) {
                    filteredRows.push(row);
                    visibleCount++;
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });

            const labelMap = {
                all: 'listings',
                available: 'available listings',
                low: 'low-stock batches',
                expiring: 'batches expiring soon'
            };

            if (countLabel) {
                countLabel.textContent = `Showing ${visibleCount.toLocaleString()} ${labelMap[activeTab]}`;
            }

            toggleTableScroll(visibleCount);
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => applyFilters());
        }

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                tabButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeTab = btn.dataset.tab || 'all';
                applyFilters();
            });
        });

        applyFilters();
    });
        </script>
    </main>

    </body>
    </html>
