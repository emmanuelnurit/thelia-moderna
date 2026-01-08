-- Moderna Template Uninstallation SQL
-- This file is executed during template uninstallation

-- Remove Customer Wishlist table
DROP TABLE IF EXISTS customer_wishlist;

-- Remove Customer Recently Viewed table
DROP TABLE IF EXISTS customer_recently_viewed;
