// Single Page Application Router for fast navigation
class SPARouter {
    constructor() {
        this.routes = {};
        this.currentView = '';
        this.cache = new Map();
        this.loadingElement = null;
        this.contentContainer = null;
        
        this.init();
    }
    
    init() {
        // Get initial view from URL
        const urlParams = new URLSearchParams(window.location.search);
        this.currentView = urlParams.get('view') || 'dashboard';
        
        // Create loading overlay
        this.createLoadingOverlay();
        
        // Find content container
        this.contentContainer = document.querySelector('main');
        
        // Set up click handlers for navigation
        this.setupNavigation();
        
        // Handle browser back/forward
        window.addEventListener('popstate', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const view = urlParams.get('view') || 'dashboard';
            const page = urlParams.get('page');
            this.navigateTo(view, false, page);
        });
    }
    
    createLoadingOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'spa-loading';
        overlay.className = 'fixed inset-0 bg-white bg-opacity-80 flex items-center justify-center z-50 hidden';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                <p class="text-gray-600 text-sm">Loading...</p>
            </div>
        `;
        document.body.appendChild(overlay);
        this.loadingElement = overlay;
    }
    
    setupNavigation() {
        // Handle sidebar navigation clicks and pagination
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href*="?view="]');
            if (link) {
                e.preventDefault();
                const url = new URL(link.href);
                const view = url.searchParams.get('view');
                const page = url.searchParams.get('page');
                if (view) {
                    this.navigateTo(view, true, page);
                }
            }
        });
    }
    
    showLoading() {
        if (this.loadingElement) {
            this.loadingElement.classList.remove('hidden');
        }
    }
    
    hideLoading() {
        if (this.loadingElement) {
            this.loadingElement.classList.add('hidden');
        }
    }
    
    async navigateTo(view, updateHistory = true, page = null) {
        // Check if we're navigating to the same view and page
        const currentUrl = new URL(window.location);
        const currentPage = currentUrl.searchParams.get('page');
        if (view === this.currentView && page === currentPage) return;
        
        // Skip loading animation for dashboard to avoid user frustration
        const shouldShowLoading = view !== 'dashboard';
        
        if (shouldShowLoading) {
            this.showLoading();
        }
        
        try {
            // Update URL without page reload
            if (updateHistory) {
                const newUrl = new URL(window.location);
                newUrl.searchParams.set('view', view);
                if (page) {
                    newUrl.searchParams.set('page', page);
                } else {
                    newUrl.searchParams.delete('page');
                }
                history.pushState({ view, page }, '', newUrl);
            }
            
            // Update active navigation item immediately for better responsiveness
            this.updateActiveNavigation(view);
            
            // Load content
            const content = await this.loadViewContent(view, page);
            
            // Update content
            if (content && this.contentContainer) {
                this.contentContainer.innerHTML = content;
                
                // Re-initialize components for new content
                this.initializeViewComponents(view);
                
                // Update page title
                this.updatePageTitle(view);
            }
            
            this.currentView = view;
            
        } catch (error) {
            console.error('Navigation error:', error);
            this.showError('Failed to load page content');
        } finally {
            if (shouldShowLoading) {
                this.hideLoading();
            }
        }
    }
    
    async loadViewContent(view, page = null) {
        // Create cache key that includes page for pagination
        const cacheKey = page ? `view_${view}_page_${page}` : `view_${view}`;
        if (this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            // Cache dashboard for longer (15 minutes) to make it instant
            const cacheTime = view === 'dashboard' ? 900000 : 300000; // 15 min vs 5 min
            if (Date.now() - cached.timestamp < cacheTime) {
                return cached.content;
            }
        }
        
        try {
            // Build URL with page parameter if provided
            const url = new URL('ajax-content.php', window.location.origin);
            url.searchParams.set('view', view);
            if (page) {
                url.searchParams.set('page', page);
            }
            
            // Fetch content via AJAX
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                // Handle authentication errors
                if (response.status === 401) {
                    const data = await response.json().catch(() => ({}));
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                }
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Cache the content (dashboard gets cached longer)
                this.cache.set(cacheKey, {
                    content: data.html,
                    timestamp: Date.now()
                });
                
                return data.html;
            } else {
                // Handle authentication redirect
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                throw new Error(data.error || 'Unknown error');
            }
            
        } catch (error) {
            console.error('Failed to load view content:', error);
            return this.getErrorContent(view);
        }
    }
    
    updateActiveNavigation(view) {
        // Remove active classes from all nav items
        document.querySelectorAll('nav a').forEach(link => {
            link.classList.remove('bg-primary-50', 'text-primary-700', 'border-l-4', 'border-primary-600');
            link.classList.add('text-gray-500', 'hover:bg-gray-50');
            
            const icon = link.querySelector('i');
            if (icon) {
                icon.classList.remove('text-primary-600');
                icon.classList.add('text-gray-400');
            }
            
            const span = link.querySelector('span');
            if (span) {
                span.classList.remove('font-semibold', 'text-gray-900');
                span.classList.add('font-normal');
            }
        });
        
        // Add active classes to current view
        const activeLink = document.querySelector(`nav a[href*="view=${view}"]`);
        if (activeLink) {
            activeLink.classList.remove('text-gray-500', 'hover:bg-gray-50');
            activeLink.classList.add('bg-primary-50', 'text-primary-700', 'border-l-4', 'border-primary-600');
            
            const icon = activeLink.querySelector('i');
            if (icon) {
                icon.classList.remove('text-gray-400');
                icon.classList.add('text-primary-600');
            }
            
            const span = activeLink.querySelector('span');
            if (span) {
                span.classList.add('font-semibold', 'text-gray-900');
                span.classList.remove('font-normal');
            }
        }
    }
    
    initializeViewComponents(view) {
        // Re-initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        
        // Re-run view-specific initialization
        if (view === 'nameservers') {
            // Re-initialize nameserver form handlers
            if (typeof setupMainPageHandlers === 'function') {
                setupMainPageHandlers();
            }
        }
        
        if (view === 'domains') {
            // Re-initialize domain filters
            this.initializeDomainFilters();
        }
        
        if (view === 'settings') {
            // Re-initialize settings form
            if (typeof updateLogoPreview === 'function') {
                updateLogoPreview();
            }
        }
        
        // Re-initialize dashboard components
        if (typeof DashboardUtils !== 'undefined') {
            if (view === 'dashboard') {
                // Re-initialize dashboard animations
                setTimeout(() => {
                    this.initializeDashboardAnimations();
                }, 100);
            }
        }
    }
    
    initializeDomainFilters() {
        const searchInput = document.getElementById('domainSearch');
        const registrarFilter = document.getElementById('registrarFilter');
        const expiryFilter = document.getElementById('expiryFilter');
        const statusFilter = document.getElementById('statusFilter');
        const clearFiltersBtn = document.getElementById('clearFilters');
        
        if (searchInput || registrarFilter) {
            // Re-run the domain filtering logic from main_page.php
            setTimeout(() => {
                const script = document.querySelector('script:last-of-type');
                if (script && script.textContent.includes('domainSearch')) {
                    eval(script.textContent);
                }
            }, 100);
        }
    }
    
    initializeDashboardAnimations() {
        const statCards = document.querySelectorAll('.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 > div');
        
        statCards.forEach((card, index) => {
            card.style.transform = 'translateY(20px)';
            card.style.opacity = '0';
            card.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                card.style.transform = 'translateY(0)';
                card.style.opacity = '1';
            }, index * 100);
        });
    }
    
    updatePageTitle(view) {
        const titles = {
            'dashboard': 'Dashboard',
            'domains': 'Domains',
            'nameservers': 'Update Nameservers',
            'export': 'Export Domains',
            'settings': 'Settings'
        };
        
        const title = titles[view] || 'Dashboard';
        document.title = `WHMCS Domain Tools - ${title}`;
    }
    
    getErrorContent(view) {
        return `
            <div class="text-center py-12">
                <i data-lucide="alert-triangle" class="w-12 h-12 text-red-500 mx-auto mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Failed to load content</h3>
                <p class="text-gray-500 mb-4">There was an error loading the ${view} page.</p>
                <button onclick="location.reload()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg">
                    Reload Page
                </button>
            </div>
        `;
    }
    
    showError(message) {
        if (typeof DashboardUtils !== 'undefined' && DashboardUtils.showNotification) {
            DashboardUtils.showNotification(message, 'error');
        } else {
            alert(message);
        }
    }
    
    // Clear cache when needed
    clearCache() {
        this.cache.clear();
    }
    
    // Force refresh current view (for development)
    forceRefresh() {
        this.cache.clear();
        if (this.currentView) {
            this.navigateTo(this.currentView, false);
        }
    }
    
    // Preload a view
    async preloadView(view) {
        await this.loadViewContent(view);
    }
    
    // Refresh servers function - available globally
    async refreshServers() {
        console.log('Refresh servers clicked!');
        
        const refreshBtn = document.getElementById('refresh-servers-btn');
        const serversContent = document.getElementById('servers-content');
        
        console.log('Button:', refreshBtn, 'Content:', serversContent);
        
        if (!refreshBtn || !serversContent) {
            console.error('Required elements not found');
            alert('Error: Required elements not found');
            return;
        }
        
        // Show loading state
        const originalText = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i><span>Loading...</span>';
        refreshBtn.disabled = true;
        
        // Re-initialize icons for loading spinner
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        
        try {
            const response = await fetch('ajax-servers.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Server response:', data);
            
            if (data.success) {
                // Update the servers content
                serversContent.innerHTML = data.html;
                
                // Update the server count badge
                const badge = document.querySelector('#servers-card .bg-gray-100');
                if (badge && data.count > 0) {
                    badge.textContent = data.count + ' server' + (data.count !== 1 ? 's' : '');
                    badge.style.display = 'inline-block';
                } else if (badge) {
                    badge.style.display = 'none';
                }
                
                // Re-initialize icons for the new content
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                
                // Show success notification
                if (typeof DashboardUtils !== 'undefined' && DashboardUtils.showNotification) {
                    DashboardUtils.showNotification('Server data refreshed successfully', 'success');
                } else {
                    console.log('Server data refreshed successfully');
                }
            } else {
                throw new Error(data.error || 'Failed to refresh server data');
            }
            
        } catch (error) {
            console.error('Error refreshing servers:', error);
            alert('Failed to refresh server data: ' + error.message);
        } finally {
            // Restore button state
            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
            
            // Re-initialize icons for the button
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }
}

// Global router instance
window.spaRouter = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on the main page
    if (document.querySelector('main') && !window.spaRouter) {
        window.spaRouter = new SPARouter();
        
        // Make refreshServers globally available
        window.refreshServers = function() {
            if (window.spaRouter) {
                window.spaRouter.refreshServers();
            }
        };
        
        // Preload common views after initial load (prioritize dashboard)
        setTimeout(() => {
            if (window.spaRouter) {
                // Preload dashboard first for instant access
                window.spaRouter.preloadView('dashboard');
                // Then preload other common views
                setTimeout(() => {
                    window.spaRouter.preloadView('domains');
                    window.spaRouter.preloadView('nameservers');
                }, 500);
            }
        }, 1000);
    }
}); 