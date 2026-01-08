# Badge Component

Composant réutilisable pour afficher des badges de contenu dans le template moderna.

## Usage

### Badge New

```twig
{{ component('Moderna:Badge', {
    type: 'new'
}) }}
```

### Badge Promo

```twig
{{ component('Moderna:Badge', {
    type: 'promo',
    value: 25
}) }}
```
Affiche: `-25%`

### Badge Default

```twig
{{ component('Moderna:Badge', {
    type: 'default',
    text: 'Default'
}) }}
```

### Badge Status

```twig
{{ component('Moderna:Badge', {
    type: 'status',
    statusCode: 'delivered',
    text: 'Delivered'
}) }}
```

## Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `type` | string | required | Type de badge: 'new', 'promo', 'default', 'status' |
| `text` | string | null | Texte custom (optionnel, auto-généré selon type) |
| `value` | int | null | Valeur pour type='promo' (ex: 25 pour -25%) |
| `statusCode` | string | null | Code status pour type='status' (paid, delivered, etc.) |

## Comportement des Traductions

- Les types `new` et `default` appliquent automatiquement le filtre `|trans` au texte
- Les types `promo` et `status` affichent le texte brut sans traduction
- Pour un texte personnalisé traduit sur type `promo` ou `status`, passez la traduction via le prop `text`

## Status Codes Supportés

Le composant mappe automatiquement les codes status aux couleurs:

| Status Code | Couleur | Exemples |
|-------------|---------|----------|
| paid, processing | blue | Payé, En traitement |
| sent, delivered | green | Expédié, Livré |
| canceled, refunded | red | Annulé, Remboursé |
| pending, not_paid | amber | En attente, Non payé |

## Computed Properties

Le composant calcule automatiquement:

- `this.color` - Couleur basée sur type/statusCode
- `this.text` - Texte à afficher
- `this.cssClass` - Classes CSS complètes
- `this.hasIcon` - true pour status (affiche le dot)

## Accessibilité

- Les badges de type `status` incluent automatiquement `role="status"` pour les lecteurs d'écran
- L'icône de statut (dot) est marquée `aria-hidden="true"` car elle est purement décorative
- Tous les textes sont échappés automatiquement pour prévenir les attaques XSS

## CSS Classes

Base: `.badge`
Couleurs: `.badge--green`, `.badge--red`, `.badge--blue`, `.badge--amber`, `.badge--gray`
Icon: `.badge-icon` (dot coloré)

## Files

- PHP: `src/UiComponents/Common/Badge/Badge.php`
- Twig: `src/UiComponents/Common/Badge/Badge.html.twig`
- CSS: `assets/css/components/_badge.css`
