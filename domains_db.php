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
        $userEmail = $_SESSION['user_email'] ?? '';
        $domains = $db->getDomains($userEmail, $page, $perPage, $search, $status, $orderBy, $orderDir);
        $totalDomains = $db->getDomainCount($userEmail, $search, $status);
        $domainStats = $db->getDomainStats($userEmail);
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
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Sora', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen">
        <!-- Mobile overlay -->
        <div class="lg:hidden fixed inset-0 bg-black bg-opacity-50 z-30 hidden" id="sidebar-overlay"></div>
        
        <!-- Sidebar -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col lg:translate-x-0 -translate-x-full transition-transform duration-300 fixed lg:relative z-40 h-full">
            <!-- Logo -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                        <i data-lucide="globe" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">Domain Tools</h1>
                        <p class="text-xs text-gray-500">Management Suite</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 flex flex-col">
                <div class="flex-1">
                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">MENU</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="main_page.php" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="layout-dashboard" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Dashboard</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">DOMAIN ACTIONS</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="sync_interface.php" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="refresh-cw" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Domain Sync</span>
                                </a>
                            </li>
                            <li>
                                <a href="main_page.php?view=nameservers" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="server" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Update Nameservers</span>
                                </a>
                            </li>
                            <li>
                                <a href="main_page.php?view=export" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="download" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Export Domains</span>
                                </a>
                            </li>
                            <li>
                                <a href="domains_db.php" class="flex items-center space-x-3 px-3 py-2 bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600">
                                    <i data-lucide="database" class="w-4 h-4 text-primary-600"></i>
                                    <span class="text-sm font-semibold text-gray-900">Database View</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">SERVER SETUP</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="main_page.php?view=database_setup" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="database" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Database Setup</span>
                                </a>
                            </li>
                            <li>
                                <a href="main_page.php?view=create_tables" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="table" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Create Tables</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-auto">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">GENERAL</h3>
                    <ul class="space-y-1">
                        <li>
                            <a href="main_page.php?view=settings" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                <i data-lucide="settings" class="w-4 h-4 text-gray-400"></i>
                                <span class="text-sm font-normal">Settings</span>
                            </a>
                        </li>
                        <li>
                            <a href="main_page.php" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                <i data-lucide="arrow-left" class="w-4 h-4 text-gray-400"></i>
                                <span class="text-sm font-normal">Back to Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center mr-3">
                            <i data-lucide="database" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-900">Database View</h1>
                            <p class="text-sm text-gray-500">View and manage domains from local database</p>
                        </div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">A</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($_SESSION['user_email'] ?? 'User') ?></p>
                                <p class="text-xs text-gray-500">Administrator</p>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">🌐 Domains Database</h1>
                    <p class="text-gray-600">View and manage domains from local database</p>
                </div>

                <!-- Error Message -->
                <?php if (isset($error)): ?>
                    <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                            <div>
                                <div class="font-semibold">Database Error</div>
                                <div class="text-sm mt-1"><?= htmlspecialchars($error) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Sync Status -->
                <?php if ($lastSync): ?>
                    <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="refresh-cw" class="w-5 h-5 text-blue-600"></i>
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
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Domains</label>
                            <input 
                                type="text" 
                                name="search" 
                                id="search" 
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Enter domain name..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status Filter</label>
                            <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                <option value="">All Statuses</option>
                                <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Expired" <?= $status === 'Expired' ? 'selected' : '' ?>>Expired</option>
                                <option value="Suspended" <?= $status === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="order_by" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                            <select name="order_by" id="order_by" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                <option value="domain_name" <?= $orderBy === 'domain_name' ? 'selected' : '' ?>>Domain Name</option>
                                <option value="status" <?= $orderBy === 'status' ? 'selected' : '' ?>>Status</option>
                                <option value="registrar" <?= $orderBy === 'registrar' ? 'selected' : '' ?>>Registrar</option>
                                <option value="expiry_date" <?= $orderBy === 'expiry_date' ? 'selected' : '' ?>>Expiry Date</option>
                                <option value="last_synced" <?= $orderBy === 'last_synced' ? 'selected' : '' ?>>Last Synced</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="order_dir" class="block text-sm font-medium text-gray-700 mb-2">Sort Direction</label>
                            <select name="order_dir" id="order_dir" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                <option value="ASC" <?= $orderDir === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                                <option value="DESC" <?= $orderDir === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-4 flex gap-4">
                            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                                <i data-lucide="search" class="w-4 h-4"></i>
                                <span>Search</span>
                            </button>
                            <a href="domains_db.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                <span>Clear Filters</span>
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
                                <a href="export_domains.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                    <span>Export CSV</span>
                                </a>
                                <a href="domain_sync.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                    <span>Sync Data</span>
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
                                <a href="domain_sync.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 inline-flex">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                    <span>Sync Domains</span>
                                </a>
                            <?php else: ?>
                                <a href="domains_db.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 inline-flex">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                    <span>Clear Filters</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html> 