// Domain selection functions
function selectAllDomains() {
    const select = document.getElementById('domain');
    if (select) {
        for (let i = 0; i < select.options.length; i++) {
            select.options[i].selected = true;
        }
        updateSelectionCount();
    }
}

function clearAllDomains() {
    const select = document.getElementById('domain');
    if (select) {
        for (let i = 0; i < select.options.length; i++) {
            select.options[i].selected = false;
        }
        updateSelectionCount();
    }
}

function updateSelectionCount() {
    const select = document.getElementById('domain');
    const countElement = document.getElementById('selectionCount');
    
    if (select && countElement) {
        const count = Array.from(select.selectedOptions).length;
        countElement.textContent = count + ' domain' + (count !== 1 ? 's' : '') + ' selected';
    }
}

// Setup event listeners
function setupMainPageHandlers() {
    // Domain selection count update
    const domainSelect = document.getElementById('domain');
    if (domainSelect) {
        domainSelect.addEventListener('change', updateSelectionCount);
    }
    
    // Selection helper buttons
    const selectAllBtn = document.getElementById('selectAllBtn');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', selectAllDomains);
    }
    
    const clearAllBtn = document.getElementById('clearAllBtn');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', clearAllDomains);
    }
    
    // Target the specific "Update Selected Domains" button
    const updateDomainsBtn = document.getElementById('updateDomainsBtn');
    console.log('Looking for Update Selected Domains button...', updateDomainsBtn);
    
    if (updateDomainsBtn) {
        console.log('Found Update Selected Domains button:', updateDomainsBtn.textContent);
        
        updateDomainsBtn.addEventListener('click', function(e) {
            console.log('Update Selected Domains button clicked!');
            
            // Validate that at least one domain is selected
            const domainSelect = document.getElementById('domain');
            const selectedDomains = domainSelect ? Array.from(domainSelect.selectedOptions) : [];
            
            console.log('Selected domains count:', selectedDomains.length);
            
            if (selectedDomains.length === 0) {
                e.preventDefault();
                alert('Please select at least one domain to update.');
                return false;
            }
            
            // Show a simple confirmation dialog
            const confirmMessage = `Are you sure you want to update nameservers for ${selectedDomains.length} domain${selectedDomains.length !== 1 ? 's' : ''}?`;
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
            
            console.log('Form will submit normally...');
            // Let the form submit naturally - don't prevent default
        });
    } else {
        console.log('Update Selected Domains button not found!');
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up handlers...');
    
    setupMainPageHandlers();
    updateSelectionCount(); // Initialize count on page load
    
    // Debug: Check if elements exist
    console.log('Update Domains button exists:', !!document.getElementById('updateDomainsBtn'));
    console.log('Domain select exists:', !!document.getElementById('domain'));
}); 