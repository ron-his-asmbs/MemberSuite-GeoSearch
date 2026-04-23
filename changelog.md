# Changelog

All notable changes to the MemberSuite GeoSearch plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.1] - 2026-04-23
### Added
- Added profile picture support

## [1.3.0] - 2026-04-20
### Added
- Released for public use

## [1.2.4] - 2026-04-20
### Added
- Provider phone/address syncing

## [1.2.3] - 2026-04-20
### Fixed
- Encoding issue in MSQL query string causing 500 error from MemberSuite API

## [1.2.2] - 2026-04-20
### Fixed
- Fixed issues with logging and syncing problems on local

## [1.2.1] - 2026-04-16
### Fixed
- Removed hard dependency on plugin-level vendor/autoload.php
- Dependencies now handled by root project Composer autoloader

## [1.2.0] - 2026-04-16
### Added
- `surgery_types` field synced from MemberSuite `surgeryTypes__c` array and stored as JSON
- `city`, `state`, `country` fields synced from `practice_Address`
- `member_type` field synced from `designation` in member list response
- `practicing` field synced from `practicing__c`
- `MS_Provider` class to wrap database rows with same interface as old `ProviderPublic` class
- `MS_ProviderAddress` class to provide `getCity()`, `getState()`, `getCountry()` methods
- Provider search page now queries `gent_member_geodata` instead of old AMS API
- Surgery type dropdown on search form populated from database
- Distance search using Haversine formula in MySQL
- Name search filtering by first and last name
- Member type filtering by designation
- Surgery type filtering via JSON `LIKE` query
- Pagination of search results at 20 per page
- `getDisplayName()` and `getProfileLink()` methods on `MS_Provider` to bypass old `CMSPlugin` type requirements
- WordPress user lookup via `mem_key` user meta for photo integration
- Static cache on `getWordPressUserId()` to prevent duplicate DB queries per page load
- Excluded `None Performed` from surgery type sync and dropdown

### Changed
- `sync_members()` now delegates to `sync_members_batch()` to keep insert logic in one place
- `getMemberGeoData()` now returns `surgery_types`, `city`, `state`, `country` in addition to lat/long
- Provider search template updated to use `instanceof MS_Provider` checks for display name and profile link
- Surgery type component bypasses `get_provider_surgeries()` for `MS_Provider` since names are already plain strings

### Fixed
- `sync_members_batch()` was referencing `$data` instead of `$geo` for `surgery_types`
- `member_type` was incorrectly sourced from `$geo` instead of `$entry['designation']`
- Double comma in Haversine SQL query causing MariaDB syntax error
- `$providers[0]` fatal error when search returned no results
- Duplicate `getWordPressUserId()` definition in `Provider.php`
- Stale transient cache causing manual sync to report 0 members

## [1.1.0] - 2026-03-25
### Added
- Ajax-based batch sync with progress bar to avoid nginx 504 gateway timeouts
- Batch size of 15 members per request to stay within 60 second nginx timeout
- Retry logic in JavaScript — failed batches retry up to 3 times with 3 second delay
- Transient cache for member list (`ms_members_cache`) to avoid re-fetching 2898 members per batch
- Cache cleared at offset 0 on every manual sync
- Cache cleared on sync completion
- File-based sync logging to `sync.log` in plugin directory
- `ignore_user_abort(true)` on cron sync to prevent interruption

### Changed
- Reduced batch size from 50 to 15 to prevent 504 timeouts
- `sync_members()` and `sync_members_batch()` unified to share insert logic

### Fixed
- Transient returning empty array `[]` instead of `false` causing sync to report 0 members
- Token fetched once per sync instead of once per member API call
- `handle_sync()` was calling `executeSearchDirectoryIndividuals()` directly without inserting to database

## [1.0.0] - 2026-03-01
### Added
- Initial plugin release
- `gent_member_geodata` database table with `id`, `member_id`, `first_name`, `last_name`, `email`, `latitude`, `longitude`, `last_updated`
- MemberSuite API authentication via JWT token from `/platform/v2/loginUser`
- Member list sync via MSQL query to `/platform/v2/dataSuite/executeSearch`
- Individual member geo data sync via `/crm/v1/individuals/{memberId}`
- Lat/long sourced from `practice_Address.geocodeLat` and `practice_Address.geocodeLong`
- WordPress cron scheduled daily sync
- Manual sync button in WordPress admin under Settings → MS Plugin
- Settings page for `MS_SURGEON`, `MS_INTEGRATED_HEALTH` member type GUIDs and Google Maps API key
- Credentials loaded from `.env` via `vlucas/phpdotenv`
- Guzzle HTTP client via Composer for API requests
- `[member_search]` shortcode for public-facing member search
- Google Maps geocoding for address-to-coordinates conversion
- Haversine distance query for proximity search
- Search results displayed as sortable table and Google Map with pins
- Default search radius of 25 miles with options for 50 and 100 miles