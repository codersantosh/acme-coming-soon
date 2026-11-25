# Maintenance mode behavior

## Overview

Maintenance mode redirects unauthorized frontend visitors to a selected published
WordPress page. The selected page is never deleted by the plugin.

A valid configuration uses an HTTP 302 redirect by default. The redirect URL and
status are filterable.

## Access policy

| Setting | Who bypasses maintenance mode |
| --- | --- |
| None | No authenticated user bypasses by access policy |
| Admin only | Users with `manage_options` |
| Login user | Any authenticated user |
| Selected roles | Users with one of the configured role slugs |

Network administrators can manage network-active installations.

## Exclusions

- **Search bots:** User-Agent based and spoofable; not an authentication boundary.
- **IP addresses:** Exact IPv4/IPv6 matches only. CIDR is not currently supported.
- **URL paths:** Matched against the normalized path only. Query strings are never
  considered. Exact paths and explicit path prefixes are supported.

## Public-content boundary

While maintenance mode is active, unauthorized visitors are blocked from:

- Frontend pages
- WordPress sitemaps
- Feeds
- Public REST endpoints
- oEmbed
- XML-RPC
- No-privilege AJAX actions

Authorized users, `wp-admin`, `wp-login.php`, the plugin's own settings API, and
explicitly allowlisted integrations remain available.

## Invalid configuration

Maintenance mode cannot be enabled without a valid published page.

If a legacy or unexpectedly modified configuration contains no valid page, the
plugin fails closed with HTTP 503, `Retry-After`, and no-cache headers instead of
leaving the site public. Administrators can still access the site to correct it.

## Caching

The plugin sends no-cache headers and triggers cache-purge actions when settings
change. A full-page cache or CDN that serves HTML without running WordPress may
still require its own maintenance-mode configuration.

## Filters

| Filter | Purpose |
| --- | --- |
| `acme_coming_soon_should_block_request` | Override the block decision for a request context |
| `acme_coming_soon_allowed_rest_routes` | Allow REST routes or namespace prefixes |
| `acme_coming_soon_allow_rest_request` | Allow a specific REST request |
| `acme_coming_soon_allowed_ajax_actions` | Allow logged-out AJAX actions |
| `acme_coming_soon_allow_ajax_request` | Allow a specific AJAX request |
| `acme_coming_soon_redirect_url` | Override the maintenance destination |
| `acme_coming_soon_redirect_status` | Override the redirect status |
| `acme_coming_soon_cancel_redirect` | Cancel the redirect and fail closed |
| `acme_coming_soon_retry_after` | Override the 503 `Retry-After` value |
| `acme_coming_soon_maintenance_message` | Override the fail-closed message |
| `acme_coming_soon_purge_cache` | Purge external caches after settings changes |
