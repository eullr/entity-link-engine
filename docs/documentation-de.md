# Entity Link Engine — Dokumentation

**Version:** 1.0.0 · **Autor:** Eugen Ullrich · **Lizenz:** GPLv2 oder neuer

Entity Link Engine baut die interne Linkstruktur Ihrer Website so auf, wie es
ein Retrieval-System tun würde: Es ordnet die **Entitäten** Ihres Inhalts den
Beiträgen zu, die sie beantworten, erweitert jeden Beitrag zu **Fan-out-Queries**,
bewertet die gefundenen Kandidaten und fügt erst dann interne Links ein. Es setzt
niemals zufällige oder schwache Links.

---

## 1. Funktionsweise

Jeder Lauf ist eine Pipeline aus vier Stufen:

### 1.1 Entity-Mapping

Das Plugin pflegt einen **Entitäts-Index**: eine Tabelle, die jede indizierte
Wendung auf den Beitrag (oder die Beiträge) abbildet, auf den sie zeigt. Der
Index entsteht aus zwei Quellen:

| Quelle | Was indiziert wird | Gewicht |
|---|---|---|
| Beitragstitel | Vollständiger Titel (2–8 Wörter) | 100 |
| Beitragstitel | Lange Einzelwörter (≥ 5 Zeichen) | 25 |
| Überschriften | H2/H3-Text (2–6 Wörter, keine Anleitungs-Überschriften) | 60 |
| Tags | Tag-Namen | 50 |
| Kategorien | Kategorie-Namen | 40 |
| **Manuelles Vokabular** | Wendung + Aliase + Zielbeitrag | 1000 + Priorität |

Vor dem Abgleich wird jede Wendung **normalisiert**: Kleinschreibung, Diakritika
entfernt (NFKD), `ß → ss`, Leerzeichen zusammengefasst. „SEO-Audit" und
„SEO Audit" werden so zu einem Schlüssel (`seo-audit`).

Das **manuelle Vokabular gewinnt immer** gegen automatisch extrahierte
Entitäten. Definieren Sie eindeutige, mehrteilige Wendungen (so wie ein
kontrolliertes Vokabular sein sollte) — kurze generische Begriffe erzeugen
bewusst schwache Anker und werden deshalb gar nicht erst indiziert.

Der Abgleich erzwingt **Wortgrenzen** und behandelt Bindestrich und Unterstrich
als Teil des Wortes. Dadurch verlinkt das Plugin niemals das „audit" in
„GEO-Audit", sehr wohl aber „GEO-Audit" als Ganzes.

### 1.2 Fan-out-Queries

Ein Beitrag wird zu vielen Retrieval-Queries, jede mit eigenem Gewicht:

| Query-Typ | Quelle | Gewicht |
|---|---|---|
| `title` | Vollständiger Titel | 1,0 |
| `heading` | Jede H2/H3 | 0,8 |
| `entity` | Jede gefundene Entitäts-Erwähnung | 0,7 |
| `tag` | Jeder Tag | 0,6 |
| `category` | Jede Kategorie | 0,5 |
| `ngram` | Titel-Bigramme | 0,4 |

Jede Query wird tokenisiert (Stoppwörter entfernt) und gegen den Entitäts-Index
ausgeführt.

### 1.3 Retrieval und Scoring

Kandidaten werden aus sechs Signalgruppen bewertet:

| Signal | Beitrag |
|---|---|
| Gemeinsame Tags | +2,0 je Treffer (max. 4) |
| Gemeinsame Kategorien | +1,5 je Treffer (max. 3) |
| Gemeinsame Ziele (`_elink_objectives`) | +3,0 je Treffer (max. 3) |
| Gleiches Format (`_elink_use_tag`) | +0,5 |
| Titel-Term-Überschneidung | +1,25 je Treffer (max. 4) |
| Stütz-Term-Überschneidung | +0,25 je Treffer (max. 4) |
| Fan-out-Abdeckung (verschiedene Query-Typen) | +0,75 je Typ (max. 4) |
| Quelle verlinkt Kandidat bereits | +2,5 |
| Kandidat verlinkt Quelle bereits | +1,5 |

