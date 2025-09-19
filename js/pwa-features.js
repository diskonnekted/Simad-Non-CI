// PWA Features JavaScript

// Offline/Online Detection
class OfflineManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.offlineIndicator = null;
        this.init();
    }
    
    init() {
        this.createOfflineIndicator();
        this.bindEvents();
        this.updateStatus();
    }
    
    createOfflineIndicator() {
        this.offlineIndicator = document.createElement('div');
        this.offlineIndicator.className = 'offline-indicator';
        this.offlineIndicator.innerHTML = '<i class="fas fa-wifi mr-2"></i>Anda sedang offline';
        document.body.appendChild(this.offlineIndicator);
    }
    
    bindEvents() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateStatus();
            this.showToast('Koneksi internet tersambung kembali', 'success');
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateStatus();
            this.showToast('Koneksi internet terputus', 'warning');
        });
    }
    
    updateStatus() {
        if (this.isOnline) {
            this.offlineIndicator.classList.remove('show');
        } else {
            this.offlineIndicator.classList.add('show');
        }
    }
    
    showToast(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'} toast-notification`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i>
                ${message}
                <button type="button" class="close ml-auto" onclick="this.parentElement.parentElement.remove()">
                    <span>&times;</span>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
}

// Mobile Navigation Manager
class MobileNavManager {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.toggleButton = document.getElementById('toggleSidebarMobile');
        this.backdrop = document.getElementById('sidebarBackdrop');
        this.init();
    }
    
    init() {
        if (this.toggleButton) {
            this.toggleButton.addEventListener('click', () => this.toggleSidebar());
        }
        
        if (this.backdrop) {
            this.backdrop.addEventListener('click', () => this.closeSidebar());
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && this.sidebar && this.sidebar.classList.contains('show')) {
                if (!this.sidebar.contains(e.target) && !this.toggleButton.contains(e.target)) {
                    this.closeSidebar();
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                this.closeSidebar();
            }
        });
    }
    
    toggleSidebar() {
        if (this.sidebar) {
            this.sidebar.classList.toggle('show');
            if (this.backdrop) {
                this.backdrop.classList.toggle('hidden');
            }
        }
    }
    
    closeSidebar() {
        if (this.sidebar) {
            this.sidebar.classList.remove('show');
            if (this.backdrop) {
                this.backdrop.classList.add('hidden');
            }
        }
    }
}

// Pull to Refresh
class PullToRefresh {
    constructor(element) {
        this.element = element || document.body;
        this.threshold = 80;
        this.startY = 0;
        this.currentY = 0;
        this.pulling = false;
        this.init();
    }
    
    init() {
        this.element.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
        this.element.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        this.element.addEventListener('touchend', () => this.handleTouchEnd(), { passive: true });
    }
    
    handleTouchStart(e) {
        this.startY = e.touches[0].clientY;
    }
    
    handleTouchMove(e) {
        this.currentY = e.touches[0].clientY;
        const diff = this.currentY - this.startY;
        
        if (diff > 0 && window.scrollY === 0) {
            e.preventDefault();
            
            if (diff > this.threshold) {
                this.pulling = true;
                this.element.classList.add('pulling');
            }
        }
    }
    
    handleTouchEnd() {
        if (this.pulling) {
            this.refresh();
        }
        
        this.pulling = false;
        this.element.classList.remove('pulling');
    }
    
    refresh() {
        // Show loading indicator
        const indicator = document.querySelector('.pull-to-refresh-indicator');
        if (indicator) {
            indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        
        // Simulate refresh (reload page)
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
}

// Form Enhancement for Mobile
class MobileFormEnhancer {
    constructor() {
        this.init();
    }
    
    init() {
        // Add loading states to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submitBtn) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    
                    // Re-enable after 5 seconds as fallback
                    setTimeout(() => {
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                    }, 5000);
                }
            });
        });
        
        // Enhance select elements for mobile
        document.querySelectorAll('select').forEach(select => {
            select.style.fontSize = '16px'; // Prevent zoom on iOS
        });
        
        // Add touch-friendly date pickers
        document.querySelectorAll('input[type="date"], input[type="datetime-local"], input[type="time"]').forEach(input => {
            input.style.fontSize = '16px';
        });
    }
}

// Swipe Gestures
class SwipeGestureManager {
    constructor() {
        this.startX = 0;
        this.startY = 0;
        this.threshold = 50;
        this.init();
    }
    
    init() {
        document.addEventListener('touchstart', (e) => {
            this.startX = e.touches[0].clientX;
            this.startY = e.touches[0].clientY;
        }, { passive: true });
        
        document.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            
            const diffX = endX - this.startX;
            const diffY = endY - this.startY;
            
            // Check if it's a horizontal swipe
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > this.threshold) {
                if (diffX > 0) {
                    this.handleSwipeRight();
                } else {
                    this.handleSwipeLeft();
                }
            }
        }, { passive: true });
    }
    
    handleSwipeRight() {
        // Open sidebar on swipe right
        if (window.innerWidth <= 768) {
            const mobileNav = new MobileNavManager();
            if (!document.getElementById('sidebar').classList.contains('show')) {
                mobileNav.toggleSidebar();
            }
        }
    }
    
    handleSwipeLeft() {
        // Close sidebar on swipe left
        if (window.innerWidth <= 768) {
            const mobileNav = new MobileNavManager();
            if (document.getElementById('sidebar').classList.contains('show')) {
                mobileNav.closeSidebar();
            }
        }
    }
}

// Viewport Height Fix for Mobile
class ViewportFix {
    constructor() {
        this.init();
    }
    
    init() {
        // Fix viewport height on mobile browsers
        const setVH = () => {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        };
        
        setVH();
        window.addEventListener('resize', setVH);
        window.addEventListener('orientationchange', () => {
            setTimeout(setVH, 100);
        });
    }
}

// Initialize all PWA features when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize offline manager
    new OfflineManager();
    
    // Initialize mobile navigation
    new MobileNavManager();
    
    // Initialize pull to refresh
    new PullToRefresh();
    
    // Initialize form enhancements
    new MobileFormEnhancer();
    
    // Initialize swipe gestures
    new SwipeGestureManager();
    
    // Initialize viewport fix
    new ViewportFix();
    
    // Add PWA-specific classes
    if (window.matchMedia('(display-mode: standalone)').matches) {
        document.body.classList.add('pwa-standalone');
    }
    
    // Handle back button for PWA
    if (window.history && window.history.pushState) {
        window.addEventListener('popstate', (e) => {
            // Handle back navigation in PWA
            if (document.getElementById('sidebar').classList.contains('show')) {
                e.preventDefault();
                new MobileNavManager().closeSidebar();
                window.history.pushState(null, null, window.location.href);
            }
        });
        
        // Push initial state
        window.history.pushState(null, null, window.location.href);
    }
});

// Export for use in other scripts
window.PWAFeatures = {
    OfflineManager,
    MobileNavManager,
    PullToRefresh,
    MobileFormEnhancer,
    SwipeGestureManager,
    ViewportFix
};