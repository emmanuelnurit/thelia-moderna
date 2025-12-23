# Moderna Template for Thelia 3.1

A modern, responsive front-office template for Thelia e-commerce platform.

## Features

- Modern, clean design with Alpine.js interactivity
- Responsive layout for all devices
- Customer wishlist functionality
- AJAX-based cart and checkout
- Multi-address checkout support

## Installation

### 1. Copy template files

Copy the `moderna` folder to your Thelia installation:
```
templates/frontOffice/moderna/
```

### 2. Run database setup

Execute the SQL installation script to create required tables:

```bash
# Using DDEV
ddev mysql < templates/frontOffice/moderna/setup/install.sql

# Or using MySQL directly
mysql -u [user] -p [database] < templates/frontOffice/moderna/setup/install.sql
```

### 3. Activate the template

In Thelia admin, go to **Configuration > Templates** and activate the Moderna template.

### 4. Build assets (optional for development)

```bash
cd templates/frontOffice/moderna
npm install
npm run build
```

## Database Tables

The template creates the following additional tables:

- `customer_wishlist` - Stores customer product favorites

## Uninstallation

To remove template-specific database tables:

```bash
# Using DDEV
ddev mysql < templates/frontOffice/moderna/setup/uninstall.sql

# Or using MySQL directly
mysql -u [user] -p [database] < templates/frontOffice/moderna/setup/uninstall.sql
```

## Requirements

- Thelia 3.1+
- PHP 8.1+
- Node.js 18+ (for asset building)