Kandidaten unter dem **Mindest-Score** (Standard 2,5) fallen weg; die stärksten
`max_links` (Standard 3) bleiben, bei Gleichstand gewinnt der neuere Beitrag.

### 1.4 Sichere Einfügung

Links werden nur eingefügt, wenn alle Regeln gelten:

- nur an Wortgrenzen (niemals innerhalb eines längeren Wortes);
- nie in Überschriften, Code, `pre`, Tabellen, Zitaten, Abbildungen oder Bildern;
- nie innerhalb eines bestehenden Links;
- nie eine URL, die im Beitrag bereits vorhanden ist;
- höchstens **ein Auto-Link pro Absatz** (auch über wiederholte Läufe);
- bestehende redaktionelle Links werden nie verändert oder entfernt.

Eingefügte Links tragen `class="elink-auto-link"` und `data-elink="<beitrags-id>"`
für Reporting und sichere Wiederholungsläufe.

---

## 2. Installation

1. Laden Sie den Ordner `entity-link-engine` nach `/wp-content/plugins/` hoch
   oder installieren Sie das Plugin über das WordPress-Admin.
2. Aktivieren Sie das Plugin unter **Plugins**.
3. Gehen Sie zu **Entity Links → Stapellauf** und klicken Sie
   **Entitäts-Index neu aufbauen**.

Voraussetzungen: WordPress 6.0+, PHP 7.4+.

---

## 3. Schnellstart

1. **Index aufbauen** — *Entity Links → Stapellauf → Entitäts-Index neu aufbauen*.
2. **Manuelle Entitäten anlegen** — *Entity Links → Entitäts-Vokabular*:
   Wendung, kommagetrennte Aliase und Zielbeitrag für Ihre wichtigsten Seiten
   (z. B. `SEO-Audit → /seo-audit/`).
3. **Vorschläge ansehen** — öffnen Sie einen Beitrag; die *Entity Link
   Engine*-Meta-Box zeigt bewertete Kandidaten über **Links vorschlagen**.
4. **Übernehmen oder zurücknehmen** — **Links einfügen** wendet sie an;
   **Letzten Lauf rückgängig machen** stellt den exakten vorherigen Inhalt her.
5. **Automatisch laufen lassen** — beim Veröffentlichen läuft das Plugin
   automatisch (Standard an; in den Einstellungen abschaltbar).

---

## 4. Einstellungen (Referenz)

*Entity Links → Einstellungen.*

| Einstellung | Standard | Beschreibung |
|---|---|---|
| Beitragstypen | `post` | Inhaltstypen, die gescannt und verlinkt werden. |
| Max. Links pro Beitrag | 3 | Obergrenze je Lauf. Pro-Beitrag-Override: `_elink_max_links`. |
| Min. Score | 2,5 | Kandidaten unter diesem Score werden nicht verlinkt. |
| Automatischer Lauf beim Veröffentlichen | an | Motor beim Veröffentlichen eines Beitrags ausführen. |
| CSS-Klasse hinzufügen | an | Fügt `elink-auto-link` hinzu (Klassenname konfigurierbar). |
| Blöcke überspringen | h1–h6, pre, code, table, blockquote, figure, img | Blocktypen, die nie Auto-Links erhalten. |
| Semantische Ebene | aus | Optionale OpenAI-kompatible Embeddings (siehe §8). |

---

## 5. Entitäts-Vokabular

*Entity Links → Entitäts-Vokabular.*

Jede manuelle Entität bildet eine Wendung (plus Aliase) auf genau einen
Zielbeitrag ab:

- **Entitätsname** — die primäre Wendung (z. B. `GEO-Audit`).
- **Aliase** — kommagetrennte Alternativen (`GEO Audit`).
- **Zielbeitrag** — das Ziel.
- **Priorität** — höhere Werte gewinnen bei Gleichstand; manuelle Entitäten
  übertrumpfen automatisch extrahierte immer.

