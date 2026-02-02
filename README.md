# Moderna Template for Thelia 3.x

A modern, high-performance front-office template for Thelia e-commerce platform built with **Symfony UX Live Components**.

## Features

- **Live Components Architecture**: Real-time UI updates without page reloads using Symfony UX
- **Modern CSS Architecture**: Design tokens, component-based CSS, Tailwind CSS utilities
- **Responsive Design**: Mobile-first approach for all devices
- **Wishlist Module**: Standalone ModernaWishlist module with Propel ORM
- **Complete Checkout Flow**: LiveComponent-powered cart, delivery, payment, and summary
- **Performance Optimized**: Extracted CSS/JS, lazy loading, minimal Alpine.js usage

## Architecture

### Live Components

The template uses Symfony UX Live Components for reactive UI elements:

| Component | Path | Description |
|-----------|------|-------------|
| `Moderna:Cart` | `UiComponents/Cart/Cart.php` | Shopping cart management |
| `Moderna:Cart:Drawer` | `UiComponents/Cart/CartDrawer.php` | Slide-out cart preview |
| `Moderna:Cart:Item` | `UiComponents/Cart/CartItem.php` | Individual cart item |
| `Moderna:Checkout:AddressForm` | `UiComponents/Checkout/AddressForm/` | Address creation/editing |
| `Moderna:Checkout:Delivery` | `UiComponents/Checkout/Delivery/` | Delivery selection |
| `Moderna:Checkout:Payment` | `UiComponents/Checkout/Payment/` | Payment method selection |
| `Moderna:Checkout:PromoCode` | `UiComponents/Checkout/PromoCode/` | Coupon management |
| `Moderna:Checkout:Summary` | `UiComponents/Checkout/Summary/` | Order summary |
| `Moderna:Toast:AddToCart` | `UiComponents/Toast/AddToCartToast.php` | Toast notifications |
| `Moderna:Product:VariantSelector` | `UiComponents/Product/VariantSelector/` | Product variant selection |
| `Moderna:Layout:SearchModal` | `UiComponents/Layout/SearchModal/` | Search modal |
| `Moderna:Account:AddressList` | `UiComponents/Account/AddressList/` | Customer addresses |
| `Moderna:Account:CustomerUpdate` | `UiComponents/Account/CustomerUpdate/` | Profile management |

### Component Communication

Components communicate via events defined in `CheckoutEvents.php`:

```php
use Moderna\UiComponents\Checkout\CheckoutEvents;

// Cart events
CheckoutEvents::ADD_ITEM_EVENT          // 'moderna:cart:add-item'
CheckoutEvents::REMOVE_ITEM_EVENT       // 'moderna:cart:remove-item'
CheckoutEvents::CART_UPDATED_EVENT      // 'moderna:cart:updated'

// Checkout events
CheckoutEvents::SET_DELIVERY_ADDRESS_ID // 'moderna:checkout:set-delivery-address'
CheckoutEvents::SET_PAYMENT_MODULE_ID   // 'moderna:checkout:set-payment-module'
```

### Data Transfer Objects

DTOs provide type-safe data structures:

- `CartItemDto` - Cart item data with price calculations
- `AddressDto` - Address data with formatting helpers

### CSS Architecture

```
assets/css/
├── design-tokens.css       # CSS custom properties
├── animations.css          # @keyframes definitions
├── utilities.css           # Utility classes
└── components/
    ├── _index.css          # Component imports
    ├── layout/
    │   ├── header.css
    │   ├── footer.css
    │   ├── mobile-menu.css
    │   ├── cart-drawer.css
    │   └── search-modal.css
    ├── product/
    │   ├── product-card.css
    │   ├── product-gallery.css
    │   ├── product-reviews.css
    │   └── variant-selector.css
    ├── cart/
    │   ├── cart-item.css
    │   └── toast.css
    └── checkout/
        └── address-form.css
```

## Installation

### 1. Copy template files

```bash
cp -r moderna templates/frontOffice/moderna
```

### 2. Install ModernaWishlist module

```bash
cp -r local/modules/ModernaWishlist local/modules/

# Generate and execute SQL
php Thelia module:generate:sql ModernaWishlist
php Thelia module:activate ModernaWishlist
```

### 3. Build assets

```bash
cd templates/frontOffice/moderna
npm install
npm run build
```

### 4. Activate template

In Thelia admin: **Configuration > Templates > Moderna**

## Requirements

- Thelia 3.x
- PHP 8.1+
- Node.js 18+ (for asset building)
- Symfony UX Live Component bundle (included in Thelia 3)

## Development

### Build Commands

```bash
npm run dev       # Development build with watch
npm run build     # Production build
npm run watch     # Watch for changes
```

### Running Tests

```bash
./vendor/bin/phpunit
```

### Live Component Usage in Twig

```twig
{# Cart component #}
{{ component('Moderna:Cart') }}

{# Cart drawer in layout #}
{{ component('Moderna:Cart:Drawer') }}

{# Address form with props #}
{{ component('Moderna:Checkout:AddressForm', {
    type: 'delivery',
    addressId: null
}) }}

{# Product variant selector #}
{{ component('Moderna:Product:VariantSelector', {
    productId: product.id,
    currentPseId: product.default_pse_id
}) }}
```

## Migration from Previous Version

If upgrading from the Alpine.js version:

1. The wishlist is now handled by the ModernaWishlist module
2. All interactive components use Live Components instead of Alpine.js stores
3. CSS is extracted to separate files - run `npm run build` after upgrade

### Wishlist Migration

```bash
# Run the migration command to transfer existing wishlist data
php Thelia moderna:wishlist:migrate
```

## Module: ModernaWishlist

Standalone wishlist module located in `local/modules/ModernaWishlist/`:

### Features
- Multiple wishlists per customer
- Public/private wishlist sharing
- LocalStorage sync for guests
- REST API endpoints
- Live Components for UI

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/wishlist` | Get wishlist items |
| POST | `/api/wishlist/add` | Add product |
| POST | `/api/wishlist/remove` | Remove product |
| POST | `/api/wishlist/toggle` | Toggle product |
| POST | `/api/wishlist/sync` | Sync from localStorage |
| POST | `/api/wishlist/clear` | Clear wishlist |
| POST | `/api/wishlist/add-all-to-cart` | Move all to cart |

## License

This template is licensed under the GPL-3.0 License.
