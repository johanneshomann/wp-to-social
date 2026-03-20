# WP to Social

A lightweight WordPress plugin that lets you share posts and custom post types to social media platforms directly from the editor.

Currently supports **LinkedIn** with more platforms planned.

## Features

- **Module-based architecture** — enable only the platforms you need
- **Custom Post Type support** — choose which post types are eligible per platform
- **Field mapping** — map platform fields (title, body, URL, image) to any post field or custom field
- **One-click publishing** — check a box in the editor to share on publish
- **Activity log** — track all posting attempts with status, errors, and retry options
- **Encrypted credentials** — API keys and tokens are stored using AES-256-GCM encryption
- **Duplicate prevention** — payload hashing prevents accidental double-posts

## Requirements

- WordPress 6.0+
- PHP 7.4+
- `AUTH_SALT` defined in `wp-config.php` (standard on all WordPress installs)
- OpenSSL PHP extension

## Installation

1. Download or clone this repository into `wp-content/plugins/wp-to-social/`
2. Activate the plugin in **Plugins > Installed Plugins**
3. Navigate to **WP to Social > Settings**

## Setup (LinkedIn)

1. Create a LinkedIn app at [linkedin.com/developers](https://www.linkedin.com/developers/)
2. Add the **Sign In with LinkedIn using OpenID Connect** and **Share on LinkedIn** products
3. In the plugin settings, enter your **Client ID** and **Client Secret**
4. Copy the displayed **OAuth Redirect URI** into your LinkedIn app's redirect URLs
5. Click **Connect with LinkedIn** and authorize the app

## Usage

1. In **Settings > Post Types**, select which post types should be shareable to LinkedIn
2. Optionally configure **Field Mapping** to map LinkedIn fields to custom post fields
3. When creating or editing a post, check **Post to LinkedIn** in the publish panel
4. Publish the post — it will be shared to LinkedIn automatically
5. Monitor results in **WP to Social > Activity**

## License

GPL-2.0-or-later
