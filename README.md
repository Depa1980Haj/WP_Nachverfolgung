# Kunden Link Tracker – WordPress Plugin

Das Plugin liegt im Ordner `kunden-link-tracker/`.

## Installation
1. Ordner `kunden-link-tracker` nach `wp-content/plugins/` kopieren.
2. Plugin in WordPress aktivieren.

## Updates über GitHub
Wenn im GitHub-Repository eine neue Release-Version (Tag, z. B. `v1.3.0`) vorhanden ist,
wird diese in der WordPress-Pluginverwaltung als Update angeboten und kann direkt aktualisiert werden.

Repository: `https://github.com/DWHS-BIZ/kunden-link-tracker`

## Verhalten
- Aufruf ohne `campaign`-Parameter: **keine Speicherung**.
- Aufruf mit unbekanntem `campaign`-Code: **keine Speicherung**.
- Aufruf mit gültigem `campaign`-Code: Besuch wird der Kampagne zugeordnet.
