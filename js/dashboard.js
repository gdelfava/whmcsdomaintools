// Dashboard JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeSidebar();
    initializeSearch();
    initializeNotifications();
    initializeCharts();
    initializeAnimations();
});

// Sidebar functionality
const initializeSidebar = () => {
    const sidebar = document.querySelector('.w-64');
    const mainContent = document.querySelector('.flex-1');
    
    // Add mobile toggle button if not exists
    if (!document.querySelector('.sidebar-toggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'sidebar-toggle lg:hidden fixed top-4 left-4 z-50 bg-white p-2 rounded-lg shadow-lg border border-gray-200';
        toggleBtn.innerHTML = '<i data-lucide="menu" class="w-5 h-5"></i>';
        document.body.appendChild(toggleBtn);
        
        // Re-initialize Lucide icons to render the menu icon
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        
        toggleBtn.addEventListener('click', () => {
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            toggleBtn.classList.toggle('left-4');
            toggleBtn.classList.toggle('left-64');
            
            if (overlay) {
                overlay.classList.toggle('hidden');
            }
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth < 1024) {
            if (!sidebar.contains(e.target) && !e.target.closest('.sidebar-toggle')) {
                sidebar.classList.add('-translate-x-full');
                const toggleBtn = document.querySelector('.sidebar-toggle');
                const overlay = document.getElementById('sidebar-overlay');
                
                if (toggleBtn) {
                    toggleBtn.classList.remove('left-64');
                    toggleBtn.classList.add('left-4');
                }
                
                if (overlay) {
                    overlay.classList.add('hidden');
                }
            }
        }
    });
    
    // Close sidebar when clicking overlay
    const overlay = document.getElementById('sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            if (toggleBtn) {
                toggleBtn.classList.remove('left-64');
                toggleBtn.classList.add('left-4');
            }
            overlay.classList.add('hidden');
        });
    }
};

// Search functionality
const initializeSearch = () => {
    const searchInput = document.querySelector('input[placeholder="Search domains..."]');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.toLowerCase();
        
        searchTimeout = setTimeout(() => {
            // Filter dashboard items based on search query
            const dashboardItems = document.querySelectorAll('.bg-white');
            
            dashboardItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(query) || query === '') {
                    item.style.display = 'block';
                    item.style.opacity = '1';
                } else {
                    item.style.opacity = '0.3';
                }
            });
        }, 300);
    });
    
    // Keyboard shortcut for search
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'f') {
            e.preventDefault();
            searchInput.focus();
        }
    });
};

// Notifications functionality
const initializeNotifications = () => {
    const notificationBtn = document.querySelector('button i[data-lucide="bell"]')?.parentElement;
    if (!notificationBtn) return;
    
    notificationBtn.addEventListener('click', () => {
        showNotification('No new notifications', 'info');
    });
    
    const mailBtn = document.querySelector('button i[data-lucide="mail"]')?.parentElement;
    if (mailBtn) {
        mailBtn.addEventListener('click', () => {
            showNotification('No new messages', 'info');
        });
    }
};

// Chart initialization (placeholder for future chart library)
const initializeCharts = () => {
    const chartContainer = document.querySelector('.h-64.bg-gray-50');
    if (!chartContainer) return;
    
    // Placeholder for chart implementation
    // This would integrate with a chart library like Chart.js or ApexCharts
    console.log('Chart container ready for integration');
};

// Smooth animations
const initializeAnimations = () => {
    // Animate statistics cards on load
    const statCards = document.querySelectorAll('.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 > div');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.style.opacity = '1';
                }, index * 100);
            }
        });
    }, { threshold: 0.1 });
    
    statCards.forEach(card => {
        card.style.transform = 'translateY(20px)';
        card.style.opacity = '0';
        card.style.transition = 'all 0.5s ease';
        observer.observe(card);
    });
    
    // Hover effects for action buttons
    const actionButtons = document.querySelectorAll('button');
    actionButtons.forEach(btn => {
        btn.addEventListener('mouseenter', () => {
            btn.style.transform = 'translateY(-2px)';
        });
        
        btn.addEventListener('mouseleave', () => {
            btn.style.transform = 'translateY(0)';
        });
    });
};

