# Kunden Link Tracker – WordPress Plugin

Das Plugin liegt im Ordner `kunden-link-tracker/`.

## Installation
1. Ordner `kunden-link-tracker` nach `wp-content/plugins/` kopieren.
2. Plugin in WordPress aktivieren.

## Updates über GitHub
Das Plugin sucht nach neuen Releases im GitHub-Repository und meldet diese in der WordPress-Pluginverwaltung.
Automatische Updates sind für dieses Plugin standardmäßig aktiviert.

Standard-Repository:
- `https://github.com/DWHS-BIZ/WP_Nachverfolgung`

Optional kannst du das Ziel-Repository per Filter überschreiben:
- `klt_github_repository` (Format: `owner/repo`)

## Verhalten
- Aufruf ohne `campaign`-Parameter: **keine Speicherung**.
- Aufruf mit unbekanntem `campaign`-Code: **keine Speicherung**.
- Aufruf mit gültigem `campaign`-Code: Besuch wird der Kampagne zugeordnet.
