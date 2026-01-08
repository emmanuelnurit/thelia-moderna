/**
 * Wishlist button component for product cards
 * Alpine.js component function for managing wishlist toggle buttons
 */

export function wishlistButton(config) {
    return {
        id: config.id,
        title: config.title,
        publicUrl: config.publicUrl,
        imageId: config.imageId,
        price: config.price || '',
        promoPrice: config.promoPrice || '',
        isPromo: config.isPromo || false,
        isInWishlist: false,

        init() {
            // Check immediately
            this.checkWishlist();

            // Listen for wishlist changes
            window.addEventListener('wishlist:added', () => this.checkWishlist());
            window.addEventListener('wishlist:removed', () => this.checkWishlist());
            window.addEventListener('wishlist:cleared', () => this.checkWishlist());
        },

        checkWishlist() {
            // Use Alpine store directly for better reliability
            if (window.Alpine && window.Alpine.store && window.Alpine.store('wishlist')) {
                this.isInWishlist = window.Alpine.store('wishlist').has(this.id);
            }
        },

        toggle() {
            // Use Alpine store directly
            if (window.Alpine && window.Alpine.store && window.Alpine.store('wishlist')) {
                // Build full image URL from imageId
                const imageUrl = this.imageId ? `/legacy-image-library/product_image_${this.imageId}/full/%5E*!400,400/0/default.webp` : '';

                const product = {
                    id: this.id,
                    title: this.title,
                    url: this.publicUrl,
                    image: imageUrl,
                    imageId: this.imageId,
                    price: this.price,
                    promoPrice: this.promoPrice,
                    isPromo: this.isPromo
                };
                const added = window.Alpine.store('wishlist').toggle(product);
                this.isInWishlist = added;
            }
        }
    };
}

// Expose to window for global access
if (typeof window !== 'undefined') {
    window.wishlistButton = wishlistButton;
}
