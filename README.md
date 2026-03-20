# WP to Social

A lightweight WordPress plugin that lets you share posts and custom post types to social media platforms directly from the editor.

Currently supports **LinkedIn** and **Instagram** with more platforms planned.

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

## Setup (Instagram)

Instagram publishing requires an **Instagram Business or Creator account** linked to a **Facebook Page**.

1. Create a Meta app at [developers.facebook.com](https://developers.facebook.com/)
2. Add the **Instagram Graph API** product to your app
3. Request these permissions via App Review: `instagram_basic`, `instagram_content_publish`, `pages_read_engagement`, `pages_show_list`
4. In the plugin settings, enter your **App ID** and **App Secret**
5. Copy the displayed **OAuth Redirect URI** into your Meta app's Valid OAuth Redirect URIs
6. Click **Connect with Instagram** and authorize via Facebook Login

**Important limitations:**
- Instagram requires an image for every post (no text-only posts)
- Images must be publicly accessible (not behind authentication or localhost)
- Rate limit: approximately 12 posts per day (25 API calls, each post uses 2-3)
- Caption max: 2,200 characters; URLs in captions are not clickable

## Usage

1. In **Settings > Post Types**, select which post types should be shareable to LinkedIn
2. Optionally configure **Field Mapping** to map LinkedIn fields to custom post fields
3. When creating or editing a post, check **Post to LinkedIn** in the publish panel
4. Publish the post — it will be shared to LinkedIn automatically
5. Monitor results in **WP to Social > Activity**

## License

GPL-2.0-or-later