Aliase werden mit Wortgrenzen abgeglichen, längste zuerst. Ein Ziel wird pro
Beitrag höchstens einmal verlinkt.

---

## 6. Editor-Workflow (Meta-Box)

Die *Entity Link Engine*-Meta-Box erscheint bei allen aktivierten Beitragstypen.

- **Links vorschlagen** — Trockenlauf: zeigt Kandidaten mit Scores und Gründen,
  ändert nichts.
- **Links einfügen** — wendet den Lauf an und legt einen Snapshot ab.
- **Letzten Lauf rückgängig machen** — stellt den exakten Inhalt vor dem Lauf her.

Die Meta-Box zeigt außerdem das Ergebnis des letzten Laufs und ob die
Autoverlinkung für diesen Beitrag deaktiviert ist.

---

## 7. Stapellauf

*Entity Links → Stapellauf.*

- **Entitäts-Index neu aufbauen** — extrahiert Entitäten aus allen Beiträgen neu.
- **Stapellauf starten** — verarbeitet alle Beiträge in WP-Cron-Batches (5 pro
  Tick) und hält pro Beitrag einen Snapshot, sodass jeder Lauf im Editor
  rückgängig gemacht werden kann.

---

## 8. Semantische Ebene (optional)

Standardmäßig arbeitet das Plugin **vollständig lokal** — keine Daten verlassen
Ihre Website.

Aktivieren Sie die semantische Ebene, sendet das Plugin kurze Textauszüge
(Titel, Überschriften, Teaser) an einen von Ihnen konfigurierten
**OpenAI-kompatiblen Embeddings-Endpunkt** und mischt die zurückgegebene
Ähnlichkeit in den Score ein:

```
End-Score = lexikalischer Score + Mischgewicht × 5 × mittlere Kosinus-Ähnlichkeit
```

- **API-URL** — Basis-URL, vom Nutzer konfiguriert (kein Standard; das Feld deutet `https://api.openai.com/v1` als Beispiel an).
- **API-Schlüssel** — Ihr Schlüssel.
- **Modell** — z. B. `text-embedding-3-small`.
- **Mischgewicht** — 0–1 (Standard 0,4).

Embeddings werden im Beitrags-Meta gecacht; bei Fehlern degradiert das Plugin
sauber zum lokalen lexikalischen Scoring. Dies ist ein externer Service-Aufruf
und wird hier sowie im readme offengelegt.

---

## 9. Pro-Beitrag-Overrides (Post-Meta)

| Meta-Schlüssel | Wirkung |
|---|---|
| `_elink_auto_links` | Auf `0` setzen, um die Autoverlinkung für diesen Beitrag zu deaktivieren. |
| `_elink_max_links` | Überschreibt das Maximum je Lauf für diesen Beitrag. |
| `_elink_objectives` | Kommagetrennte Liste; gemeinsame Ziele geben +3,0 je Treffer. |
| `_elink_use_tag` | Format-Label; gemeinsamer Wert gibt +0,5. |
| `_elink_lang` | Sprachmarker; wird auf Index-Zeilen für mehrsprachige Sites gespeichert. |

---

## 10. REST-API

Basis: `/wp-json/entity-link-engine/v1/` (erfordert Bearbeitungsrechte).

| Endpunkt | Methode | Body | Wirkung |
|---|---|---|---|
| `/suggest` | POST | `{ "post_id": 123 }` | Trockenlauf-Kandidaten (keine Änderung). |
| `/run` | POST | `{ "post_id": 123 }` | Lauf anwenden. |
| `/undo` | POST | `{ "post_id": 123 }` | Snapshot wiederherstellen. |
| `/rebuild` | POST | — | Entitäts-Index neu aufbauen (manage_options). |

Alle Endpunkte verlangen ein REST-Nonce (`X-WP-Nonce`) und prüfen die
Fähigkeiten `edit_post` bzw. `manage_options`.

---

## 11. Hooks und Filter

- **`elink_manual_entities`** — Filter, um Entitäten aus Code zu ergänzen:
  `array( 'entity_label' => string, 'aliases' => array, 'target_post_id' => int, 'priority' => int )`.