// Notification system
const showNotification = (message, type = 'info') => {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification-toast fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg border max-w-sm transform translate-x-full transition-transform duration-300`;
    
    // Set notification styles based on type
    const typeStyles = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800'
    };
    
    notification.className += ` ${typeStyles[type] || typeStyles.info}`;
    
    // Add icon based on type
    const icons = {
        success: 'check-circle',
        error: 'x-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    
    notification.innerHTML = `
        <div class="flex items-center space-x-3">
            <i data-lucide="${icons[type] || icons.info}" class="w-5 h-5"></i>
            <span class="text-sm font-medium">${message}</span>
            <button class="ml-auto text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.remove()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.transform = 'translateX(full)';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
    
    // Reinitialize icons for the new notification
    lucide.createIcons();
};

// Utility functions
const formatNumber = (num) => {
    return new Intl.NumberFormat().format(num);
};

const formatDate = (date) => {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }).format(new Date(date));
};

// Logo preview functionality
const updateLogoPreview = () => {
    const logoUrlInput = document.getElementById('logo_url');
    const logoPreviewContainer = document.getElementById('logo_preview_container');
    const logoPreview = document.getElementById('logo_preview');
    const logoError = document.getElementById('logo_error');
    
    if (!logoUrlInput || !logoPreviewContainer || !logoPreview || !logoError) return;
    
    const logoUrl = logoUrlInput.value.trim();
    
    if (logoUrl) {
        // Show the preview container
        logoPreviewContainer.style.display = 'block';
        
        // Update the image source
        logoPreview.src = logoUrl;
        
        // Hide any previous error
        logoError.style.display = 'none';
        logoPreview.style.display = 'block';
    } else {
        // Hide the preview container if no URL
        logoPreviewContainer.style.display = 'none';
    }
};

const showLogoError = () => {
    const logoPreview = document.getElementById('logo_preview');
    const logoError = document.getElementById('logo_error');
    
    if (logoPreview && logoError) {
        logoPreview.style.display = 'none';
        logoError.style.display = 'block';
    }
};

const hideLogoError = () => {
    const logoPreview = document.getElementById('logo_preview');
    const logoError = document.getElementById('logo_error');
    
    if (logoPreview && logoError) {
        logoPreview.style.display = 'block';
        logoError.style.display = 'none';
    }
};

// Export functions for global use
window.DashboardUtils = {
    showNotification,
    formatNumber,
    formatDate,
    updateLogoPreview,
    showLogoError,
    hideLogoError
};

// Domain selection functionality
const initializeDomainSelection = () => {
    const selectAllBtn = document.getElementById('selectAllBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    const domainSelect = document.getElementById('domain');
    const selectionCount = document.getElementById('selectionCount');
    
    if (!selectAllBtn || !clearAllBtn || !domainSelect || !selectionCount) return;
    
    // Update selection count
    const updateSelectionCount = () => {
        const selectedCount = domainSelect.selectedOptions.length;
        selectionCount.textContent = `${selectedCount} domains selected`;
    };
    
    // Select all domains
    selectAllBtn.addEventListener('click', () => {
        for (let i = 0; i < domainSelect.options.length; i++) {
            domainSelect.options[i].selected = true;
        }
        updateSelectionCount();
    });
    
    // Clear all selections
    clearAllBtn.addEventListener('click', () => {
        for (let i = 0; i < domainSelect.options.length; i++) {
            domainSelect.options[i].selected = false;
        }
        updateSelectionCount();
    });
    
    // Update count when selection changes
    domainSelect.addEventListener('change', updateSelectionCount);
    
    // Initialize count
    updateSelectionCount();
};

// Initialize logo preview on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar functionality
    initializeSidebar();
    
    // Initialize other components
    initializeSearch();
    initializeNotifications();
    initializeAnimations();
    initializeCharts();
    
    // Initialize logo preview
    updateLogoPreview();
    
    // Initialize domain selection
    initializeDomainSelection();
});

// Make functions globally available for inline HTML
window.updateLogoPreview = updateLogoPreview;
window.showLogoError = showLogoError;
window.hideLogoError = hideLogoError; 