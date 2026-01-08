# Add Recently Viewed Products Alpine Store

## Overview

Create a new Alpine.js store to track and persist recently viewed products, allowing users to see their browsing history across sessions. The store will follow the exact same pattern as the existing wishlist store with local persistence using Alpine's $persist plugin.

## Rationale

The wishlist store pattern in app.js demonstrates a fully working local persistence mechanism with Alpine.$persist. This same pattern can be directly applied to track recently viewed products. The product data structure already exists (id, title, url, image, imageId, price, promoPrice, isPromo) from wishlistButton component.

---
*This spec was created from ideation and is pending detailed specification.*
