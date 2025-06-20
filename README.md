# OpenCart Mobile Admin API Module

A lightweight REST API extension that lets you control your OpenCart store directly from our official **OpenCart Mobile Admin** Android application.

![OpenCart Mobile Admin](https://img.shields.io/badge/OpenCart-2.x%20%E2%80%93%203.x-blue?logo=opencart)

## Features

• View and manage orders, products, customers, categories and statistics from your phone.

• Secure authentication using JSON Web Tokens (JWT).

• Compatible with OpenCart **2.0.0.0 – 3.x** (tested up to 3.0.3.9).

• Installs via the standard Extension Installer (OCMOD, **no core file changes**).

## Quick Start

1. Sign-in to the OpenCart admin panel as *Administrator*.
2. Navigate to **Extensions ➜ Installer**.
3. Click **Upload** and choose `apimobile.ocmod.zip` from this repository.
4. After a successful upload go to **Extensions ➜ Extensions** and select **Modules** from the drop-down list.
5. Locate **Mobile API** and click the green **Install** button.
6. Edit the module, set a **Secret Key** (used to sign JWT tokens) and enable the status.

> ⚠️  The module will not work while your store is in **Maintenance Mode**.

### Updating
Re-install a newer `apimobile.ocmod.zip` through the Extension Installer. Your settings will be preserved.

## Authentication
Every request must include a valid JWT token in the `Authorization` header:

```http
Authorization: Bearer <your-jwt-token>
```

Obtain the token via:

```
POST /index.php?route=api/mobile/login
{
  "username": "admin@example.com",
  "password": "your_admin_password"
}
```

Full API documentation is available in the `docs/` folder.

## Companion App
Download the official Android application from Google Play:
<https://play.google.com/store/apps/details?id=com.pinta.opencart.opencartmobileadmin>

## Troubleshooting
• Make sure FTP and Extension Installer are configured correctly.

• If you get a *"Could not create directory"* error during upload, install the free fix from the OpenCart Marketplace:
<https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=18892>

## License
This project is distributed under the MIT license. See `LICENSE` for full text.
