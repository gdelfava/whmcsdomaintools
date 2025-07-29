<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'user_settings.php';

// Require authentication
requireAuth();

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

// Initialize database
try {
    $db = Database::getInstance();
    $db->createTables(); // Ensure tables exist
} catch (Exception $e) {
    $error = "Database connection failed: " . $e->getMessage();
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$orderBy = $_GET['order_by'] ?? 'domain_name';
$orderDir = $_GET['order_dir'] ?? 'ASC';

// Validate order by field
$allowedOrderBy = ['domain_name', 'status', 'registrar', 'expiry_date', 'last_synced'];
if (!in_array($orderBy, $allowedOrderBy)) {
    $orderBy = 'domain_name';
}

// Validate order direction
$orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

// Get domains from database
$domains = [];
$totalDomains = 0;
$domainStats = [];

if (!isset($error)) {
    try {
        $domains = $db->getDomains($page, $perPage, $search, $status, $orderBy, $orderDir);
        $totalDomains = $db->getDomainCount($search, $status);
        $domainStats = $db->getDomainStats();
    } catch (Exception $e) {
        $error = "Failed to fetch domains: " . $e->getMessage();
    }
}

$totalPages = ceil($totalDomains / $perPage);
$offset = ($page - 1) * $perPage;

// Get last sync information
$lastSync = null;
if (!isset($error)) {
    try {
        $lastSync = $db->getLastSyncInfo($_SESSION['user_email'] ?? '');
    } catch (Exception $e) {
        // Don't fail the page if sync info fails
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Domains Database</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
    <div class="page-wrapper">
        <div class="container">
            <!-- Navigation Bar -->
            <nav class="navbar">
                <div class="navbar-brand">
                    <img src="<?= htmlspecialchars(getLogoUrl()) ?>" alt="Logo" onerror="this.style.display='none'">
                    <div>
                        <div class="font-semibold text-gray-900">WHMCS Domain Tools</div>
                        <div class="text-xs text-gray-500">Database View</div>
                    </div>
                </div>
                <div class="navbar-user">
                    <span>Logged in as <span class="user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? 'User') ?></span></span>
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="logout" class="logout-btn">Logout</button>
                    </form>
                </div>
            </nav>

            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="main_page.php">Dashboard</a>
                <span class="breadcrumb-separator">/</span>
                <span>Domains Database</span>
            </nav>

            <!-- Main Card -->
            <div class="main-card">
                <!-- Card Header -->
                <div class="card-header">
                    <div class="card-header-content">
                        <h1 class="page-title">🌐 Domains Database</h1>
                        <p class="page-subtitle">View and manage domains from local database</p>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-error mb-6">
                            <div class="flex items-center gap-3">
                                <span style="font-size: 1.5rem;">❌</span>
                                <div>
                                    <div class="font-semibold">Database Error</div>
                                    <div class="text-sm mt-1"><?= htmlspecialchars($error) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sync Status -->
                    <?php if ($lastSync): ?>
                        <div class="alert alert-info mb-6">
                            <div class="flex items-center gap-3">
                                <span style="font-size: 1.5rem;">🔄</span>
                                <div>
                                    <div class="font-semibold">Last Sync: <?= date('M j, Y g:i A', strtotime($lastSync['sync_started'])) ?></div>
                                    <div class="text-sm mt-1">
                                        Batch <?= $lastSync['batch_number'] ?> - 
                                        <?= $lastSync['domains_processed'] ?> domains processed
                                        (<?= $lastSync['domains_added'] ?> added, <?= $lastSync['domains_updated'] ?> updated)
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Domain Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-primary-600 text-white p-6 rounded-xl">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium text-primary-100">Total Domains</h3>
                                <div class="w-8 h-8 bg-primary-500 rounded-full flex items-center justify-center">
                                    <i data-lucide="globe" class="w-4 h-4"></i>
                                </div>
                            </div>
                            <div class="text-4xl font-bold"><?= $domainStats['total_domains'] ?? 0 ?></div>
                        </div>

                        <div class="bg-white p-6 rounded-xl border border-gray-200">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium text-gray-500">Active Domains</h3>
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="check-circle" class="w-4 h-4 text-green-600"></i>
                                </div>
                            </div>
                            <div class="text-4xl font-bold text-gray-900"><?= $domainStats['active_domains'] ?? 0 ?></div>
                        </div>

                        <div class="bg-white p-6 rounded-xl border border-gray-200">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium text-gray-500">Expired Domains</h3>
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="alert-triangle" class="w-4 h-4 text-red-600"></i>
                                </div>
                            </div>
                            <div class="text-4xl font-bold text-gray-900"><?= $domainStats['expired_domains'] ?? 0 ?></div>
                        </div>

                        <div class="bg-white p-6 rounded-xl border border-gray-200">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium text-gray-500">Pending Domains</h3>
                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="clock" class="w-4 h-4 text-yellow-600"></i>
                                </div>
                            </div>
                            <div class="text-4xl font-bold text-gray-900"><?= $domainStats['pending_domains'] ?? 0 ?></div>
                        </div>
                    </div>

                    <!-- Search and Filter Controls -->
                    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="form-group">
                                <label for="search" class="form-label">Search Domains</label>
                                <input 
                                    type="text" 
                                    name="search" 
                                    id="search" 
                                    value="<?= htmlspecialchars($search) ?>"
                                    placeholder="Enter domain name..."
                                    class="form-input"
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="status" class="form-label">Status Filter</label>
                                <select name="status" id="status" class="form-input">
                                    <option value="">All Statuses</option>
                                    <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Expired" <?= $status === 'Expired' ? 'selected' : '' ?>>Expired</option>
                                    <option value="Suspended" <?= $status === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                                    <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="order_by" class="form-label">Sort By</label>
                                <select name="order_by" id="order_by" class="form-input">
                                    <option value="domain_name" <?= $orderBy === 'domain_name' ? 'selected' : '' ?>>Domain Name</option>
                                    <option value="status" <?= $orderBy === 'status' ? 'selected' : '' ?>>Status</option>
                                    <option value="registrar" <?= $orderBy === 'registrar' ? 'selected' : '' ?>>Registrar</option>
                                    <option value="expiry_date" <?= $orderBy === 'expiry_date' ? 'selected' : '' ?>>Expiry Date</option>
                                    <option value="last_synced" <?= $orderBy === 'last_synced' ? 'selected' : '' ?>>Last Synced</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="order_dir" class="form-label">Sort Direction</label>
                                <select name="order_dir" id="order_dir" class="form-input">
                                    <option value="ASC" <?= $orderDir === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                                    <option value="DESC" <?= $orderDir === 'DESC' ? 'selected' : '' ?>>Descending</option>
                                </select>
                            </div>
                            
                            <div class="md:col-span-4 flex gap-4">
                                <button type="submit" class="btn btn-primary">
                                    <i data-lucide="search" class="w-4 h-4 mr-2"></i>
                                    Search
                                </button>
                                <a href="domains_db.php" class="btn btn-secondary">
                                    <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>
                                    Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Domains Table -->
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">Domains (<?= $totalDomains ?> total)</h3>
                                <div class="flex items-center space-x-4">
                                    <a href="export_domains.php" class="btn btn-secondary">
                                        <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                        Export CSV
                                    </a>
                                    <a href="domain_sync.php" class="btn btn-primary">
                                        <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>
                                        Sync Data
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($domains)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registrar</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nameservers</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Synced</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($domains as $domain): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center mr-3">
                                                            <i data-lucide="globe" class="w-4 h-4 text-primary-600"></i>
                                                        </div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($domain['domain_name']) ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?= htmlspecialchars($domain['registrar'] ?? 'Unknown') ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $status = $domain['status'] ?? 'Unknown';
                                                    $statusColors = [
                                                        'Active' => 'bg-green-100 text-green-800',
                                                        'Pending' => 'bg-yellow-100 text-yellow-800',
                                                        'Suspended' => 'bg-red-100 text-red-800',
                                                        'Cancelled' => 'bg-gray-100 text-gray-800',
                                                        'Expired' => 'bg-red-100 text-red-800'
                                                    ];
                                                    $statusColor = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                                    ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusColor ?>">
                                                        <?= htmlspecialchars($status) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php 
                                                    if (!empty($domain['expiry_date'])) {
                                                        echo date('M j, Y', strtotime($domain['expiry_date']));
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php if (!empty($domain['ns1'])): ?>
                                                        <div class="text-xs">
                                                            <div><?= htmlspecialchars($domain['ns1']) ?></div>
                                                            <?php if (!empty($domain['ns2'])): ?>
                                                                <div class="text-gray-500"><?= htmlspecialchars($domain['ns2']) ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">Not available</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= date('M j, Y g:i A', strtotime($domain['last_synced'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-gray-700">
                                            Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalDomains) ?> of <?= $totalDomains ?> domains
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <!-- Previous Page -->
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&order_by=<?= urlencode($orderBy) ?>&order_dir=<?= urlencode($orderDir) ?>" 
                                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                    Previous
                                                </a>
                                            <?php else: ?>
                                                <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                                                    Previous
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Page Numbers -->
                                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                <?php if ($i == $page): ?>
                                                    <span class="px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-primary-600 rounded-lg">
                                                        <?= $i ?>
                                                    </span>
                                                <?php else: ?>
                                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&order_by=<?= urlencode($orderBy) ?>&order_dir=<?= urlencode($orderDir) ?>" 
                                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                        <?= $i ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            
                                            <!-- Next Page -->
                                            <?php if ($page < $totalPages): ?>
                                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&order_by=<?= urlencode($orderBy) ?>&order_dir=<?= urlencode($orderDir) ?>" 
                                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                    Next
                                                </a>
                                            <?php else: ?>
                                                <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                                                    Next
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="px-6 py-12 text-center">
                                <div class="text-gray-400 mb-4">
                                    <i data-lucide="database" class="w-12 h-12 mx-auto"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No domains found</h3>
                                <p class="text-gray-500 mb-4">
                                    <?php if (!empty($search) || !empty($status)): ?>
                                        No domains match your current filters. Try adjusting your search criteria.
                                    <?php else: ?>
                                        No domains have been synced to the database yet.
                                    <?php endif; ?>
                                </p>
                                <?php if (empty($search) && empty($status)): ?>
                                    <a href="domain_sync.php" class="btn btn-primary">
                                        <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>
                                        Sync Domains
                                    </a>
                                <?php else: ?>
                                    <a href="domains_db.php" class="btn btn-secondary">
                                        <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>
                                        Clear Filters
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html> 