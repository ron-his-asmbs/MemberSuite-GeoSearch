# MemberSuite GeoSearch

A WordPress plugin that syncs member data from the MemberSuite API and provides a geographic search interface for finding members by proximity.

## Features

- Syncs member data from MemberSuite including name, email, and credentials
- Fetches and stores geocoded lat/long coordinates for each member's practice address
- Batch processing with progress bar to avoid server timeouts
- Automatic hourly sync via WordPress cron
- Front-end member search using Google Maps
- Search by address with configurable radius
- Results displayed as a sortable list and interactive map with pins
- Detailed sync logging to a local log file

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Composer
- MemberSuite account with API access
- Google Maps API key with Maps JavaScript API and Geocoding API enabled

<img width="281" height="272" alt="image" src="https://github.com/user-attachments/assets/7c16623d-13bc-44a8-a996-7274129bee84" />
