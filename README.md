# Kunden Link Tracker – WordPress Plugin

Installierbares Plugin im Ordner `kunden-link-tracker/`.

## Inhalt
- `kunden-link-tracker/` – Plugin-Ordner für WordPress
- `scripts/build-zip.sh` – erzeugt eine installierbare ZIP lokal

## ZIP lokal erzeugen (nicht im Git)
```bash
bash scripts/build-zip.sh
```
Danach liegt die Datei unter:
- `dist/kunden-link-tracker.zip`

## Installation in WordPress
1. In WordPress: **Plugins → Installieren → Plugin hochladen**.
2. Datei `dist/kunden-link-tracker.zip` auswählen.
3. Aktivieren.
4. Im Backend den Menüpunkt **Kunden Tracker** öffnen.

## Verhalten
- Aufruf ohne `campaign`-Parameter: **keine Speicherung**.
- Aufruf mit unbekanntem `campaign`-Code: **keine Speicherung**.
- Aufruf mit gültigem `campaign`-Code: Besuch wird der Kampagne zugeordnet.
