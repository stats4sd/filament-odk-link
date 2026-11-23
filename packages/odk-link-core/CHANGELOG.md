# Changelog

All notable changes to `filament-odk-link` will be documented in this file.

## Unreleased

- **Breaking:** requires `maatwebsite/excel` ^4.0 (and therefore `phpoffice/phpspreadsheet` ^5). Host apps that require Laravel Excel 3.1 directly must upgrade alongside this package; see Laravel Excel's `UPGRADE-4.x.md`. Drain queues before deploying, as queued 3.1 import/export jobs may not unserialize under 4.0.

## 1.0.0 - 202X-XX-XX

- initial release
