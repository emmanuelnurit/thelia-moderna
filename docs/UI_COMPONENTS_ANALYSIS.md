# Analyse UI Components - Template Moderna

**Date**: 2026-01-07
**Objectif**: Identifier les opportunités de création de composants UI réutilisables

---

## 📊 Résumé Exécutif

### État Actuel
- **Flexy**: 48 UI Components structurés (Symfony UX TwigComponent)
- **Moderna**: 1 seul composant (`LangSelect`) + 62 fichiers Twig avec patterns répétés
- **Duplication estimée**: ~2000+ lignes de code réutilisable

### Recommandation
Créer **15-20 nouveaux UI Components** pour réduire la duplication de 40% et améliorer la maintenabilité.

---

## 🎯 Top 6 Composants Prioritaires

### 1. 🔴 ProductCard (TRÈS HAUTE PRIORITÉ)
**Duplication**: Utilisé 50+ fois dans 6 fichiers différents

**Fichiers concernés**:
- `components/Product/ProductCard.html.twig`
- `components/Home/ProductsGrid.html.twig`
- `category.html.twig`
- `wishlist.html.twig`
- `components/Cart/Cart.html.twig`
- `promo.html.twig`

**Variations actuelles**:
- ProductCard standard (avec wishlist button)
- ProductCard dans grille (légèrement différent)
- WishlistCard (avec bouton remove)
- CartSuggestionCard (dans panier vide)
- FeaturedProductCard (version promotionnelle)

**Bénéfices**:
- Normalisation du design
- Centralisation logique wishlist
- Calcul promo percentage unifié
- Réduction de 300+ lignes de code

---

### 2. 🔴 Button (TRÈS HAUTE PRIORITÉ)
**Duplication**: Utilisé 100+ fois dans 15+ fichiers

**Problèmes actuels**:
- Nommage inconsistant: `btn-primary` vs `btn--primary`
- 15+ variantes de styles
- Logique de loading dupliquée

**Variations à supporter**:
- `primary`, `secondary`, `ghost`
- Tailles: `sm`, `md`, `lg`
- États: `default`, `loading`, `disabled`
- Largeur: `auto`, `full`

**Bénéfices**:
- Cohérence visuelle garantie
- API unifiée pour les états
- Accessibilité centralisée

---

### 3. 🔴 Badge (TRÈS HAUTE PRIORITÉ) ✅ IMPLEMENTED

**Status**: ✅ Implemented in `src/UiComponents/Common/Badge/`

**Duplication**: 5 variantes répétées dans 20+ fichiers

**Usage**:
```twig
{{ component('Moderna:Badge', { type: 'new' }) }}
{{ component('Moderna:Badge', { type: 'promo', value: 25 }) }}
{{ component('Moderna:Badge', { type: 'status', statusCode: 'delivered', text: 'Delivered' }) }}
```

**Types supportés**: new, promo, default, status

---

### 4. 🔴 AddressCard (HAUTE PRIORITÉ)
**Duplication**: 3 variantes dans 4 fichiers

**Variations**:
1. **Display** - Affichage avec edit/delete + modal confirmation
2. **Selectable** - Radio button pour sélection checkout
3. **Compact** - Version compacte dans commande passée

**Fichiers concernés**:
- `account-addresses.html.twig`
- `checkout-delivery.html.twig`
- `checkout-payment.html.twig`
- `account-order.html.twig`

**Bénéfices**:
- Gestion modal suppression centralisée
- Support multi-pays unifié
- Accessibilité améliorée

---

### 5. 🔴 FormGroup (HAUTE PRIORITÉ)
**Duplication**: Pattern répété 20+ times dans 8+ fichiers

**Structure commune**:
- Label + input
- Indicateurs Optional/Required
- Hint text
- Error message
- Support validation

**Types à supporter**:
- Input text
- Select
- Checkbox
- Toggle/Switch
- Textarea

**Fichiers concernés**:
- Tous les formulaires (address, login, register, account-update, etc.)

**Bénéfices**:
- Validation visuelle cohérente
- Intégration i18n automatique
- Accessibilité garantie

---

### 6. 🔴 OrderCard (HAUTE PRIORITÉ)
**Duplication**: 2 variantes dans 2 fichiers

**Structure**:
- En-tête: référence, date, statut
- Liste produits (4 premiers avec badges ×qty)
- Actions: View, Track, etc.

**Variations**:
1. **List** - Carte compacte dans liste commandes
2. **Detail** - Vue détaillée avec tous les produits

**Fichiers concernés**:
- `account-orders.html.twig`
- `account-order.html.twig`

**Bénéfices**:
- Calcul statut avec couleur centralisé
- Affichage produits unifié

---

## 📋 Composants Priorité Moyenne (7)

### 7. 🟠 ProductsGrid
**Utilisation**: 3 fichiers
Grille responsive avec ProductCard, titre section, "View All"

