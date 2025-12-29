/**
 * Modern.A - Main JavaScript Entry Point
 */

// Styles
import '../css/app.css';

// Cart synchronization
import './cart-sync.js';

// Stimulus
import { startStimulusApp } from '@symfony/stimulus-bridge';

// Start Stimulus application
export const app = startStimulusApp(
  require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.[jt]sx?$/
  )
);

// Alpine.js
import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';

// Register plugins
Alpine.plugin(persist);

// Global Alpine stores
Alpine.store('cart', {
  count: 0,
  items: [],

  init() {
    // Will be populated by LiveComponent or API
  },

  updateCount(count) {
    this.count = count;
  }
});

Alpine.store('wishlist', {
  items: Alpine.$persist([]).as('moderna_wishlist'),
  isAuthenticated: false,
  isSyncing: false,

  has(productId) {
    // Convert to string for comparison to handle both number and string IDs
    const searchId = String(productId);
    return this.items.some(item => String(item.id) === searchId);
  },

  toggle(product) {
    if (this.has(product.id)) {
      this.remove(product.id);
      return false;
    } else {
      this.add(product);
      return true;
    }
  },

  add(product) {
    if (!this.has(product.id)) {
      this.items.push(product);
      window.dispatchEvent(new CustomEvent('wishlist:added', { detail: product }));

      // Sync with server if authenticated
      if (this.isAuthenticated) {
        this.addToServer(product.id);
      }
    }
  },

  remove(productId) {
    // Convert to string for comparison to handle both number and string IDs
    const removeId = String(productId);
    this.items = this.items.filter(item => String(item.id) !== removeId);
    window.dispatchEvent(new CustomEvent('wishlist:removed', { detail: { productId: removeId } }));

    // Sync with server if authenticated
    if (this.isAuthenticated) {
      this.removeFromServer(productId);
    }
  },

  clear() {
    this.items = [];
    window.dispatchEvent(new CustomEvent('wishlist:cleared'));

    // Clear on server if authenticated
    if (this.isAuthenticated) {
      this.clearOnServer();
    }
  },

  get count() {
    return this.items.length;
  },

  // Server sync methods
  async addToServer(productId) {
    try {
      await fetch('/moderna-api/wishlist/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ product_id: productId })
      });
    } catch (error) {
      console.error('Failed to add to server wishlist:', error);
    }
  },

  async removeFromServer(productId) {
    try {
      await fetch('/moderna-api/wishlist/remove', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ product_id: productId })
      });
    } catch (error) {
      console.error('Failed to remove from server wishlist:', error);
    }
  },

  async clearOnServer() {
    try {
      await fetch('/moderna-api/wishlist/clear', {
        method: 'POST',
        credentials: 'include'
      });
    } catch (error) {
      console.error('Failed to clear server wishlist:', error);
    }
  },

  // Sync local wishlist with server (merge)
  async syncWithServer() {
    if (this.isSyncing) return;
    this.isSyncing = true;

    try {
      // Get current local items before sync
      const localItems = [...this.items];
      console.log('[Wishlist] Syncing with server, local items:', localItems);

      const response = await fetch('/moderna-api/wishlist/sync', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ items: localItems })
      });

      console.log('[Wishlist] Sync response status:', response.status);

      if (response.ok) {
        const data = await response.json();
        console.log('[Wishlist] Sync response data:', data);

        if (data.success && data.items) {
          console.log('[Wishlist] Server returned', data.items.length, 'items');

          // Clear current items and add merged ones
          // Use while loop to ensure all items are removed
          while (this.items.length > 0) {
            this.items.pop();
          }

          // Add all server items
          for (const serverItem of data.items) {
            this.items.push(serverItem);
          }

          // Also manually update localStorage as backup for Alpine $persist
          try {
            localStorage.setItem('_x_moderna_wishlist', JSON.stringify(this.items));
            console.log('[Wishlist] Saved to localStorage:', this.items.length, 'items');
          } catch (e) {
            console.error('[Wishlist] Failed to save to localStorage:', e);
          }

          console.log('[Wishlist] Updated store items:', this.items.length, 'items');

          // Dispatch event after a small delay to ensure DOM is updated
          setTimeout(() => {
            window.dispatchEvent(new CustomEvent('wishlist:synced', { detail: { items: [...this.items] } }));
            console.log('[Wishlist] Dispatched wishlist:synced event');
          }, 100);
        }
      } else {
        const errorData = await response.json();
        console.log('[Wishlist] Sync error:', errorData);
      }
    } catch (error) {
      console.error('[Wishlist] Failed to sync with server:', error);
    } finally {
      this.isSyncing = false;
    }
  },

  // Check authentication status
  async checkAuth() {
    try {
      const response = await fetch('/moderna-api/auth/check', { credentials: 'include' });
      const data = await response.json();
      this.isAuthenticated = data.authenticated === true;
      return this.isAuthenticated;
    } catch (error) {
      this.isAuthenticated = false;
      return false;
    }
  }
});

// Mobile menu store
Alpine.store('mobileMenu', {
  open: false,

  toggle() {
    this.open = !this.open;
    document.body.style.overflow = this.open ? 'hidden' : '';
  },

  close() {
    this.open = false;
    document.body.style.overflow = '';
  }
});

// Search modal store
Alpine.store('search', {
  open: false,
  query: '',

  toggle() {
    this.open = !this.open;
    if (this.open) {
      setTimeout(() => {
        document.querySelector('[data-search-input]')?.focus();
      }, 100);
    }
  },

  close() {
    this.open = false;
    this.query = '';
  }
});

// Expose Alpine to window before starting
window.Alpine = Alpine;

// Expose helpers to window for LiveComponents
window.Moderna = {
  wishlist: {
    has: (productId) => Alpine.store('wishlist').has(productId),
    toggle: (product) => Alpine.store('wishlist').toggle(product),
    add: (product) => Alpine.store('wishlist').add(product),
    remove: (productId) => Alpine.store('wishlist').remove(productId),
    clear: () => Alpine.store('wishlist').clear(),
    items: () => Alpine.store('wishlist').items,
    sync: () => Alpine.store('wishlist').syncWithServer(),
    isAuthenticated: () => Alpine.store('wishlist').isAuthenticated,
  },
  cart: {
    updateCount: (count) => Alpine.store('cart').updateCount(count),
  }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', async () => {
  document.body.classList.remove('no-js');

  // Handle escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      Alpine.store('mobileMenu').close();
      Alpine.store('search').close();
    }
  });

  // Check authentication and sync wishlist if authenticated
  const wishlistStore = Alpine.store('wishlist');
  const isAuth = await wishlistStore.checkAuth();
  if (isAuth) {
    wishlistStore.syncWithServer();
  }

  // Listen for auth changes (login/logout)
  window.addEventListener('auth-changed', async (event) => {
    const { isLogout } = event.detail || {};

    if (isLogout) {
      // User logged out - keep local wishlist, mark as not authenticated
      wishlistStore.isAuthenticated = false;
    } else {
      // User logged in - sync wishlist with server (merge)
      wishlistStore.isAuthenticated = true;
      await wishlistStore.syncWithServer();
    }
  });
});

// Start Alpine (will initialize when DOM is ready)
Alpine.start();
