// Cache Modal JavaScript
class CacheModal {
    constructor() {
        this.modal = null;
        this.init();
    }

    init() {
        // Create modal HTML
        this.createModal();
        
        // Add event listeners
        this.addEventListeners();
    }

    createModal() {
        const modalHTML = `
            <div id="cacheModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center space-x-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <span>Clear Domain Cache</span>
                            </h3>
                            <button id="closeCacheModal" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Cache Statistics -->
                        <div class="mb-6">
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="bg-gray-50 rounded-lg p-3 text-center">
                                    <div class="text-sm font-medium text-gray-500">Total Cache Files</div>
                                    <div class="text-xl font-bold text-gray-900" id="cacheTotalFiles">-</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3 text-center">
                                    <div class="text-sm font-medium text-gray-500">Cache Size</div>
                                    <div class="text-xl font-bold text-gray-900" id="cacheSize">-</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3 text-center">
                                    <div class="text-sm font-medium text-gray-500">Expired Files</div>
                                    <div class="text-xl font-bold text-gray-900" id="cacheExpiredFiles">-</div>
                                </div>
                            </div>
                            
                            <p class="text-gray-600 mb-4">
                                This will clear all cached domain data and force a fresh fetch from your WHMCS API. 
                                The domain list will be refreshed with proper alphabetical sorting.
                            </p>
                            
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <div class="flex items-start space-x-3">
                                    <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    <div>
                                        <h4 class="font-semibold text-yellow-800 mb-1">Important Note</h4>
                                        <p class="text-sm text-yellow-700">
                                            Clearing the cache will temporarily slow down the next domain list load as it fetches fresh data from your WHMCS API. 
                                            This is normal and will improve performance on subsequent loads.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex space-x-3">
                            <button id="clearCacheBtn" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                <span>Clear Cache</span>
                            </button>
                            <button id="cancelCacheBtn" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors">
                                Cancel
                            </button>
                        </div>

                        <!-- Loading State -->
                        <div id="cacheLoading" class="hidden mt-4 text-center">
                            <div class="inline-flex items-center space-x-2">
                                <div class="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                                <span class="text-sm text-gray-600">Clearing cache...</span>
                            </div>
                        </div>

                        <!-- Success Message -->
                        <div id="cacheSuccess" class="hidden mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-sm text-green-700">Cache cleared successfully!</span>
                            </div>
                        </div>

                        <!-- Error Message -->
                        <div id="cacheError" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm text-red-700" id="cacheErrorMessage">Error clearing cache</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('cacheModal');
    }

    addEventListeners() {
        // Close modal events
        document.getElementById('closeCacheModal').addEventListener('click', () => this.hide());
        document.getElementById('cancelCacheBtn').addEventListener('click', () => this.hide());
        
        // Clear cache button
        document.getElementById('clearCacheBtn').addEventListener('click', () => this.clearCache());
        
        // Close modal when clicking outside
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.hide();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.modal.classList.contains('hidden')) {
                this.hide();
            }
        });
    }

    show() {
        this.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Load cache statistics when modal opens
        this.loadCacheStats();
    }

    hide() {
        this.modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        
        // Reset states
        this.hideAllStates();
    }

    hideAllStates() {
        document.getElementById('cacheLoading').classList.add('hidden');
        document.getElementById('cacheSuccess').classList.add('hidden');
        document.getElementById('cacheError').classList.add('hidden');
        document.getElementById('clearCacheBtn').disabled = false;
    }

    showLoading() {
        this.hideAllStates();
        document.getElementById('cacheLoading').classList.remove('hidden');
        document.getElementById('clearCacheBtn').disabled = true;
    }

    showSuccess() {
        this.hideAllStates();
        document.getElementById('cacheSuccess').classList.remove('hidden');
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            this.hide();
        }, 3000);
    }

    showError(message) {
        this.hideAllStates();
        document.getElementById('cacheErrorMessage').textContent = message;
        document.getElementById('cacheError').classList.remove('hidden');
    }

    async loadCacheStats() {
        try {
            const response = await fetch('ajax-clear-cache.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_stats'
                })
            });

            const result = await response.json();

            if (result.success) {
                document.getElementById('cacheTotalFiles').textContent = result.stats.total_files || 0;
                document.getElementById('cacheSize').textContent = result.stats.total_size || '0 KB';
                document.getElementById('cacheExpiredFiles').textContent = result.stats.expired_files || 0;
            }
        } catch (error) {
            console.error('Failed to load cache stats:', error);
        }
    }

    async clearCache() {
        this.showLoading();

        try {
            const response = await fetch('ajax-clear-cache.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'clear_cache'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess();
                
                // Update cache statistics after clearing
                this.loadCacheStats();
                
                // Optionally refresh the page or reload domain list
                if (typeof reloadDomainList === 'function') {
                    reloadDomainList();
                }
            } else {
                this.showError(result.error || 'Failed to clear cache');
            }
        } catch (error) {
            this.showError('Network error: ' + error.message);
        }
    }
}

// Initialize cache modal when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.cacheModal = new CacheModal();
});

// Function to show cache modal (can be called from other scripts)
function showCacheModal() {
    if (window.cacheModal) {
        window.cacheModal.show();
    }
} 