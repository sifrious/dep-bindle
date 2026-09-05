# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
- Adds first version
- Records the browser driver on every run, so a placeholder scan cannot be
  mistaken for a real one after the fact
- Panel scans choose their driver, and a request for real screenshots is refused
  with the reasons rather than silently downgraded to placeholders
- Panel lists the unmet Dusk requirements and offers a button for each fix that is
  an Artisan command
- Panel-spawned scans write to `.bindle/scan.log` instead of `/dev/null`, and the
  status page shows the tail plus any errors the run recorded
- `bindle:scan --driver=dusk` checks its preconditions up front and diagnoses the
  common Chrome/ChromeDriver/connection failures
- `/_bindle/wireframe-board` adds a documents-first board view with named regions
  and explicit `empty` / `loading` / `error` / `populated` states
