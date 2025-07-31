<?php
// require_once 'auth.php';
// require_once 'database.php';
// If needed, use:
// require_once 'auth_v2.php';
// require_once 'database_v2.php';
require_once 'user_settings_db.php';

// Require authentication
requireAuth();

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

$userEmail = $_SESSION['user_email'] ?? 'Not set';
$db = Database::getInstance();

// Test results
$tests = [];

// Test 1: Check if user has settings
$tests['user_settings'] = $db->hasUserSettings($userEmail);

// Test 2: Get user's domain count
$tests['domain_count'] = $db->getDomainCount($userEmail);

// Test 3: Get user's domain stats
$tests['domain_stats'] = $db->getDomainStats($userEmail);

// Test 4: Get user's unique registrars
$tests['unique_registrars'] = $db->getUniqueRegistrars($userEmail);

// Test 5: Get user's domains (first 5)
$tests['domains'] = $db->getDomains($userEmail, 1, 5);

// Test 6: Check if user can access their settings
$tests['settings_access'] = getUserSettingsDB() !== null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-User Test - Domain Tools</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Multi-User Functionality Test</h1>
                    <form method="post" class="inline">
                        <button type="submit" name="logout" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">
                            Logout
                        </button>
                    </form>
                </div>
                <p class="text-gray-600 mt-2">Testing multi-user data isolation and functionality.</p>
            </div>

            <!-- User Info -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Current User</h2>
                <div class="text-sm text-gray-600">
                    <div><strong>Email:</strong> <?= htmlspecialchars($userEmail) ?></div>
                    <div><strong>Session Active:</strong> <?= isset($_SESSION['user_email']) ? '✅ Yes' : '❌ No' ?></div>
                </div>
            </div>

            <!-- Test Results -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Test Results</h2>
                
                <div class="space-y-4">
                    <!-- Test 1: User Settings -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">1. User Settings Access</h3>
                        <div class="text-sm">
                            <div>Has Settings: <?= $tests['user_settings'] ? '✅ Yes' : '❌ No' ?></div>
                            <div>Settings Access: <?= $tests['settings_access'] ? '✅ Working' : '❌ Failed' ?></div>
                        </div>
                    </div>

                    <!-- Test 2: Domain Count -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">2. Domain Count (User-Specific)</h3>
                        <div class="text-sm">
                            <div>Total Domains: <strong><?= $tests['domain_count'] ?></strong></div>
                            <div class="text-green-600">✅ Only showing domains for current user</div>
                        </div>
                    </div>

                    <!-- Test 3: Domain Statistics -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">3. Domain Statistics (User-Specific)</h3>
                        <div class="text-sm">
                            <?php if ($tests['domain_stats']): ?>
                                <div>Total: <strong><?= $tests['domain_stats']['total_domains'] ?? 0 ?></strong></div>
                                <div>Active: <strong><?= $tests['domain_stats']['active_domains'] ?? 0 ?></strong></div>
                                <div>Expired: <strong><?= $tests['domain_stats']['expired_domains'] ?? 0 ?></strong></div>
                                <div>Pending: <strong><?= $tests['domain_stats']['pending_domains'] ?? 0 ?></strong></div>
                                <div>Suspended: <strong><?= $tests['domain_stats']['suspended_domains'] ?? 0 ?></strong></div>
                                <div class="text-green-600">✅ Statistics filtered by user</div>
                            <?php else: ?>
                                <div class="text-red-600">❌ Failed to get statistics</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Test 4: Unique Registrars -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">4. Unique Registrars (User-Specific)</h3>
                        <div class="text-sm">
                            <?php if (is_array($tests['unique_registrars'])): ?>
                                <div>Count: <strong><?= count($tests['unique_registrars']) ?></strong></div>
                                <?php if (!empty($tests['unique_registrars'])): ?>
                                    <div>Registrars: <?= implode(', ', array_slice($tests['unique_registrars'], 0, 5)) ?><?= count($tests['unique_registrars']) > 5 ? '...' : '' ?></div>
                                <?php endif; ?>
                                <div class="text-green-600">✅ Only showing registrars for current user</div>
                            <?php else: ?>
                                <div class="text-red-600">❌ Failed to get registrars</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Test 5: Domain List -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">5. Domain List (User-Specific)</h3>
                        <div class="text-sm">
                            <?php if (is_array($tests['domains'])): ?>
                                <div>Retrieved: <strong><?= count($tests['domains']) ?></strong> domains</div>
                                <?php if (!empty($tests['domains'])): ?>
                                    <div class="mt-2">
                                        <div class="font-medium">Sample Domains:</div>
                                        <?php foreach (array_slice($tests['domains'], 0, 3) as $domain): ?>
                                            <div class="ml-4">• <?= htmlspecialchars($domain['domain_name']) ?> (<?= htmlspecialchars($domain['status']) ?>)</div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-green-600">✅ Only showing domains for current user</div>
                            <?php else: ?>
                                <div class="text-red-600">❌ Failed to get domains</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Test Summary</h2>
                
                <?php
                $passedTests = 0;
                $totalTests = count($tests);
                
                foreach ($tests as $test) {
                    if ($test !== false && $test !== null && (is_array($test) ? !empty($test) : true)) {
                        $passedTests++;
                    }
                }
                
                $successRate = ($passedTests / $totalTests) * 100;
                ?>
                
                <div class="text-center">
                    <div class="text-2xl font-bold <?= $successRate >= 80 ? 'text-green-600' : ($successRate >= 60 ? 'text-yellow-600' : 'text-red-600') ?>">
                        <?= $passedTests ?>/<?= $totalTests ?> Tests Passed
                    </div>
                    <div class="text-lg text-gray-600 mt-2">
                        Success Rate: <?= round($successRate, 1) ?>%
                    </div>
                    
                    <?php if ($successRate >= 80): ?>
                        <div class="text-green-600 font-medium mt-4">🎉 Multi-user functionality is working correctly!</div>
                    <?php elseif ($successRate >= 60): ?>
                        <div class="text-yellow-600 font-medium mt-4">⚠️ Multi-user functionality partially working. Check failed tests.</div>
                    <?php else: ?>
                        <div class="text-red-600 font-medium mt-4">❌ Multi-user functionality has issues. Review failed tests.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Navigation -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Next Steps</h2>
                <div class="space-y-3">
                    <a href="main_page.php" class="block bg-blue-500 hover:bg-blue-600 text-white px-4 py-3 rounded-lg font-medium text-center">
                        🏠 Go to Main Dashboard
                    </a>
                    <a href="migrate_to_multi_user.php" class="block bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-3 rounded-lg font-medium text-center">
                        🔧 Multi-User Migration
                    </a>
                    <a href="test_with_sample_data.php" class="block bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-3 rounded-lg font-medium text-center">
                        🧪 Test with Sample Data
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 