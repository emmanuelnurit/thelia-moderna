# ProductCard Component

Composant réutilisable pour afficher une carte produit dans le template moderna.

## Usage

### Basic

```twig
{{ component('Moderna:ProductCard', {
    product: product
}) }}
```

### With Options

```twig
{{ component('Moderna:ProductCard', {
    product: product,
    variant: 'compact',
    showWishlist: false
}) }}
```

## Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `product` | array | required | Objet produit Thelia complet |
| `variant` | string | 'standard' | Variante: 'standard', 'compact', 'wishlist' |
| `showWishlist` | bool | true | Afficher le bouton wishlist |

## Computed Properties

Le composant expose automatiquement:

- `this.defaultPse` - PSE par défaut
- `this.price` - Prix du PSE
- `this.firstImage` - Première image produit
- `this.imageUrl` - URL complète de l'image
- `this.hasPromo` - true si produit en promo
- `this.promoPercentage` - Pourcentage de réduction
- `this.isNew` - true si produit nouveau

## Variants

### Standard (default)
Carte produit complète avec image, badges, wishlist, titre et prix.

### Compact
Version compacte (future implementation).

### Wishlist
Version pour la page wishlist (future implementation).

## CSS Classes

Base: `.product-card`
Variants: `.product-card--standard`, `.product-card--compact`, `.product-card--wishlist`

## Dependencies

- Alpine.js (pour wishlist button)
- Function `wishlistButton()` définie globalement (dans assets/js/)

## Files

- PHP: `src/UiComponents/Product/ProductCard/ProductCard.php`
- Twig: `src/UiComponents/Product/ProductCard/ProductCard.html.twig`
- CSS: `assets/css/components/_product-card.css`
