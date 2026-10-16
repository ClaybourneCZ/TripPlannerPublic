# TripPlanner

A Laravel and Vue application for planning trips on a map, built as a personal
student portfolio and learning project. This is version **0.1**: an early
foundation for continued development.

## Live demo

TBA

## Tech stack

- Laravel 12 and PHP 8.2+
- Vue 3 and TypeScript
- Inertia.js
- Tailwind CSS
- Leaflet and OpenStreetMap
- SQLite by default; database connection can be configured for other supported
  relational databases

## About this repository

This public repository presents selected parts and snapshots of the TripPlanner
project for my student portfolio. The production application is developed in a
private repository. Some modules, infrastructure code, and production-specific
implementation details are intentionally not published. The public repository
may be updated periodically with selected project work.

## Featured implementation

- Laravel session-based registration, login, email verification, and account
  settings
- Inertia-powered pages connecting Laravel with Vue
- Route editor with interactive map points and route display
- Waypoint names, tags, and descriptions in the current browser-based prototype
- Shared Vue layouts and reusable interface components

Trip and waypoint data is not yet persisted in the database. Groups, chat,
sharing, exports, and place recommendations are ideas for future development,
not features included in this version.

## Architecture

See [docs/architecture.md](docs/architecture.md) for an introduction to the
project structure and current implementation.
