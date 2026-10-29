# LIKE-Json

[![Aktuelle Version](https://img.shields.io/github/package-json/v/Felix-LIKE/like-json-updated/main?label=Version)](https://github.com/Felix-LIKE/like-json-updated) [![GitHub License](https://img.shields.io/github/license/Felix-LIKE/like-json-updated)](https://github.com/Felix-LIKE/like-json-updated) [![GitHub issues](https://img.shields.io/github/issues/Felix-LIKE/like-json-updated)](https://github.com/Felix-LIKE/like-json-updated/issues)

WordPress-Plugin zur Darstellung der studentischen Arbeiten (Abschlussarbeiten) des
Lehrstuhls für Informationstechnik mit dem Schwerpunkt Kommunikationselektronik (LIKE).

**Version:** 2.1.0 · **Lizenz:** GPLv3 or later · **Sprache:** Deutsch / Englisch

> **Endnutzer-Dokumentation (webbasiert):** Dieses README ist die gepflegte, webbasierte
> Benutzerdokumentation (siehe auch die [Plugin-Beschreibung](readme.txt)). Sie wird im
> GitHub-Repository gepflegt und ist unter
> https://github.com/Felix-LIKE/like-json-updated/blob/main/README.md erreichbar.

---

## Inhaltsverzeichnis

1. [Über das Plugin](#über-das-plugin)
2. [Voraussetzungen](#voraussetzungen)
3. [Installation](#installation)
4. [Nutzung: Gutenberg-Block (empfohlen)](#nutzung-gutenberg-block-empfohlen)
5. [Nutzung: Legacy-Shortcode](#nutzung-legacy-shortcode)
6. [Einstellungen](#einstellungen)
7. [Externe Dienste und Daten](#externe-dienste-und-daten)
8. [Sicherheit](#sicherheit)
9. [Barrierefreiheit](#barrierefreiheit)
10. [Datenschutz](#datenschutz)
11. [Entwicklung und Tests](#entwicklung-und-tests)
12. [Mitwirken und Feedback](#mitwirken-und-feedback)
13. [Copyright](#copyright)

---

## Über das Plugin

LIKE-Json lädt die studentischen Arbeiten des LIKE-Lehrstuhls aus der LIKE-Datenbank und
stellt sie auf Ihrer WordPress-Website dar. Unterstützt werden:

- Bachelorarbeiten
- Masterarbeiten
- Forschungspraktika
- Projektarbeiten
- Sonstige Arbeiten
- Alle Kategorien zusammen

Die Daten werden live von der JSON-Schnittstelle des Lehrstuhls abgerufen. Änderungen an
der Datenbank (z. B. neu ausgeschriebene Themen) erscheinen daher automatisch auf der Website.

## Voraussetzungen

- WordPress 6.0 oder neuer
- PHP 8.0 oder neuer (getestet mit PHP 8.5)
- Der Webserver muss ausgehende HTTPS-Verbindungen erlauben (Standardkonfiguration)

## Installation

1. Laden Sie die ZIP-Datei des Plugins herunter und entpacken Sie sie nach `wp-content/plugins/like-json`.
2. Aktivieren Sie das Plugin im Menü **Plugins**.
3. Fügen Sie in einer Seite oder einem Beitrag den Block **Studentische Arbeiten (LIKE)** ein
   (siehe unten) – oder verwenden Sie den Legacy-Shortcode.

## Nutzung: Gutenberg-Block (empfohlen)

Das Plugin stellt einen Gutenberg-Block **Studentische Arbeiten (LIKE)** bereit.

1. Öffnen Sie eine Seite oder einen Beitrag im Block-Editor.
2. Klicken Sie auf **+** (Block hinzufügen) und suchen Sie nach **Studentische Arbeiten**.
3. Konfigurieren Sie den Block im Einstellungsbereich (rechte Seitenleiste):

| Einstellung | Bedeutung |
|---|---|
| **Aufgabe** | Welche Arbeiten angezeigt werden (Bachelorarbeiten, Masterarbeiten, Forschungspraktika, Projektarbeiten, Sonstige, Alle) |
| **Darstellung** | **Liste** oder **Akkordeon** (aufklappbare Einträge) |
| **PDF-Ressourcen** | **Link** (empfohlen, verlinkt die PDF-Datei) oder **Base64 eingebettet** (PDF wird in die Seite eingebettet) |
| **ID** | Nur die Arbeit mit dieser ID anzeigen (optional) |
| **Betreuer** | Nur Arbeiten eines bestimmten Betreuers anzeigen (optional, Teilstring, Groß-/Kleinschreibung egal) |
| **Status** | Nur Arbeiten mit diesem Vergabestatus anzeigen, z. B. `zu vergeben` (optional) |

Der Block wird serverseitig gerendert; eine Vorschau erscheint direkt im Editor.

## Nutzung: Legacy-Shortcode

Der Shortcode `[like]` wird **ausschließlich aus Abwärtskompatibilität** weitergeführt.
Für neue Inhalte verwenden Sie bitte den Gutenberg-Block.

```
[like task="bachelorarbeiten"]
[like task="arbeiten-alle" format="accordion"]
[like task="bachelorarbeiten" id="27"]
[like task="bachelorarbeiten" advisor="fischer"]
[like task="bachelorarbeiten" status="zu vergeben"]
[like task="bachelorarbeiten" ressources="link"]
```

| Attribut | Werte | Standard |
|---|---|---|
| `task` | `bachelorarbeiten`, `masterarbeiten`, `forschungspraktika`, `projektarbeiten`, `sonstiges`, `arbeiten-alle` | `bachelorarbeiten` |
| `format` | `liste`, `accordion` | `liste` |
| `id` | Nummer der Arbeit | leer |
| `advisor` | Teilstring des Betreuers | leer |
| `status` | Teilstring des Status | leer |
| `ressources` | `link`, `base64` | `link` |

> **Hinweis:** In früheren Versionen war der Task-Wert `sonstig` dokumentiert. Der korrekte
> Wert ist `sonstiges`.

## Einstellungen

Die Einstellungen finden Sie unter **Einstellungen → LIKE-Json**.

| Einstellung | Bedeutung |
|---|---|
| **JSON-Quelle (URL)** | Basis-URL der JSON-Daten (ohne Endpunktnamen). Standard: `https://like35.like.e-technik.uni-erlangen.de/json/` |
| **Timeout (Sekunden)** | Maximale Wartezeit für Remote-Anfragen (Standard: 10) |
| **User-Agent** | RRZE-konformer User-Agent für automatisierte Abrufe |

Auf einer WordPress-Multisite sind die Einstellungen ebenfalls ausschließlich lokal: Jede
Website verwaltet ihre eigene JSON-Quelle, ihr eigenes Timeout und ihren eigenen User-Agent.
Es gibt keine netzwerkweiten Einstellungen und keine Vererbung zwischen Websites.

> **Hinweis (bewusste Abweichung von der RRZE-SPEC):** Die RRZE-SPEC verlangt in den
> Abschnitten *1.2.1 „Multisite is mandatory“* und *14 „Single Site and Multisite“* eine
> vollständige Multisite-Fähigkeit inklusive sauberer Trennung von netzwerkweiten und
> site-spezifischen Einstellungen. Dieses Plugin ist bewusst **nur site-lokal** konfiguriert
> (keine Netzwerkweiten Einstellungen, keine Vererbung), weil es ausschließlich auf einzelnen
> Sites verwendet wird und keine netzwerkweiten Secrets oder zentralen Endpunkte verwaltet.
> Die site-lokalen Optionen funktionieren unter Multisite korrekt und unabhängig pro Site.
> Die Abweichung ist eine bewusste Entscheidung der Website-Verantwortlichen; bei einer
> Aufnahme in den zentralen RRZE-CMS-Betrieb ist sie mit dem RRZE-Webteam abzustimmen
> (siehe Abschnitte 1.2.1 und 14 der SPEC).

## Externe Dienste und Daten

| Eigenschaft | Wert |
|---|---|
| **Dienst** | JSON-Schnittstelle des LIKE-Lehrstuhls |
| **Anbieter** | Lehrstuhl für Informationstechnik mit dem Schwerpunkt Kommunikationselektronik (LIKE), FAU |
| **URL** | `https://like35.like.e-technik.uni-erlangen.de/json/` (konfigurierbar) |
| **Zweck** | Abruf der studentischen Arbeiten für die Anzeige auf der Website |
| **Übertragene Daten** | Öffentliche Arbeitsdaten (Titel, Beschreibung, Betreuer, Status, PDF-Links) |
| **Authentifizierung** | Keine (öffentliche Daten) |
| **Timeout-Verhalten** | Timeout konfigurierbar (1–60 s, Standard 10 s); bei Zeitüberschreitung wird ein Hinweis angezeigt |
| **Fehlerverhalten** | Bei nicht erreichbarer Quelle wird ein definierter Hinweis ausgegeben, die Website bleibt funktionsfähig |
| **Datenschutz** | Es werden ausschließlich öffentliche Daten abgerufen; keine personenbezogenen Daten übertragen |
| **Verlässt Daten die FAU?** | Nein – Quelle und Website liegen beide im FAU-Netz |

**User-Agent:** Das Plugin setzt bei allen Remote-Anfragen einen RRZE-konformen User-Agent
gemäß den [FAU-Crawler-Regeln](https://www.wp.rrze.fau.de/entwicklung/vorgaben/crawler/):

```
FAU-LIKE-like-json/2.1.0 (+https://www.like.tf.fau.de/;mailto:like-admin@fau.de)
```

**Caching:** Remote-Daten werden für 1 Stunde zwischengespeichert (Transient), um die Anzahl
der Anfragen zu begrenzen.

## Sicherheit

- **Escaping:** Alle Remote-Daten werden am Ausgabepunkt kontextgerecht escaped
  (`esc_html()`, `esc_url()`, `esc_attr()`). Es wird keine ungefilterte HTML-Ausgabe
  externer Daten erzeugt.
- **Validierung:** Remote-Antworten werden auf HTTP-Status und JSON-Struktur geprüft.
- **Remote Requests:** Es wird ausschließlich die WordPress-HTTP-API verwendet (kein
  `curl`/`file_get_contents` direkt). Die HTTPS-Verbindung wird mit einem mitgelieferten
  Intermediate-Zertifikat (GEANT TLS ECC 1) aufgebaut. Bei einem HTTPS-Fehler wird der
  Abruf abgebrochen; es erfolgt kein unsicherer Fallback auf HTTP.
- **Same-Origin-Lock (SSRF-Härtung):** Das Plugin ruft ausschließlich HTTPS-URLs auf dem
  in „source_url" konfigurierten Host ab – sowohl für die JSON-Endpunkte als auch für
  base64-PDF-Einbettungen. Dadurch kann weder eine manipulierte `source_url` noch ein
  `pdf`-Feld aus den Remote-Daten den Server als Proxy für beliebige Ziele missbrauchen.
  Da die Beschränkung über die Einstellung (nicht den Code) wirkt, kann das Plugin ohne
  Codeänderung auf einen anderen oder zusätzlichen LIKE-Server umgestellt werden.
- **https-Pflicht für source_url:** Die Einstellung „source_url" akzeptiert nur noch
  `https://`-Adressen; nicht-HTTPS-Adressen werden abgelehnt. Beim Update von einer
  älteren Version wird eine noch gespeicherte `http://`-Quelle **automatisch** auf
  `https://` korrigiert (Migration läuft einmalig beim ersten Laden nach dem Update).
- **PDF-Magic-Bytes:** Im base64-Modus wird eine PDF nur dann eingebettet, wenn der
  abgerufene Inhalt tatsächlich mit den PDF-Magic-Bytes (`%PDF-`) beginnt. Andernfalls
  wird auf einen einfachen Link zurückgefallen.
- **Antwortgrößen-Limits:** Alle Antworten sind hart begrenzt (JSON 2 MiB, PDFs 5 MiB,
  absolute Obergrenze 10 MiB); übermäßig große Antworten werden verworfen (DoS-Schutz).
- **Einstellungen:** Eingaben werden validiert und sanitized; die Ausgabe wird escaped.
- **Berechtigungen:** Die Einstellungsseiten sind serverseitig über WordPress-Capabilities
  geschützt (`manage_options`); Formulare verwenden Nonces.

## Barrierefreiheit

Die Ausgabe des Plugins verwendet semantisches HTML (Listen, Überschriften, Links) und
beschreibende Texte. Einzelne, nicht durch das Plugin beeinflussbare Aspekte (z. B. die
Barrierefreiheit des Akkordeon-Plugins RRZE-Elements) hängen vom jeweiligen Theme bzw. den
eingesetzten Blöcken ab. Eine vollständige WCAG-Prüfung der Live-Ausgabe erfolgt auf der
Produktionsinstanz.

## Datenschutz

Das Plugin speichert keine personenbezogenen Daten und setzt **keine Cookies**.
Es ruft ausschließlich öffentliche Arbeitsdaten aus der LIKE-Datenbank ab. Beim Deaktivieren
bleiben die gespeicherten Einstellungen erhalten; beim **Deinstallieren** werden die
Plugin-eigenen Einstellungen entfernt.

## Entwicklung und Tests

Voraussetzungen: Node.js ≥ 18, npm ≥ 9, PHP ≥ 8.0.

```bash
# Abhängigkeiten installieren
npm install
composer install   # oder: php bin/composer install

# Block-Assets bauen (in build/)
npm run build

# Prüfungen
npm run lint:js                 # ESLint (WordPress-Standard)
npm run build                   # baut die Block-Assets
./bin/composer phpcs            # WordPress Coding Standards
./bin/composer lint:php         # PHP-Lint
./bin/composer test             # Automatisierte Tests (40 Assertions)

# Versions-Synchronisation prüfen/korrigieren
node bin/sync-version.js
node bin/sync-version.js --fix
```

Die Metadaten (Version, WordPress-/PHP-Kompatibilität, Repository) werden zentral in
`package.json` gepflegt und per `bin/sync-version.js` in Plugin-Header, `readme.txt` und
Konstanten synchronisiert.

## Mitwirken und Feedback

- **Feedback / Issues:** https://github.com/Felix-LIKE/like-json-updated/issues
- **E-Mail:** like-admin@fau.de
- **Verantwortlich:** Lehrstuhl für Informationstechnik mit dem Schwerpunkt Kommunikationselektronik (LIKE)

## Copyright

© 2026 LIKE – Lehrstuhl für Informationstechnik mit dem Schwerpunkt Kommunikationselektronik

Dieses Plugin ist freie Software, veröffentlicht unter der
[GNU General Public License, Version 3](https://www.gnu.org/licenses/gpl-3.0.html).