- **`elink_bulk_tick`** — Cron-Event, das den Stapellauf antreibt.

---

## 12. Datenmodell

**Tabellen** (Präfix `wp_elink_`):

- `elink_entity_index` — Entitäts-Schlüssel, Label, Beitrags-ID, Quelle, Gewicht, Sprache.
- `elink_links` — Quell-ID, Ziel-ID, Anker, Score, Modus, Status, Zeitstempel.

**Optionen:** `elink_settings`, `elink_entities_manual`, `elink_index_built`,
`elink_bulk_*`.

**Post-Meta:** `_elink_snapshot`, `_elink_inserted_links`, `_elink_last_run`,
`_elink_embedding`, `_elink_auto_links`, `_elink_max_links`, `_elink_objectives`,
`_elink_use_tag`, `_elink_lang`.

Die Deinstallation entfernt alle Optionen, Tabellen und `_elink_*`-Post-Meta.

---

## 13. Häufige Fragen

**Ruft das Plugin einen externen KI-Dienst auf?**
Nur wenn Sie die semantische Ebene ausdrücklich aktivieren und API-URL und
-Schlüssel hinterlegen. Ohne das läuft alles lokal.

**Verändert es meine bestehenden Links?**
Nein. Bestehende Links werden erkannt und erhalten; das Plugin ergänzt nur URLs,
die im Beitrag noch nicht vorhanden sind.

**Kann es in Überschriften oder Code verlinken?**
Nein. Überschriften, Code, pre, Tabellen, Zitate, Abbildungen und Bilder werden
übersprungen (konfigurierbar).

**Warum bekommt mein Beitrag keine drei Links?**
Ein Beitrag erhält nur Links, wenn Kandidaten über dem Mindest-Score liegen
*und* ihre Anker-Wendung tatsächlich in einem verlinkbaren Absatz vorkommt.
Generische Wörter werden bewusst nicht verlinkt.

**Wie mache ich einen Lauf rückgängig?**
Die Editor-Meta-Box bietet *Letzten Lauf rückgängig machen*; sie stellt den
exakten Inhalt vor dem Lauf aus dem gespeicherten Snapshot her.

**Funktioniert es mit Gutenberg und dem klassischen Editor?**
Ja, einschließlich kommentar-getrennter Blöcke und klassischem Einzel-Zeilen-Markup.

---

## 14. Fehlerbehebung

- **Keine Kandidaten sichtbar** — der Entitäts-Index ist vermutlich leer. Bauen
  Sie ihn neu auf (*Stapellauf → Entitäts-Index neu aufbauen*). Manuelle
  Entitäten brauchen einen existierenden Zielbeitrag (Auflösung per Slug).
- **Anker sind schwach/generisch** — legen Sie manuelle Entitäten für Ihre
  wichtigsten Seiten an; automatisch extrahierte Einzelwörter sind ein Fallback,
  nicht das primäre Signal.
- **Semantische Ebene trägt nichts bei** — API-URL/-Schlüssel prüfen und ob der
  Endpunkt erreichbar ist; bei Fehlern fällt das Plugin still auf das lexikalische
  Scoring zurück.
- **Links landen an der falschen Stelle** — prüfen Sie *Blöcke überspringen* in
  den Einstellungen.

---

## 15. Datenschutz und Datenverarbeitung

- Keine Daten verlassen die Website, solange die optionale semantische Ebene
  deaktiviert ist.
- Bei aktivierter semantischer Ebene werden kurze Textauszüge (Titel,
  Überschriften, Teaser) an den konfigurierten Embeddings-Endpunkt gesendet; die
  Antworten dienen nur der Kandidatenbewertung und werden lokal gecacht.
- Es werden keine personenbezogenen Daten erhoben. Die Deinstallation entfernt
  alle Plugin-Daten.

---

## 16. Lizenz und Autor

**Entity Link Engine** ist freie Software unter der
[GPLv2 oder neuer](https://www.gnu.org/licenses/gpl-2.0.html).

Autor: **Eugen Ullrich** — <https://eullrich.com>