### 8. 🟠 EmptyState
**Utilisation**: 4 fichiers
États vides (panier, wishlist, commandes, produits)

### 9. 🟠 OrderSummary
**Utilisation**: 3 fichiers
Résumé commande avec totaux (subtotal, shipping, taxes, total)

### 10. 🟠 DeleteConfirmation
**Utilisation**: 2 fichiers
Modale de confirmation avec timer animation

### 11. 🟠 QuantityControl
**Utilisation**: 2 fichiers
Contrôle quantité (-, input, +) avec validation

### 12. 🟠 Breadcrumb
**Utilisation**: 6 fichiers
Navigation fil d'Ariane avec séparateur configurable

### 13. 🟠 AddressSelector
**Utilisation**: 2 fichiers checkout
Sélection adresse existante ou création nouvelle

---

## 📋 Composants Priorité Basse (3)

### 14. 🟡 CheckoutSteps
Indicateur d'étapes checkout (pending, active, completed)

### 15. 🟡 OptionCard
Sélection options radio (delivery methods, payment methods)

### 16. 🟡 AccountNavigation
Navigation sidebar compte avec icônes

---

## 🎨 Structure Recommandée

```
/templates/frontOffice/moderna/src/UiComponents/
├── Product/
│   ├── ProductCard/
│   │   ├── ProductCard.php
│   │   └── ProductCard.html.twig
│   ├── ProductsGrid/
│   │   ├── ProductsGrid.php
│   │   └── ProductsGrid.html.twig
│   └── Badge/
│       ├── Badge.php
│       └── Badge.html.twig
│
├── Checkout/
│   ├── OrderSummary/
│   ├── AddressSelector/
│   └── CheckoutSteps/
│
├── Form/
│   ├── FormGroup/
│   ├── FormRow/
│   └── QuantityControl/
│
├── Navigation/
│   ├── Breadcrumb/
│   ├── AccountNav/
│   └── Tabs/
│
├── Common/
│   ├── Button/
│   ├── EmptyState/
│   ├── DeleteConfirmation/
│   └── OptionCard/
│
└── Order/
    ├── OrderCard/
    └── AddressCard/
```

---

## 📈 Bénéfices Attendus

### Réduction Code
- **-40%** de lignes Twig dupliquées
- **~2000 lignes** de code réutilisable centralisé

### Maintenabilité
- Changements centralisés (1 lieu au lieu de 6)
- Tests unitaires possibles sur composants PHP
- Documentation centralisée

### Cohérence
- Comportement uniforme
- Gestion Alpine.js optimisée
- Styles normalisés

### Performance
- Moins d'instances Alpine.js
- Cache Twig amélioré
- Chargement optimisé

---

## 🚀 Plan d'Action Suggéré

### Phase 1: Composants Critiques (Semaine 1-2)
1. Button
2. Badge ✅
3. ProductCard

### Phase 2: Composants Formulaires (Semaine 3)
4. FormGroup
5. AddressCard

### Phase 3: Composants Commandes (Semaine 4)
6. OrderCard
7. OrderSummary

### Phase 4: Composants Utilitaires (Semaine 5-6)
8. EmptyState
9. ProductsGrid
10. DeleteConfirmation
11. QuantityControl

### Phase 5: Composants Navigation (Semaine 7)
12. Breadcrumb
13. CheckoutSteps
14. AccountNavigation

---

## 📝 Notes Techniques

### Symfony UX TwigComponent
Tous les composants suivront le pattern Flexy:
- **Fichier PHP**: Logique métier, props, computed properties
- **Fichier Twig**: Template avec `{{ this.prop }}`
- **Configuration**: Enregistrement automatique via `twig_component.yaml`

### Exemple: ProductCard.php
```php
<?php

namespace ModernaBundle\UiComponents\Product\ProductCard;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent(name: 'Moderna:ProductCard')]
class ProductCard
{
    #[ExposeInTemplate]
    public array $product;

    #[ExposeInTemplate]
    public string $variant = 'default'; // 'default'|'compact'|'featured'

    #[ExposeInTemplate]
    public bool $showWishlist = true;

    public function getPromoPercentage(): ?int
    {
        if (!isset($this->product['price_promo'])) {
            return null;
        }

        $regular = $this->product['price'];
        $promo = $this->product['price_promo'];

        return (int) round((($regular - $promo) / $regular) * 100);
    }
}
```

### Utilisation dans templates
```twig
{{ component('Moderna:ProductCard', {
    product: product,
    variant: 'compact',
    showWishlist: true
}) }}
```

---

## 🔗 Références

- [Symfony UX TwigComponent Documentation](https://symfony.com/bundles/ux-twig-component/current/index.html)
- Template Flexy: `templates/frontOffice/flexy/src/UiComponents/`
- Configuration: `config/packages/twig_component.yaml`
