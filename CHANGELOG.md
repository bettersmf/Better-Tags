# Changelog

All notable changes to Better Tags will be documented here.

## 0.1.1

Maintenance and stable-release audit update.

### Fixed

- Removed the latent direct-action routing issue that could trigger a PHP argument-count fatal on the unused legacy `action=tagfollow` route.
- Preserved the supported Follow / Unfollow workflow through the tag action controller.
- Added direct upgrade support from Better Tags 0.1.0.
- Normalized release versioning and asset cache identity.

### Verified

- PHP and JavaScript syntax
- Package manifest/file coverage
- Language-key coverage
- Admin/session protections
- Tag follow/unfollow permissions
- Board visibility on tag results
- Per-recipient visibility checks on tag-follow alerts
- Better Gallery and Better Downloads integrations
- Hook uninstall symmetry and data-preserving uninstall behavior

## 0.1.0

Initial public release.

### Added

- Topic tagging for SMF 2.1.x
- Tag autocomplete
- Shared tag result pages
- Follow / Following workflow with SMF alerts
- Administration Tag Manager
- Rename, merge, delete, and bulk tag cleanup tools
- Orphan cleanup support
- Better Gallery album integration
- Better Gallery media integration
- Better Downloads integration
- Gallery media deep-link support
- Shared results for Forum Topics, Gallery Albums, Gallery Media, and Downloads
- Integration hooks for supported Better SMF MODs
- Optional data removal on uninstall
- Custom Better Tags administration icons

### Hardened

- Language fallback handling
- Tag creation concurrency with normalized unique tags
- Follower orphan cleanup
- Delete and bulk-cleanup paths
- Theme Lab delayed widget initialization compatibility
- Uninstall hook parity and data-preservation behavior
