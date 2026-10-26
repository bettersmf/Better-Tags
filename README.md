# Better Tags

**Shared tagging for SMF 2.1.x topics, Better Gallery albums/media, and Better Downloads.**

Better Tags adds a clean tagging system to SMF and extends that same tag vocabulary across supported Better SMF MODs. Tags can connect forum topics, Gallery content, and Downloads through one shared results page instead of creating separate tagging silos.

> **One tag system. Forum-wide discovery.**

## Features

- Add tags to SMF topics
- Tag autocomplete while entering tags
- Shared tag result pages
- Follow tags and receive SMF alerts
- View and manage followed tags
- Administration Tag Manager
- Rename, merge, delete, and bulk-clean tags
- Orphan cleanup tools
- Better Gallery album tagging
- Better Gallery media tagging
- Better Downloads item tagging
- Deep links to tagged Gallery media
- Shared results can show Forum Topics, Gallery Albums, Gallery Media, and Downloads
- Integration hooks for supported Better SMF MODs
- Uninstall can preserve tag data unless explicit data removal is enabled
- PHP 8.x friendly

## Current Version

**0.1.1**

Better Tags 0.1.1 is the current audited stable release. It includes the v0.1.0 feature set plus the stable-release routing fix for the legacy direct follow action, complete upgrade coverage from v0.1.0, and normalized release asset identity.

## Compatibility

- Simple Machines Forum **2.1.x**
- Developed and tested on modern SMF 2.1 releases
- PHP 8.x friendly
- Better Gallery integration is optional
- Better Downloads integration is optional

Better Tags is designed so the core topic-tagging system continues to work whether or not the optional Better SMF integrations are installed.

## Installation

1. Download the latest Better Tags package from the GitHub **Releases** section.
2. In SMF, open **Administration Center → Main → Package Manager → Download Packages**.
3. Upload the Better Tags ZIP.
4. Install the package normally.
5. Open **Administration Center → Configuration → Better Tags**.
6. Configure tagging permissions and any optional Better Gallery / Better Downloads integrations.

## Tag Manager

The administration Tag Manager provides tools to maintain the shared tag vocabulary, including:

- Search and sort tags
- Rename tags
- Merge duplicate or overlapping tags
- Delete tags
- Bulk selection and cleanup
- Remove orphaned relations

## Following Tags

Members with permission can follow tags they care about. Better Tags uses SMF's alert system to surface relevant activity and provides a dedicated view for followed tags.

## Better Gallery Integration

When enabled, Better Tags can tag:

- Albums
- Individual media items

Tagged Gallery media can link directly to the relevant media/lightbox location from a shared tag results page.

## Better Downloads Integration

When enabled, Better Tags can attach the same shared tags to Better Downloads items, allowing downloads and forum content to be discovered from the same tag page.

## Project Structure

```text
Sources/
  BetterTags.php

Themes/default/
  css/
    better_tags.css
  images/admin/
    better_tags.png
    big/
      better_tags.png
  languages/
    BetterTags.english.php
  scripts/
    better_tags.js

install.php
uninstall.php
package-info.xml
```

## Support & More Better SMF MODs

Better Tags is part of the **Better SMF** project.

Visit **https://bettersmf.com/** for support, discussion, downloads, and the rest of the Better SMF MOD ecosystem.

## Development Philosophy

Better SMF MODs are built around a simple idea:

**Keep SMF familiar. Make it better.**

Better Tags follows that philosophy by adding powerful discovery and organization without turning normal SMF posting into a complicated workflow.

## License

A project license will be published separately. Until then, all rights are reserved unless otherwise stated.
