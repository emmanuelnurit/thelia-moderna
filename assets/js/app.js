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

/**
 * Wishlist Store - Local storage only (persisted via Alpine $persist)
 * Server-side wishlist is handled by ModernaWishlist module when customer is logged in.
 * This store provides instant client-side feedback for wishlist interactions.
 */
Alpine.store('wishlist', {
  items: Alpine.$persist([]).as('moderna_wishlist'),

  has(productId) {
    return this.items.some(item => item.id === productId);
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
    }
  },

  remove(productId) {
    this.items = this.items.filter(item => item.id !== productId);
    window.dispatchEvent(new CustomEvent('wishlist:removed', { detail: { productId } }));
  },

  clear() {
    this.items = [];
    window.dispatchEvent(new CustomEvent('wishlist:cleared'));
  },

  get count() {
    return this.items.length;
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

// Expose helpers to window for LiveComponents and external scripts
window.Moderna = {
  wishlist: {
    has: (productId) => Alpine.store('wishlist').has(productId),
    toggle: (product) => Alpine.store('wishlist').toggle(product),
    add: (product) => Alpine.store('wishlist').add(product),
    remove: (productId) => Alpine.store('wishlist').remove(productId),
    clear: () => Alpine.store('wishlist').clear(),
    items: () => Alpine.store('wishlist').items,
    count: () => Alpine.store('wishlist').count,
  },
  cart: {
    updateCount: (count) => Alpine.store('cart').updateCount(count),
  }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  document.body.classList.remove('no-js');

  // Handle escape key for closing modals and menus
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      Alpine.store('mobileMenu').close();
      Alpine.store('search').close();
    }
  });
});

// Start Alpine (will initialize when DOM is ready)
Alpine.start();
