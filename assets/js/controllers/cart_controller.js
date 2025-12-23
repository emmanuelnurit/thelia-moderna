/**
 * Cart Stimulus Controller - Modern.A
 * Handles cart interactions, animations, and state management
 */

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['quantity', 'addButton', 'counter'];
    static values = {
        itemId: Number,
        quantity: Number,
        maxStock: Number
    };

    connect() {
        // Listen for cart update events
        window.addEventListener('cart-updated', this.handleCartUpdate.bind(this));

        // Initialize quantity if exists
        if (this.hasQuantityTarget) {
            this.quantityValue = parseInt(this.quantityTarget.value) || 1;
        }
    }

    disconnect() {
        window.removeEventListener('cart-updated', this.handleCartUpdate.bind(this));
    }

    /**
     * Increase quantity
     */
    increase(event) {
        event.preventDefault();

        if (this.quantityValue < this.maxStockValue) {
            this.quantityValue++;
            this.updateQuantityInput();
            this.triggerUpdate();
        }
    }

    /**
     * Decrease quantity
     */
    decrease(event) {
        event.preventDefault();

        if (this.quantityValue > 1) {
            this.quantityValue--;
            this.updateQuantityInput();
            this.triggerUpdate();
        }
    }

    /**
     * Handle manual input change
     */
    handleInput(event) {
        const value = parseInt(event.target.value) || 1;

        // Validate bounds
        if (value < 1) {
            this.quantityValue = 1;
        } else if (value > this.maxStockValue) {
            this.quantityValue = this.maxStockValue;
        } else {
            this.quantityValue = value;
        }

        this.updateQuantityInput();
        this.triggerUpdate();
    }

    /**
     * Update quantity input field
     */
    updateQuantityInput() {
        if (this.hasQuantityTarget) {
            this.quantityTarget.value = this.quantityValue;
        }
    }

    /**
     * Trigger quantity update event
     */
    triggerUpdate() {
        if (this.hasItemIdValue) {
            // Dispatch custom event for cart item quantity update
            this.dispatch('quantity-changed', {
                detail: {
                    itemId: this.itemIdValue,
                    quantity: this.quantityValue
                }
            });
        }
    }

    /**
     * Add to cart action
     */
    async addToCart(event) {
        event.preventDefault();

        if (this.hasAddButtonTarget) {
            this.addButtonTarget.disabled = true;
            this.addButtonTarget.classList.add('loading');
        }

        const form = event.target.closest('form');
        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                // Show success animation
                this.showSuccessAnimation();

                // Open cart drawer after a brief delay
                setTimeout(() => {
                    window.dispatchEvent(new CustomEvent('cart:open'));
                }, 300);

                // Update cart counter
                this.updateCartCounter();

                // Show toast notification
                window.showToast({
                    type: 'success',
                    message: 'Product added to cart!',
                    duration: 3000
                });
            } else {
                throw new Error('Failed to add to cart');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);

            window.showToast({
                type: 'error',
                message: 'Failed to add product to cart. Please try again.',
                duration: 4000
            });
        } finally {
            if (this.hasAddButtonTarget) {
                this.addButtonTarget.disabled = false;
                this.addButtonTarget.classList.remove('loading');
            }
        }
    }

    /**
     * Show success animation on add button
     */
    showSuccessAnimation() {
        if (this.hasAddButtonTarget) {
            this.addButtonTarget.classList.add('success');

            setTimeout(() => {
                this.addButtonTarget.classList.remove('success');
            }, 2000);
        }
    }

    /**
     * Update cart counter in header
     */
    async updateCartCounter() {
        try {
            const response = await fetch('/moderna-api/cart_items');
            const data = await response.json();

            const totalQuantity = data.count || 0;

            // Update counter elements
            document.querySelectorAll('[data-cart-counter]').forEach(counter => {
                counter.textContent = totalQuantity;
                counter.style.display = totalQuantity > 0 ? 'flex' : 'none';
            });

            // Update Alpine store if available
            if (window.Alpine && window.Alpine.store('cart')) {
                window.Alpine.store('cart').count = totalQuantity;
                window.Alpine.store('cart').items = items;
            }
        } catch (error) {
            console.error('Error updating cart counter:', error);
        }
    }

    /**
     * Handle cart update event from other components
     */
    handleCartUpdate(event) {
        this.updateCartCounter();
    }

    /**
     * Quick add to cart (for product cards)
     */
    async quickAdd(event) {
        event.preventDefault();
        event.stopPropagation();

        const button = event.currentTarget;
        const productId = button.dataset.productId;
        const pseId = button.dataset.pseId;

        button.disabled = true;
        button.classList.add('loading');

        try {
            const formData = new URLSearchParams({
                'product_id': productId,
                'product_sale_elements_id': pseId,
                'quantity': 1
            });

            const response = await fetch('/?view=add-to-cart', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            });

            if (response.ok) {
                // Show success feedback
                button.classList.add('success');

                window.showToast({
                    type: 'success',
                    message: 'Product added to cart!',
                    duration: 3000
                });

                // Update cart counter
                await this.updateCartCounter();

                setTimeout(() => {
                    button.classList.remove('success');
                }, 2000);
            }
        } catch (error) {
            console.error('Error in quick add:', error);

            window.showToast({
                type: 'error',
                message: 'Failed to add product to cart.',
                duration: 4000
            });
        } finally {
            button.disabled = false;
            button.classList.remove('loading');
        }
    }
}
