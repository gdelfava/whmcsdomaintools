<?php
require_once 'api.php';
require_once 'user_settings.php';
require_once 'cache.php';

// Require authentication
// requireAuth(); // This line is removed as per the edit hint.

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Direct access forbidden']);
    exit;
}

try {
    // Check if user has settings configured
    $hasSettings = userHasSettings();
    $userSettings = getUserSettings();
    
    // Get servers data - same logic as ajax-content.php
    $serversData = [];
    if ($hasSettings && $userSettings) {
        // Clear any cached server data first
        $cache = new SimpleCache();
        $cache->delete('servers_' . md5($userSettings['api_url'] . $userSettings['api_identifier']), $_SESSION['user_email']);
        
        // Get fresh servers from WHMCS
        $serversResponse = curlCall($userSettings['api_url'], [
            'action' => 'GetServers',
            'identifier' => $userSettings['api_identifier'],
            'secret' => $userSettings['api_secret'],
            'responsetype' => 'json'
        ]);
        
        if (isset($serversResponse['result']) && $serversResponse['result'] === 'success' && isset($serversResponse['servers'])) {
            $servers = $serversResponse['servers'];
            
            // Handle both single server and multiple servers
            if (isset($servers['server'])) {
                $serverList = $servers['server'];
                
                // If single server, wrap in array
                if (isset($serverList['name'])) {
                    $serverList = [$serverList];
                }
                
                foreach ($serverList as $server) {
                    $serversData[] = [
                        'name' => $server['name'] ?? 'Unknown Server',
                        'hostname' => $server['hostname'] ?? 'N/A', 
                        'ipaddress' => $server['ipaddress'] ?? 'N/A',
                        'active' => isset($server['active']) && $server['active'] == '1',
                        'module' => $server['type'] ?? 'unknown',
                        'activeServices' => $server['statusaddress'] ?? '0',
                        'maxAllowedServices' => $server['maxaccounts'] ?? '100',
                        'percentUsed' => isset($server['maxaccounts']) && $server['maxaccounts'] > 0 ? 
                            round((($server['statusaddress'] ?? 0) / $server['maxaccounts']) * 100) : 0
                    ];
                }
            }
        }
    }
    
    // Generate the HTML content for servers
    ob_start();
    ?>
    <?php if (!empty($serversData)): ?>
        <div class="space-y-2">
            <?php foreach ($serversData as $server): ?>
                <div class="flex items-center space-x-3 p-2 bg-gray-50 rounded-lg">
                    <div class="w-6 h-6 <?= $server['active'] ? 'bg-green-100' : 'bg-red-100' ?> rounded-full flex items-center justify-center">
                        <i data-lucide="<?= $server['active'] ? 'server' : 'server-off' ?>" class="w-3 h-3 <?= $server['active'] ? 'text-green-600' : 'text-red-600' ?>"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center space-x-2">
                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($server['name']) ?></p>
                            <?php if (!empty($server['module'])): ?>
                                <span class="text-xs text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full">
                                    <?= htmlspecialchars(ucfirst($server['module'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($server['hostname']) ?> • <?= htmlspecialchars($server['ipaddress']) ?></p>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-medium text-gray-900"><?= $server['activeServices'] ?>/<?= $server['maxAllowedServices'] ?></div>
                        <div class="w-12 bg-gray-200 rounded-full h-1 mt-1">
                            <div class="bg-primary-600 h-1 rounded-full" style="width: <?= $server['percentUsed'] ?>%"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-4">
            <i data-lucide="server" class="w-8 h-8 text-gray-400 mx-auto mb-2"></i>
            <p class="text-xs text-gray-500">No server data available</p>
        </div>
    <?php endif; ?>
    <?php
    $content = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $content,
        'count' => count($serversData),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 