/**
 * Cart Synchronization - Moderna Template
 * Handles cart synchronization with authentication state
 */

// Update cart counter from API
window.updateCartCounter = async function() {
    try {
        const response = await fetch('/moderna-api/cart_items');
        if (!response.ok) return;

        const data = await response.json();
        const count = data.count || 0;

        // Dispatch event to update counter
        window.dispatchEvent(new CustomEvent('cart-updated', {
            detail: { count }
        }));
    } catch (error) {
        console.error('Error updating cart counter:', error);
    }
};

// Refresh cart items from API (for cart drawer)
window.refreshCart = async function() {
    try {
        const response = await fetch('/moderna-api/cart_items');
        if (!response.ok) return;

        const data = await response.json();
        const items = data.cart_items || [];
        const count = data.count || 0;

        // Update Alpine store if available
        if (window.Alpine && window.Alpine.store('cart')) {
            window.Alpine.store('cart').items = items;
            window.Alpine.store('cart').count = count;
        }

        // Dispatch event for other components
        window.dispatchEvent(new CustomEvent('cart-updated', {
            detail: { count, items }
        }));
    } catch (error) {
        console.error('Error refreshing cart:', error);
    }
};

// Clear cart display (for logout)
window.clearCartDisplay = function() {
    if (window.Alpine && window.Alpine.store('cart')) {
        window.Alpine.store('cart').items = [];
        window.Alpine.store('cart').count = 0;
    }

    // Dispatch event to update UI
    window.dispatchEvent(new CustomEvent('cart-updated', {
        detail: { count: 0, items: [] }
    }));
};

// Save wishlist for a specific customer
window.saveWishlistForCustomer = function(customerId) {
    if (!customerId) return;

    if (window.Alpine && window.Alpine.store('wishlist')) {
        const items = window.Alpine.store('wishlist').items;
        localStorage.setItem(`wishlist_customer_${customerId}`, JSON.stringify(items));
        console.log(`Wishlist saved for customer ${customerId}:`, items.length, 'items');
    }
};

// Load wishlist for a specific customer
window.loadWishlistForCustomer = function(customerId) {
    if (!customerId) return;

    const saved = localStorage.getItem(`wishlist_customer_${customerId}`);
    if (saved && window.Alpine && window.Alpine.store('wishlist')) {
        try {
            const items = JSON.parse(saved);
            window.Alpine.store('wishlist').items = items;
            console.log(`Wishlist loaded for customer ${customerId}:`, items.length, 'items');
            window.dispatchEvent(new CustomEvent('wishlist:loaded', { detail: { items } }));
        } catch (e) {
            console.error('Error loading wishlist:', e);
        }
    }
};

// Clear wishlist display (for logout) - saves before clearing
window.clearWishlistDisplay = function(customerId) {
    // Save wishlist for customer before clearing
    if (customerId) {
        window.saveWishlistForCustomer(customerId);
    }

    if (window.Alpine && window.Alpine.store('wishlist')) {
        window.Alpine.store('wishlist').items = [];
    }

    // Dispatch event to update UI
    window.dispatchEvent(new CustomEvent('wishlist:cleared'));
};

// Listen for auth changes to sync cart and wishlist
document.addEventListener('DOMContentLoaded', function() {
    window.addEventListener('auth-changed', function(event) {
        const isLogout = event.detail && event.detail.isLogout;
        const customerId = event.detail && event.detail.customerId;

        // On logout, don't refresh - just keep displays cleared
        if (isLogout) {
            return;
        }

        // On login, refresh cart and load wishlist
        setTimeout(() => {
            window.refreshCart();
            if (customerId) {
                window.loadWishlistForCustomer(customerId);
            }
        }, 100);
    });
});
