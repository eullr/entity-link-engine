#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Demo-Content Generator: Industrie-Klimageräte
Erzeugt demo-content.xml (WXR 1.2) + demo-entities.php + DEMO.md
Alle Zahlen in den Texten sind fiktive Demo-Daten, keine echten Messwerte.
"""

import html
import os
import re
from datetime import datetime, timedelta

OUT = os.path.dirname(os.path.abspath(__file__))

# ---------------------------------------------------------------------------
# Manuelles Entity-Vokabular (wird als elink_entities_manual importiert)
# (label, aliases, target_slug, priority)
# ---------------------------------------------------------------------------
ENTITIES = [
    ("Industrie-Klimageräte", ["Industrieklimageräte", "Industrie Klimageräte", "Industrieklimaanlagen"], "startseite", 100),
    ("Prozessklimatisierung", ["Prozesskühlung"], "prozessklimatisierung-produktionshallen", 100),
    ("Kaltwassersatz", ["Kaltwassersätze", "Kaltwassersaetze", "Chiller"], "produkte-kaltwassersaetze", 100),
    ("Schaltschrankklimatisierung", ["Schaltschrank-Kühlung"], "produkte-schaltschrankklimatisierung", 100),
    ("Serverraum-Klimatisierung", ["Serverraumklimatisierung", "Rechenzentrum-Klimatisierung"], "produkte-serverraum-klimatisierung", 100),
    ("Free Cooling", ["Free-Cooling"], "free-cooling-aussenluft", 95),
    ("F-Gas-Verordnung", ["F-Gas-VO", "F-Gas"], "f-gas-verordnung-2026", 95),
    ("Kältemittel R290", ["R290", "Propan als Kältemittel"], "kaeltemittel-r290", 95),
    ("Adiabatische Kühlung", ["adiabatische Kuehlung", "Adiabatik"], "adiabatische-kuehlung", 95),
    ("Kältelastberechnung", ["Kältebedarf berechnen"], "kaeltelastberechnung", 95),
    ("Dachklimageräte", ["Dach-Klimageräte"], "produkte-dachklimageraete", 95),
    ("Mobile Klimageräte", ["mobile Industrieklimageräte", "Miet-Klimageräte"], "produkte-mobile-klimageraete", 95),
    ("Wartungsvertrag", ["Klima-Wartungsvertrag", "Wartungsverträge"], "wartungsvertrag-klimaanlagen", 95),
    ("BAFA-Förderung", ["BAFA", "Bafa-Förderung", "BAFA-Zuschuss"], "bafa-foerderung-kaelteanlagen", 95),
    ("EN 378", ["EN378"], "en-378-sicherheit", 90),
    ("ATEX-Klimageräte", ["ATEX", "explosionsgeschützte Klimageräte"], "atex-klimageraete", 90),
    ("Wärmerückgewinnung", ["WRG", "Wärmerückgewinnung aus Kälte"], "waermerueckgewinnung-kaelte", 90),
    ("Kälteleistung", ["Kälteleistung berechnen"], "kaelteleistung-berechnen", 90),
    ("Notkühlung", ["Notkühlkonzept"], "notkuehlung-redundanz", 90),
    ("Container-Klimatisierung", ["Containerklimatisierung"], "container-klimatisierung-rechenzentren", 90),
    ("ErP-Richtlinie", ["ErP"], "erp-richtlinie-effizienzklassen", 90),
    ("CO2-Kältemittel R744", ["R744", "CO2-Kältemittel"], "co2-kaeltemittel-r744", 90),
    ("Hybridkühler", ["Hybridkühlung"], "hybridkuehler-trocken-verdunstung", 90),
    ("Abluft-Wärmepumpe", ["Abluft-Wärmepumpen"], "abluft-waermepumpen-hallen", 90),
    ("Reinraumklimatisierung", ["ISO 14644"], "reinraumklimatisierung-iso-14644", 90),
    ("Kältemittelleckage", ["Kältemittelleckagen", "Leckage"], "kaeltemittelleckagen-erkennen", 90),
    ("Glykol", ["Glykol-Kühlkreislauf"], "glykol-kuehlkreislaeufe", 85),
    ("Pufferspeicher", ["Kältepufferspeicher"], "pufferspeicher-kaelte", 85),
]

# ---------------------------------------------------------------------------
# Kategorien & Tags
# ---------------------------------------------------------------------------
CATS = {
    "planung": "Planung",
    "produkte": "Produkte",
    "kaeltemittel": "Kältemittel",
    "effizienz": "Effizienz",
    "wartung": "Wartung",
    "sicherheit": "Sicherheit",
    "branchen": "Branchen",
    "foerderung": "Förderung",
}
TAGS = ["kuehlung", "rechenzentrum", "logistik", "produktion", "foerderung",
        "saison", "smart", "lebensmittel", "pharma", "notfall"]

# ---------------------------------------------------------------------------
# Hilfsfunktionen
# ---------------------------------------------------------------------------
def art(slug, title, cat, tags, date, excerpt, intro, sections, max_links=None, auto_links=None):
    return {
        "slug": slug, "title": title, "cat": cat, "tags": tags, "date": date,
        "excerpt": excerpt, "intro": intro, "sections": sections,
        "max_links": max_links, "auto_links": auto_links,
    }

def sec(h2, *paras):
    return (h2, list(paras))

def p(text):
    return "<p>%s</p>" % text

def h2(text):
    return "<h2>%s</h2>" % text

def render_body(intro, sections):
    out = [p(intro)]
    for heading, paras in sections:
        out.append(h2(heading))
        out.extend(p(x) for x in paras)
    return "\n\n".join(out)

# ---------------------------------------------------------------------------
# 50 Blog-Artikel
# ---------------------------------------------------------------------------
POSTS = [
    art("industrie-klimageraete-leitfaden", "Industrie-Klimageräte: Der Leitfaden für Produktionshallen",
        "planung", ["kuehlung", "produktion"], "2026-02-10",
        "Worauf es bei Industrie-Klimageräten ankommt: Last, Technologie, Betriebskosten.",
        "Industrie-Klimageräte kühlen nicht Menschen, sondern Prozesse. Wer in einer Produktionshalle stabile Temperaturen braucht, entscheidet sich zwischen Dachgeräten, Kaltwassersätzen und dezentralen Lösungen. Dieser Leitfaden zeigt, worauf es bei der Auswahl wirklich ankommt.",
        [sec("Zuerst die Last, dann das Gerät",
             "Jede Planung beginnt mit der Kältelastberechnung. Ohne sie ist jedes Industrie-Klimagerät entweder zu groß, zu klein oder zur falschen Zeit am falschen Ort.",
             "Gemessen werden Maschinenabwärme, Beleuchtung, Personen und die Gebäudehülle. Bei Produktionshallen dominiert fast immer die Abwärme der Anlagen."),
         sec("Technologien im Überblick",
             "Dachklimageräte bringen die Kälte dorthin, wo sie entsteht, und sparen wertvolle Hallenfläche. Kaltwassersätze versorgen mehrere Verbraucher zentral und erlauben später eine Erweiterung.",
             "Für Einzelmaschinen lohnt sich oft die Schaltschrankklimatisierung statt einer kompletten Hallenklimatisierung."),
         sec("Betriebskosten mitdenken",
             "Der Kaufpreis ist nur ein Teil der Wahrheit. EER und SEER entscheiden über die Stromrechnung der nächsten fünfzehn Jahre.",
             "Wer heute plant, sollte die F-Gas-Verordnung und die ErP-Richtlinie bereits in die Technologieentscheidung einbeziehen.")]),
    art("prozessklimatisierung-produktionshallen", "Prozessklimatisierung: Klima, das der Produktion folgt",
        "planung", ["kuehlung", "produktion"], "2026-01-22",
        "Prozessklimatisierung hält Temperatur und Feuchte stabil – nicht den Menschen, sondern das Produkt.",
        "Prozessklimatisierung bedeutet: Die Luft muss genau das können, was der Prozess verlangt. Eine Schweißnaht, eine Beschichtung oder eine Verpackungslinie reagiert empfindlich auf Temperaturschwankungen. Deshalb wird das Klima hier zum Qualitätsfaktor.",
        [sec("Temperaturfenster definieren",
             "Der erste Schritt ist ein Lastenheft mit Ober- und Untergrenzen. Toleranzen von plus minus zwei Grad sind in der Prozessklimatisierung oft schon eng.",
             "Liegt das Fenster enger, steigen die Kosten überproportional. Deshalb gilt: So eng wie nötig, so weit wie möglich."),
         sec("Dezentral oder zentral",
             "Dezentrale Geräte reagieren schnell auf lokale Lastspitzen. Ein zentraler Kaltwassersatz glättet dagegen die Gesamtlast und läuft effizienter.",
             "Viele Hallen fahren eine Mischung: zentrale Kälte für die Grundlast, dezentrale Spitzenabdeckung."),
         sec("Luftfeuchte nicht vergessen",
             "Bei Trocknungs- oder Beschichtungsprozessen ist die relative Feuchte oft wichtiger als die Temperatur. Dann gehört die Befeuchtung in die Prozessklimatisierung hinein.")]),
    art("kaltwassersatz-dimensionierung", "Kaltwassersatz richtig dimensionieren",
        "planung", ["kuehlung"], "2026-03-04",
        "Fünf Fehler, die bei der Dimensionierung von Kaltwassersätzen teuer werden.",
        "Ein Kaltwassersatz ist kein Regalartikel. Zu klein dimensioniert, schafft er die Spitzenlast nicht; zu groß, taktet er sich zu Tode und verschwendet Strom. Die Dimensionierung entscheidet über Effizienz und Lebensdauer.",
        [sec("Die Spitzenlast ist der Maßstab",
             "Grundlast und Spitzenlast sind zwei verschiedene Welten. Ein Kaltwassersatz, der nur die mittlere Last deckt, kollabiert im Hochsommer genau dann, wenn er gebraucht wird.",
             "Die Kältelastberechnung liefert die Zahlen. Darauf aufbauend wird die Maschinenkombination geplant."),
         sec("Teillastverhalten prüfen",
             "Ein Kaltwassersatz läuft selten bei Volllast. Entscheidend ist der Teillastwirkungsgrad, den moderne Geräte über Drehzahlregelung erreichen.",
             "Vergleichen Sie SEER-Werte, nicht nur die Nennleistung im Datenblatt."),
         sec("Pufferspeicher einplanen",
             "Ein Pufferspeicher entkoppelt Erzeugung und Verbrauch. Damit kann der Kaltwassersatz länger in effizienten Lastbereichen laufen statt ständig zu takten.")]),
    art("serverraum-free-cooling", "Serverraum-Klimatisierung mit Free Cooling",
        "produkte", ["rechenzentrum", "kuehlung"], "2026-01-18",
        "Free Cooling nutzt Außenluft und spart bis zu 70 Prozent Kältestrom.",
        "Serverräume produzieren das ganze Jahr Abwärme – auch im Winter. Genau da setzt Free Cooling an: Sobald die Außenluft kalt genug ist, übernimmt sie die Kühlung und der Kaltwassersatz geht in den Standby. Das ist die wirtschaftlichste Form der Serverraum-Klimatisierung.",
        [sec("Wann lohnt sich Free Cooling",
             "In Deutschland liegen die Temperaturen die meiste Zeit des Jahres unter der Rücklauftemperatur des Kühlkreislaufs. Deshalb rechnet sich Free Cooling hier fast immer.",
             "Je höher die Vorlauftemperatur der Server (warm water cooling), desto mehr Stunden im Jahr kann frei gekühlt werden."),
         sec("Direkt oder indirekt",
             "Direkte Free-Cooling-Systeme führen Außenluft direkt in den Raum – günstig, aber luftfeuchteabhängig. Indirekte Systeme trennen die Luftströme über einen Wärmetauscher und sind robuster.",
             "Für Serverräume mit empfindlicher Elektronik ist die indirekte Variante der sichere Standard."),
         sec("Redundanz nicht vergessen",
             "Free Cooling ist ein Effizienzbaustein, kein Redundanzkonzept. Die Notkühlung muss auch bei Außentemperaturspitzen und Geräteausfall liefern.")]),
    art("schaltschrankklimatisierung-warum", "Schaltschrankklimatisierung: Warum Steuerungen kühl bleiben müssen",
        "produkte", ["produktion", "kuehlung"], "2026-02-02",
        "Jede zehnte Grad über 35 Grad halbiert die Lebensdauer von Elektronik – Schaltschrankklimatisierung schützt die Steuerung.",
        "In jedem Schaltschrank steckt Elektronik, die Wärme produziert: Frequenzumrichter, SPS, Servoverstärker. Ohne Schaltschrankklimatisierung steigt die Temperatur im Schrank auf Werte, die Ausfälle und Stillstände provozieren. Der Zusammenhang ist einfach: Wärme verkürzt die Lebensdauer von Bauteilen drastisch.",
        [sec("Die 10-Grad-Regel",
             "Als Faustregel gilt: Jede Temperaturerhöhung um zehn Kelvin halbiert die Lebensdauer elektronischer Bauteile. Bei 50 Grad Innentemperatur arbeitet die Steuerung also mit einem Bruchteil ihrer erwarteten Lebensdauer.",
             "Deshalb ist ein Schaltschrankklimagerät keine Komfortfrage, sondern eine Verfügbarkeitsfrage."),
         sec("Gerätetypen richtig wählen",
             "Filterlüfter reichen nur bei geringer Verlustleistung. Sobald die Abwärme steigt, sind Kompaktklimageräte oder Kältesysteme mit Wärmetauscher gefragt.",
             "Wichtig ist die Auslegung auf die tatsächliche Verlustleistung des Schranks, nicht auf die Schrankgröße."),
         sec("Wartung einplanen",
             "Verstopfte Filter und verschmutzte Wärmetauscher senken die Kälteleistung schleichend. Die Schaltschrankklimatisierung gehört in den Wartungsvertrag.")]),
    art("f-gas-verordnung-2026", "F-Gas-Verordnung 2026: Was Betreiber jetzt wissen müssen",
        "kaeltemittel", ["kuehlung", "foerderung"], "2026-04-12",
        "Quoten, Verbote, Übergangsfristen: Die F-Gas-Verordnung verändert den Bestand an Klimaanlagen.",
        "Die F-Gas-Verordnung begrenzt schrittweise fluorierte Kältemittel. Für Betreiber von Industrie-Klimageräten bedeutet das: Bestandsanlagen mit hohem Treibhauspotenzial werden teurer, und neue Anlagen müssen frühzeitig auf klimafreundliche Kältemittel umgestellt werden.",
        [sec("Die neuen Stufen",
             "Die Quoten für die Inverkehrbringung fluorierter Kältemittel sinken in mehreren Stufen. Das verteuert Wartung und Nachfüllung von Bestandsanlagen spürbar.",
             "Gleichzeitig gelten Verbote für neue Anlagen mit bestimmten Kältemitteln in immer mehr Anwendungen."),
         sec("Was Betreiber jetzt tun sollten",
             "Bestandsanalyse: Welche Kältemittel stecken in meinen Anlagen, und wie hoch ist das Treibhauspotenzial?",
             "Dann folgt eine Umstellungsplanung: Anlagen, die in den nächsten Jahren ersetzt werden, sollten heute schon auf R290, R744 oder andere natürliche Kältemittel ausgelegt werden."),
         sec("Förderung nutzen",
             "Die Umstellung auf klimafreundliche Kältetechnik wird über die BAFA-Förderung unterstützt. Der Antrag sollte vor der Angebotsphase gestellt werden.")]),
    art("kaeltemittel-r290", "Kältemittel R290: Propan in Industrieklimageräten",
        "kaeltemittel", ["kuehlung", "sicherheit"], "2026-03-20",
        "R290 ist effizient und klimafreundlich – verlangt aber mehr Sorgfalt bei Sicherheit und Aufstellung.",
        "Kältemittel R290 ist Propan: ein natürliches Kältemittel mit minimalem Treibhauspotenzial und hervorragenden thermodynamischen Eigenschaften. Für Industrie-Klimageräte wird es zur ersten Wahl, wenn die F-Gas-Verordnung die Alternativen verteuert.",
        [sec("Warum R290 gewinnt",
             "R290 hat ein GWP von 3 und gehört damit nicht unter die Quote der F-Gas-Verordnung. Gleichzeitig sind die Wirkungsgrade in vielen Anwendungen besser als bei Fluorkohlenwasserstoffen.",
             "Die Kältemittelfüllmenge ist allerdings begrenzt, weil Propan brennbar ist."),
         sec("Sicherheit nach EN 378",
             "Die EN 378 legt fest, wie viel R290 in welchem Raum eingesetzt werden darf. Lüftung, Leckage-Erkennung und Aufstellort werden Teil der Planung.",
             "In Produktionshallen mit hohen Räumen sind die zulässigen Füllmengen meist unkritisch. In engen Technikräumen muss die Lüftung nachgerüstet werden."),
         sec("Wartung braucht Qualifikation",
             "Arbeiten an R290-Anlagen verlangen eine spezielle Zertifizierung. Der Wartungsvertrag sollte nachweisen, dass der Dienstleister dafür qualifiziert ist.")]),
    art("adiabatische-kuehlung", "Adiabatische Kühlung: Weniger Strom, weniger Wasser",
        "effizienz", ["kuehlung", "produktion"], "2026-05-06",
        "Adiabatische Kühlung nutzt Verdunstung und senkt den Strombedarf von Kälteanlagen deutlich.",
        "Adiabatische Kühlung funktioniert nach einem einfachen Prinzip: Wasser verdunstet, entzieht der Luft Wärme und kühlt sie ab. In der Industriekälte wird dieser Effekt genutzt, um die Kondensationstemperatur zu senken und damit Strom zu sparen.",
        [sec("Wo die Einsparung herkommt",
             "Je niedriger die Kondensationstemperatur, desto effizienter arbeitet der Kältekreislauf. Adiabatische Vorkühlung der Ansaugluft senkt diese Temperatur gezielt an heißen Tagen.",
             "In Spitzenlastzeiten können dadurch 20 bis 30 Prozent Kältestrom gespart werden."),
         sec("Wasserbedarf realistisch planen",
             "Adiabatische Kühlung braucht Wasser. Die Aufbereitung und der Verbrauch müssen in die Betriebskostenrechnung einfließen.",
             "Moderne Systeme regeln die Befeuchtung lastabhängig und minimieren so den Wasserbedarf."),
         sec("Kombination mit Hybridkühlern",
             "Hybridkühler fahren im Winter trocken, im Sommer adiabatisch. Diese Kombination deckt das ganze Jahr mit einem Gerät ab.")]),
    art("kaeltelastberechnung", "Kältelastberechnung Schritt für Schritt",
        "planung", ["kuehlung"], "2026-02-26",
        "Die Kältelastberechnung ist das Fundament jeder Klimaplanung – Schritt für Schritt erklärt.",
        "Die Kältelastberechnung beantwortet eine einzige Frage: Wie viel Kälteleistung braucht dieser Raum wirklich? Alle Folgeentscheidungen – Gerätegröße, Kanalnetz, Betriebskosten – hängen an dieser Zahl.",
        [sec("Schritt 1: Lasten sammeln",
             "Maschinenabwärme aus Datenblättern, Beleuchtung, Personen, Sonneneinstrahlung, Luftwechsel. Jede Lastquelle wird mit ihrer maximalen Leistung angesetzt.",
             "Bei Industriehallen dominiert fast immer die Maschinenabwärme."),
         sec("Schritt 2: Gleichzeitigkeit bewerten",
             "Nicht alle Lasten treten gleichzeitig auf. Der Gleichzeitigkeitsfaktor verhindert, dass die Anlage für ein Szenario ausgelegt wird, das nie eintritt.",
             "Das Ergebnis ist die maßgebliche Kühllast, auf die das Gerät dimensioniert wird."),
         sec("Schritt 3: Sicherheitsreserve festlegen",
             "Eine Reserve von 10 bis 15 Prozent deckt Planungsunsicherheit ab – mehr ist Verschwendung, weniger ist Risiko.")]),
    art("dachklimageraete-logistik", "Dachklimageräte für Logistikzentren",
        "branchen", ["logistik", "kuehlung"], "2026-04-03",
        "Dachklimageräte kühlen große Hallen, ohne wertvolle Fläche zu belegen.",
        "Logistikzentren haben ein besonderes Problem: Die Halle ist voll mit Regalen, Fördertechnik und Ware – für Klimageräte bleibt kein Platz. Dachklimageräte lösen das, indem sie die gesamte Technik auf dem Dach tragen und nur die Luftverteilung in die Halle führen.",
        [sec("Warum Dachgeräte in der Logistik dominieren",
             "Sie belegen keine Hallenfläche, verteilen die Kälte gleichmäßig und lassen sich modular erweitern, wenn die Halle wächst.",
             "Für Hochregallager gilt: Die Kältelastberechnung muss die Höhe der Regale und die Luftschichtung berücksichtigen."),
         sec("Anbindung und Verteilung",
             "Die Luftverteilung über textile Schläuche oder Kanäle sorgt für eine gleichmäßige Temperatur ohne Zugluft an den Arbeitsplätzen.",
             "Moderne Dachgeräte arbeiten mit Wärmerückgewinnung und adiabatischer Zusatzkühlung."),
         sec("Wartungszugang planen",
             "Dachgeräte sind wartungsintensiver, weil sie der Witterung ausgesetzt sind. Der Wartungsvertrag sollte Dacharbeiten explizit abdecken.")]),
    art("wartungsvertrag-klimaanlagen", "Wartungsvertrag für Klimaanlagen: Kosten und Leistungen",
        "wartung", ["kuehlung", "saison"], "2026-03-14",
        "Was ein Wartungsvertrag für Industrie-Klimageräte kosten darf und was er leisten muss.",
        "Ein Wartungsvertrag für Klimaanlagen ist kein Nice-to-have, sondern die Versicherung gegen den teuersten Ausfall: den im Hochsommer. Wer wartet, verhindert Stillstände, hält die Effizienz und erfüllt zugleich die dokumentationspflichten der F-Gas-Verordnung.",
        [sec("Leistungen, die drin sein müssen",
             "Mindestens zwei Wartungsdurchgänge pro Jahr, Dichtigkeitsprüfung nach F-Gas-Verordnung, Filterwechsel, Reinigung der Wärmetauscher und ein Protokoll je Durchgang.",
             "Optional kommen Fernwartung und eine garantierte Reaktionszeit im Notfall dazu."),
         sec("Kosten einordnen",
             "Die Kosten hängen von Gerätezahl, Leistungsklasse und Standort ab. Als Faustwert: ein bis zwei Prozent des Anlagenwertes pro Jahr sind ein realistischer Rahmen.",
             "Ein Vertrag, der deutlich darunter liegt, enthält meist versteckte Ausschlüsse."),
         sec("Reaktionszeiten festlegen",
             "Für Produktionshallen ist die garantierte Reaktionszeit wichtiger als der Preis. Jede Stunde Stillstand kostet mehr als ein Jahr Wartung.")]),
    art("miete-oder-kauf", "Miete oder Kauf von Industrie-Klimageräten",
        "planung", ["kuehlung", "foerderung"], "2026-01-30",
        "Miete oder Kauf: Die Entscheidung hängt von Laufzeit, Liquidität und Flexibilität ab.",
        "Industrie-Klimageräte sind Kapitalgüter mit langer Lebensdauer – aber nicht jede Situation rechtfertigt einen Kauf. Miete oder Kauf ist eine Finanzierungsfrage mit technischen Nebenwirkungen.",
        [sec("Kauf: klare Kosten, klare Verantwortung",
             "Der Kauf rechnet sich ab einer Nutzungsdauer von mehreren Jahren. Dafür trägt der Betreiber das volle Wartungs- und Ersatzrisiko.",
             "Geförderte Maßnahmen nach der BAFA-Förderung setzen in der Regel einen Kauf voraus."),
         sec("Miete: Flexibilität gegen Aufschlag",
             "Miete lohnt sich bei befristeten Projekten, Standorterweiterungen oder als Mobile Klimageräte-Lösung während einer Sanierung.",
             "Der Mietpreis enthält Wartung und oft auch den Notdienst. Wer das auslagern will, mietet."),
         sec("Entscheidungsmatrix",
             "Nutzungsdauer unter zwei Jahren: mieten. Über fünf Jahre: kaufen. Dazwischen: Amortisationsrechnung mit Wartungskosten und Restwert.")]),
    art("mobile-klimageraete-notkuehlung", "Mobile Klimageräte: Notkühlung, wenn es schnell gehen muss",
        "produkte", ["kuehlung", "notfall"], "2026-05-22",
        "Mobile Klimageräte überbrücken Ausfälle, Bauphasen und Lastspitzen – ohne Umbau.",
        "Wenn die Kälteanlage ausfällt oder eine Halle kurzfristig genutzt werden soll, zählt Geschwindigkeit. Mobile Klimageräte liefern innerhalb weniger Stunden Kälteleistung, ohne Installation und ohne Umbau. Sie sind das Notkühlkonzept für alle Fälle.",
        [sec("Einsatzszenarien",
             "Notkühlung nach Ausfall der Bestandsanlage, Überbrückung während der Sanierung, Zusatzkühlung für Events oder Lastspitzen im Sommer.",
             "Auch für die Serverraum-Klimatisierung gibt es mobile Lösungen mit Redundanzanschluss."),
         sec("Was mobile Geräte leisten",
             "Leistungen von wenigen Kilowatt bis zu mehreren hundert Kilowatt über Containerlösungen. Der Anschluss braucht Strom und einen Abluftweg.",
             "Die Aufstellung im Freien mit Kondensatorlüftung ist meist die schnellste Variante."),
         sec("Miete statt Kauf",
             "Mobile Geräte sind ideal für die Miete: befristet, inklusive Wartung, ohne Kapitalbindung. Der Wartungsvertrag entfällt, der Service ist im Mietpreis.")]),
    art("free-cooling-aussenluft", "Free Cooling: Außenluft als kostenloses Kältemittel",
        "effizienz", ["rechenzentrum", "kuehlung"], "2026-01-12",
        "Free Cooling nutzt die Außenluft als Kältequelle – und spart Strom, solange es draußen kalt ist.",
        "Free Cooling ist die eleganteste Form der Energieeffizienz: Man nutzt die Kälte, die die Natur schon liefert. Sobald die Außentemperatur unter die Rücklauftemperatur des Kältekreislaufs fällt, übernimmt die Außenluft die Kühlung und der Verdichter kann pausieren.",
        [sec("Die Wirtschaftlichkeit",
             "Die Einsparung hängt von den Betriebsstunden ab, in denen frei gekühlt werden kann. Je höher die Systemtemperaturen, desto länger das Fenster.",
             "Mit warmer Wasserkühlung (Vorlauf 18 Grad) sind in Mitteleuropa über 80 Prozent der Jahresstunden abgedeckt."),
         sec("Technische Varianten",
             "Direkte Free-Cooling-Systeme führen Außenluft in den Raum, indirekte trennen die Luftströme. Zusätzlich gibt es Kaltwassersätze mit integriertem Freikühlregister.",
             "Für Serverraum-Klimatisierung ist die indirekte Variante der Standard."),
         sec("Grenzen einplanen",
             "Free Cooling ist keine Notkühlung. An heißen Tagen und bei Ausfällen muss die mechanische Kälte die Last übernehmen – das gehört in das Redundanzkonzept.")]),
    art("waermerueckgewinnung-kaelte", "Wärmerückgewinnung aus Kälteanlagen",
        "effizienz", ["kuehlung", "produktion"], "2026-04-18",
        "Abwärme aus Kälteanlagen heizt Hallen, Wasser und Prozesse – doppelt genutzte Energie.",
        "Eine Kälteanlage produziert nicht nur Kälte, sondern auch Wärme. Die Wärmerückgewinnung aus Kälteanlagen nutzt diese Abwärme, statt sie ungenutzt an die Umwelt abzugeben. Damit wird aus einem Stromverbraucher eine doppelt genutzte Energiequelle.",
        [sec("Wo die Wärme herkommt",
             "Am Kondensator einer Kälteanlage fallen je nach Betriebspunkt 20 bis 40 Prozent mehr Wärme an als Kälteleistung erzeugt wird.",
             "Diese Wärme hat in Produktionshallen viele Abnehmer: Raumheizung, Warmwasser, Vorwärmung von Prozessen."),
         sec("Technische Anbindung",
             "Wärmetauscher im Kältemittelkreis oder im Kühlwasserkreis geben die Wärme an das Heizsystem ab. Wichtig ist die Abstimmung der Temperaturniveaus.",
             "Abluft-Wärmepumpen und Hybridkühler ergänzen die Rückgewinnung in Hallen."),
         sec("Wirtschaftlichkeit",
             "Die Amortisationsrechnung verbucht die eingesparte Heizenergie gegen die Mehrkosten der Wärmetauscher. Bei dauerhaftem Wärmebedarf rechnet sich die Wärmerückgewinnung fast immer.")]),
    art("reinraumklimatisierung-iso-14644", "Reinraumklimatisierung nach ISO 14644",
        "branchen", ["produktion", "pharma"], "2026-02-14",
        "Reinraumklimatisierung kontrolliert Partikel, Temperatur und Feuchte – nach ISO 14644.",
        "In Reinräumen entscheidet die Luftqualität über die Produktqualität. Die Reinraumklimatisierung nach ISO 14644 hält Partikelkonzentration, Temperatur und relative Feuchte in engen Grenzen – für Pharma, Elektronik und Medizintechnik.",
        [sec("Klassen verstehen",
             "ISO 14644 definiert Reinheitsklassen über die zulässige Partikelzahl je Kubikmeter. Je strenger die Klasse, desto höher der Luftwechsel und der Filteraufwand.",
             "Die Klimatisierung muss den Luftstrom so führen, dass Partikel aus dem Produktbereich abtransportiert werden."),
         sec("Temperatur und Feuchte",
             "Neben der Partikelreinheit sind Temperatur und Feuchte prozesskritisch. Schwankungen verändern Materialeigenschaften und Messergebnisse.",
             "Die Prozessklimatisierung liefert dafür die präzise Regelung."),
         sec("Druckverhältnisse",
             "Reinräume werden über Druckstufen gegen Verunreinigung geschützt. Die Lüftungsgeräte müssen diesen Differenzdruck stabil halten.")]),
    art("spritzguss-kuehlung-kunststoff", "Spritzguss kühlen: Kältetechnik für die Kunststoffindustrie",
        "branchen", ["produktion", "kuehlung"], "2026-03-28",
        "Kühlung entscheidet über Taktzeit und Qualität beim Spritzguss.",
        "Beim Spritzguss bestimmt die Kühlung die Taktzeit: Je schneller das Werkzeug die Wärme abführt, desto schneller kann die nächste Spritze starten. Gleichzeitig beeinflusst die Kühltemperatur die Maßhaltigkeit der Teile. Kältetechnik ist hier Produktionsfaktor Nummer eins.",
        [sec("Kühlwassertemperatur als Stellgröße",
             "Die Werkzeugtemperatur wird über das Kühlwasser geführt. Ein Kaltwassersatz mit präziser Regelung hält sie stabil, auch wenn mehrere Maschinen parallel laufen.",
             "Unterschiedliche Werkzeuge brauchen unterschiedliche Temperaturen – das spricht für getrennte Kühlkreise."),
         sec("Lastspitzen puffern",
             "Beim Maschinenwechsel entstehen Kältelastspitzen. Ein Pufferspeicher glättet sie und verhindert, dass der Kaltwassersatz ständig hoch- und runterfährt.",
             "Die Kältelastberechnung muss die gleichzeitige Nutzung aller Werkzeuge abbilden."),
         sec("Wartung der Kühlkreise",
             "Verschmutzte Wärmetauscher und falsche Glykoldosierung senken die Kälteleistung. Die Kühlkreisläufe gehören in den Wartungsvertrag.")]),
    art("lebensmittelproduktion-kuehlen", "Lebensmittelproduktion: Kühlen ohne Stillstand",
        "branchen", ["lebensmittel", "kuehlung"], "2026-04-25",
        "In der Lebensmittelproduktion ist Kälte Hygiene, Qualität und Stillstandsrisiko zugleich.",
        "Lebensmittelproduktion stellt höchste Ansprüche an die Kältetechnik: Temperaturen im HACCP-Bereich, kurze Reaktionszeiten und null Toleranz für Ausfälle. Ein Kälteausfall bedeutet in der Regel Wareneinsatz in Millionenhöhe.",
        [sec("Kritische Temperaturbereiche",
             "Jeder Produktionsschritt hat sein eigenes Temperaturfenster. Die Klimatisierung der Hallen und die Kühlung der Prozesse müssen getrennt geregelt werden.",
             "Prozessklimatisierung und Lagerkühlung dürfen nicht an derselben Anlage hängen."),
         sec("Redundanz ist Pflicht",
             "In der Lebensmittelindustrie gehört die Notkühlung zur Grundausstattung. Zwei unabhängige Kältepfade verhindern den Totalausfall.",
             "Der Wartungsvertrag sollte eine garantierte Reaktionszeit von wenigen Stunden enthalten."),
         sec("Reinigung und Wartung",
             "Kältemittelleckagen sind in Lebensmittelbetrieben doppelt kritisch: Sie gefährden die Anlage und das Produkt. Dichtigkeitsprüfungen gehören in den festen Rhythmus.")]),
    art("bestandsanlage-nachruesten", "Bestandsanlage nachrüsten statt neu kaufen",
        "effizienz", ["kuehlung", "foerderung"], "2026-05-15",
        "Nachrüsten ist oft günstiger als Erneuern – wenn die Anlage es hergibt.",
        "Nicht jede alte Kälteanlage muss ersetzt werden. Häufig lassen sich mit überschaubarem Aufwand große Effizienzsprünge erzielen: Drehzahlregelung, bessere Regelung, Wärmerückgewinnung oder Free-Cooling-Nachrüstung. Die Bestandsanlage wird so zur modernen Anlage.",
        [sec("Die Potenzialanalyse",
             "Zuerst wird der Ist-Zustand gemessen: Teillastverhalten, Kältemittel, Verschaltung. Daraus entsteht ein Maßnahmenpaket mit Amortisationsrechnung.",
             "Die F-Gas-Verordnung gibt dabei den Zeithorizont vor: Anlagen, die bald ohnehin umgestellt werden müssen, gehören eher ersetzt als nachgerüstet."),
         sec("Typische Nachrüstmaßnahmen",
             "Drehzahlgeregelte Ventilatoren und Verdichter, elektronische Expansionsventile, Pufferspeicher, Freikühlregister und Wärmetauscher für die Wärmerückgewinnung.",
             "Jede Maßnahme wird einzeln bewertet, nicht als Paket verkauft."),
         sec("Förderung mitnehmen",
             "Auch Nachrüstmaßnahmen sind über die BAFA-Förderung förderfähig. Der Antrag lohnt sich, weil er die Amortisation verkürzt.")]),
    art("erp-richtlinie-effizienzklassen", "ErP-Richtlinie: Effizienzklassen richtig lesen",
        "effizienz", ["kuehlung"], "2026-02-20",
        "Die ErP-Richtlinie regelt, wie effizient Klimageräte sein dürfen – und was die Klassen bedeuten.",
        "Die ErP-Richtlinie setzt Mindesteffizienzstandards für Klimageräte und verpflichtet Hersteller zu Effizienzklassen. Wer Industrie-Klimageräte beschafft, muss diese Klassen lesen können, sonst kauft er teure Betriebskosten.",
        [sec("Was die Klassen aussagen",
             "Die Klasse sagt, wie effizient das Gerät unter Normbedingungen arbeitet – nicht, wie effizient es in Ihrer Halle sein wird.",
             "Die realen Betriebskosten hängen von Teillastverhalten, Einbau und Regelung ab."),
         sec("SEER statt Nennleistung",
             "Der SEER-Wert (Seasonal Energy Efficiency Ratio) bildet das Jahresverhalten ab und ist aussagekräftiger als der Nenn-EER.",
             "Für Kaltwassersätze gelten eigene Normen und Kennzahlen."),
         sec("ErP und Förderung",
             "Viele Förderprogramme verlangen Effizienzklassen über dem Mindeststandard. Die ErP-Einstufung ist damit auch ein Förderkriterium.")]),
    art("co2-kaeltemittel-r744", "CO2-Kältemittel R744 in der Industrie",
        "kaeltemittel", ["kuehlung", "sicherheit"], "2026-05-30",
        "R744 ist das natürliche Kältemittel mit den härtesten Randbedingungen – und großem Potenzial.",
        "CO2-Kältemittel R744 ist ungiftig, nicht brennbar und hat ein GWP von 1. Es arbeitet aber bei deutlich höheren Drücken als klassische Kältemittel. Für Industriekälte und Wärmepumpen wird R744 trotzdem immer attraktiver, weil die F-Gas-Verordnung Alternativen verteuert.",
        [sec("Wo R744 stark ist",
             "In der Kaltwasserbereitung und bei Wärmepumpen mit hohen Vorlauftemperaturen zeigt R744 hervorragende Wirkungsgrade.",
             "Besonders im transkritischen Betrieb lassen sich hohe Temperaturhübe realisieren."),
         sec("Die Technik ist anspruchsvoller",
             "Die Drücke liegen deutlich über denen konventioneller Kältemittel. Rohrleitungen, Armaturen und Verdichter müssen dafür ausgelegt sein.",
             "Die EN 378 klassifiziert R744 als A1 – ungefährlich für Menschen, aber mit hohen Systemdrücken."),
         sec("Planung und Wartung",
             "Wer auf R744 umstellt, braucht spezialisierte Planung und Wartung. Die Kältelastberechnung bleibt gleich, die Anlagentechnik ist eine andere.")]),
    art("hybridkuehler-trocken-verdunstung", "Hybridkühler: Trocken- und Verdunstungsbetrieb kombinieren",
        "effizienz", ["kuehlung"], "2026-03-08",
        "Hybridkühler fahren trocken im Winter und adiabatisch im Sommer – ein Gerät für das ganze Jahr.",
        "Ein Hybridkühler vereint zwei Betriebsarten in einem Gerät: Im Winter kühlt er trocken über die Außenluft, im Sommer unterstützt die adiabatische Kühlung über Verdunstung. Damit deckt er das ganze Jahr ab und senkt den Strombedarf genau in den Spitzenzeiten.",
        [sec("Die zwei Betriebsarten",
             "Im Trockenbetrieb arbeitet der Hybridkühler wie ein klassischer Trockenkühler. Sobald die Außentemperatur steigt, befeuchtet er die Ansaugluft und nutzt die Verdunstungskälte.",
             "Der Übergang ist stufenlos geregelt und für den Betreiber unsichtbar."),
         sec("Wasser und Wartung",
             "Der Verdunstungsbetrieb braucht aufbereitetes Wasser. Die Befeuchtungspads müssen regelmäßig getauscht werden, sonst sinkt die Leistung.",
             "Die Wasserqualität ist ein Wartungsthema, das in den Wartungsvertrag gehört."),
         sec("Einsatz als Rückkühler",
             "Hybridkühler ersetzen oft Rückkühler von Kaltwassersätzen und senken deren Kondensationstemperatur – mit direktem Effekt auf den Stromverbrauch.")]),
    art("kaltwasser-waermepumpe", "Kaltwasserbereitung mit Wärmepumpe",
        "effizienz", ["kuehlung", "smart"], "2026-06-04",
        "Wärmepumpen erzeugen Kaltwasser und Wärme zugleich – das neue Doppelspiel in der Industrie.",
        "Eine Wärmepumpe kann beides: Kaltwasser für Prozesse und Wärme für die Halle. In der Kaltwasserbereitung ersetzt sie klassische Kaltwassersätze und liefert nebenbei Heizenergie. Für Betriebe mit gleichzeitigem Kälte- und Wärmebedarf ist das die wirtschaftlichste Lösung.",
        [sec("Wirkungsweise",
             "Die Wärmepumpe entzieht dem Prozesskreis Wärme und gibt sie auf höherem Temperaturniveau an das Heizsystem ab. Kälte und Wärme entstehen immer gemeinsam.",
             "Je besser die Temperaturniveaus zusammenpassen, desto höher die Jahresarbeitszahl."),
         sec("Kaskaden für große Leistungen",
             "Für große Industriebetriebe werden mehrere Wärmepumpen kaskadiert. Die Steuerung verteilt die Last auf die effizientesten Betriebspunkte.",
             "Ein Pufferspeicher entkoppelt Erzeugung und Verbrauch."),
         sec("Förderung",
             "Wärmepumpen in Bestandsgebäuden und Industrieanlagen sind förderfähig. Die Kombination mit der BAFA-Förderung verkürzt die Amortisation deutlich.")]),
    art("metallverarbeitung-temperatur", "Temperaturregelung in der Metallverarbeitung",
        "branchen", ["produktion", "kuehlung"], "2026-04-09",
        "Maßhaltigkeit beginnt bei der Temperatur – Kühlung in der Metallverarbeitung.",
        "In der Metallverarbeitung entscheidet die Temperatur über Maßhaltigkeit und Werkzeugstandzeit. Eine ungleichmäßig gekühlte Maschine produziert Ausschuss, lange bevor jemand die Ursache sieht. Die Temperaturregelung ist deshalb ein zentraler Qualitätsfaktor.",
        [sec("Wo Kälte gebraucht wird",
             "Spindeln, Hydraulik, Schweißprozesse und Messtechnik haben unterschiedliche Kühlbedarfe. Die Prozessklimatisierung führt sie in getrennten Kreisen.",
             "Kaltwassersätze liefern die präzise Kühlwassertemperatur für die Maschinenkreise."),
         sec("Mess- und Prüftechnik",
             "Messräume brauchen ein besonders stabiles Klima, weil Temperaturschwankungen die Messergebnisse verfälschen. Hier zählt die Genauigkeit der Regelung mehr als die Leistung."),
         sec("Stillstand vermeiden",
             "Ein Kälteausfall stoppt die gesamte Fertigung. Die Notkühlung gehört deshalb in die Risikoplanung der Metallverarbeitung.")]),
    art("abluft-waermepumpen-hallen", "Abluft-Wärmepumpen für Produktionshallen",
        "effizienz", ["produktion", "smart"], "2026-05-10",
        "Abluft-Wärmepumpen heben Wärme aus der Hallenabluf – für Heizung und Warmwasser.",
        "Produktionshallen blasen permanent warme Abluft ins Freie. Abluft-Wärmepumpen fangen diese Wärme ab, heben sie auf Heizniveau und speisen sie in das Heizsystem zurück. Die Halle heizt sich damit gewissermaßen selbst.",
        [sec("Die Technik",
             "Die Wärmepumpe entzieht der Abluft Wärme, bevor sie ins Freie geht. Über einen Wärmetauscher wird die gewonnene Energie auf den Heizkreis übertragen.",
             "Je wärmer die Abluft und je niedriger die Vorlauftemperatur, desto besser die Effizienz."),
         sec("Kombination mit der Lüftung",
             "Idealerweise wird die Abluft-Wärmepumpe mit dem Lüftungsgerät der Halle gekoppelt. So entsteht ein System aus Lüftung, Wärmerückgewinnung und Heizung.",
             "Die Luftmengen müssen zur Heizlast passen – die Planung beginnt mit der Lastberechnung."),
         sec("Wirtschaftlichkeit",
             "Die Amortisation hängt von den Betriebsstunden der Lüftung ab. Hallen mit 24-Stunden-Betrieb amortisieren Abluft-Wärmepumpen in wenigen Jahren.")]),
    art("lueftungsgeraete-wrg", "Lüftungsgeräte mit Wärmerückgewinnung",
        "effizienz", ["kuehlung", "smart"], "2026-01-25",
        "Lüftungsgeräte mit Wärmerückgewinnung senken den Energiebedarf von Hallen spürbar.",
        "Moderne Lüftungsgeräte machen aus Abluft eine Energiequelle: Wärmerückgewinnung überträgt bis zu 80 Prozent der Abwärme auf die Zuluft. Für Industriehallen mit hohem Luftwechsel ist das der größte einzelne Hebel zur Senkung des Heizbedarfs.",
        [sec("Funktionsweise",
             "Im Wärmetauscher des Lüftungsgeräts gibt die warme Abluft ihre Energie an die kalte Zuluft ab – ohne dass die Luftströme sich vermischen.",
             "Im Sommer kann das System umgekehrt arbeiten und die Zuluft kühlen."),
         sec("Planungsgrößen",
             "Luftwechselrate, Ablufttemperatur und Feuchte bestimmen die Rückgewinnungsleistung. Die Kältelastberechnung der Halle bleibt davon unabhängig.",
             "Bei hoher Luftfeuchte lohnt sich eine Feuchterückgewinnung als Ergänzung."),
         sec("Integration in die Kältetechnik",
             "Lüftung und Kälte sollten gemeinsam geplant werden. Ein Kaltwassersatz, der nur die Zuluftkühlung übernimmt, ist oft günstiger als eine komplette Hallenklimatisierung.")]),
    art("en-378-sicherheit", "EN 378: Sicherheit von Kälteanlagen",
        "sicherheit", ["kuehlung"], "2026-03-02",
        "Die EN 378 regelt Aufstellung, Füllmengen und Sicherheit von Kälteanlagen.",
        "Die EN 378 ist die zentrale Norm für die Sicherheit von Kälteanlagen. Sie regelt, welche Kältemittel in welchen Räumen verwendet werden dürfen, wie Füllmengen begrenzt werden und welche Schutzmaßnahmen Pflicht sind. Wer Industrie-Klimageräte plant, kommt an ihr nicht vorbei.",
        [sec("Kältemittelklassen",
             "Kältemittel werden nach Toxizität und Brennbarkeit klassifiziert. R290 ist beispielsweise A3 – nicht toxisch, aber brennbar.",
             "Die Klasse bestimmt die zulässige Füllmenge je Raumvolumen und die erforderliche Lüftung."),
         sec("Füllmengengrenzen",
             "Die EN 378 definiert maximale Füllmengen in Abhängigkeit von Raumgröße, Aufstellhöhe und Kältemittelklasse. Bei Überschreitung sind technische Maßnahmen wie Leckage-Erkennung vorgeschrieben.",
             "Für die Kältelastberechnung ändert das nichts, wohl aber für die Gerätewahl."),
         sec("Dokumentation",
             "Die Norm verlangt eine nachvollziehbare Dokumentation der Anlage. Zusammen mit der F-Gas-Verordnung entsteht daraus das Sicherheitsdossier des Betreibers.")]),
    art("atex-klimageraete", "ATEX-Klimageräte für explosionsgefährdete Bereiche",
        "sicherheit", ["produktion", "kuehlung"], "2026-04-20",
        "Explosionsgeschützte Klimageräte kühlen dort, wo Funken tödlich sein können.",
        "In Bereichen mit brennbaren Gasen oder Stäuben darf keine normale Klimaanlage laufen. ATEX-Klimageräte sind für explosionsgefährdete Zonen gebaut: sämtliche elektrischen Bauteile sind gekapselt, Funkenbildung ist ausgeschlossen. Die EN 378 und die ATEX-Richtlinie bestimmen die Auslegung.",
        [sec("Zonen verstehen",
             "ATEX unterscheidet Zonen nach der Wahrscheinlichkeit explosionsfähiger Atmosphäre. Je gefährlicher die Zone, desto höher die Anforderungen an das Gerät.",
             "Die Zone muss in der Ausschreibung stehen – die Geräte sind zonenspezifisch."),
         sec("Gerätetechnik",
             "Gekapselte Motoren, explosionsgeschützte Schaltkästen, geerdete Bauteile: ATEX-Klimageräte sind in der Anschaffung deutlich teurer, dafür sicher.",
             "Die Wartung verlangt speziell geschultes Personal und dokumentierte Prüfungen."),
         sec("Kombination mit Prozessklimatisierung",
             "Auch in Ex-Bereichen brauchen Prozesse stabile Temperaturen. Die Prozessklimatisierung übernimmt das mit ATEX-zertifizierten Geräten – bis zur Schaltschrankklimatisierung in der Steuerungstechnik.")]),
    art("container-klimatisierung-rechenzentren", "Container-Klimatisierung für mobile Rechenzentren",
        "branchen", ["rechenzentrum", "kuehlung"], "2026-06-12",
        "Container-Rechenzentren brauchen Klimatisierung, die mobil und trotzdem redundant ist.",
        "Container-Rechenzentren stehen dort, wo Rechenleistung kurzfristig gebraucht wird: auf Baustellen, in Katastrophengebieten, hinter Rechenzentren im Ausbau. Die Container-Klimatisierung muss kompakt, effizient und absolut zuverlässig sein – bei begrenztem Platz für die Technik.",
        [sec("Herausforderungen im Container",
             "Auf engstem Raum wird die Abwärme mehrerer Racks konzentriert. Die Luftführung muss die Kaltgänge sauber von den Warmsgängen trennen.",
             "Free Cooling ist im Container besonders wertvoll, weil der Platz für mechanische Kälte begrenzt ist."),
         sec("Redundanz im Kleinen",
             "Ein Container ohne Notkühlung ist ein Ausfallrisiko. Zwei unabhängige Kältepfade müssen auch im Container Platz finden.",
             "Die Fernwartung überwacht Temperatur und Kälteleistung in Echtzeit."),
         sec("Mobile Anbindung",
             "Container-Klimatisierung wird häufig als Komplettlösung gemietet: Gerät, Anschlüsse, Wartung aus einer Hand.")]),
    art("befeuchtung-produktion", "Befeuchtung in der Produktion: Luftfeuchte stabilisieren",
        "branchen", ["produktion", "kuehlung"], "2026-02-08",
        "Zu trockene Luft kostet Qualität: Befeuchtung stabilisiert Prozesse und Material.",
        "Temperatur ist nur die halbe Geschichte. In vielen Produktionen ist die relative Luftfeuchte der kritischere Parameter: zu trocken lädt sich Material statisch auf, zu feucht quillt Papier oder Holz. Die Befeuchtung in der Produktion hält die Feuchte im Prozessfenster.",
        [sec("Feuchte als Prozessparameter",
             "In der Elektronikfertigung verursacht statische Aufladung durch trockene Luft Ausschuss. In der Holz- und Papierindustrie führen Feuchteschwankungen zu Maßabweichungen.",
             "Die Prozessklimatisierung regelt Feuchte und Temperatur gemeinsam."),
         sec("Befeuchtungstechniken",
             "Dampfbefeuchter, Verdunstungsbefeuchter und Ultraschallbefeuchter unterscheiden sich in Energiebedarf, Hygiene und Regelgeschwindigkeit.",
             "Die Wahl hängt vom geforderten Feuchtefenster und der Luftmenge ab."),
         sec("Kälte und Feuchte koppeln",
             "Klimageräte, die kühlen, entfeuchten automatisch. Wer beides braucht – kühlen und befeuchten –, plant die Entfeuchtung und die Befeuchtung in einem System.")]),
    art("eer-seer-verstehen", "EER und SEER: Effizienzkennzahlen richtig lesen",
        "effizienz", ["kuehlung"], "2026-01-08",
        "EER und SEER sind die wichtigsten Kennzahlen für Klimageräte – und werden oft falsch gelesen.",
        "EER und SEER sagen, wie effizient ein Klimagerät arbeitet. Der EER beschreibt den Wirkungsgrad bei Nennbedingungen, der SEER über die gesamte Kühlsaison. Für Industrie-Klimageräte zählt der SEER, weil er das Teillastverhalten abbildet.",
        [sec("Die Kennzahlen im Detail",
             "EER = Kälteleistung geteilt durch elektrische Leistung bei Normbedingungen. Ein EER von 3,5 bedeutet: ein Kilowatt Strom erzeugt 3,5 Kilowatt Kälte.",
             "Der SEER gewichtet die Betriebspunkte über das Jahr. Je höher der SEER, desto niedriger die Stromrechnung."),
         sec("Datenblatt vs. Realität",
             "Die Normbedingungen entsprechen selten der eigenen Halle. Faktoren wie Außentemperatur, Teillast und Regelung verschieben die realen Werte.",
             "Deshalb: Kennzahlen vergleichen, aber die Auslegung auf die eigene Kältelastberechnung stützen."),
         sec("Förderung und Ausschreibung",
             "Viele Förderprogramme und Ausschreibungen fordern Mindest-SEER-Werte. Die ErP-Richtlinie setzt den gesetzlichen Rahmen.")]),
    art("kaelteleistung-berechnen", "Kälteleistung berechnen: Formeln und Beispiele",
        "planung", ["kuehlung"], "2026-02-18",
        "Kälteleistung berechnen ist Handwerk: die wichtigsten Formeln für die Praxis.",
        "Kälteleistung berechnen klingt kompliziert, ist aber überschaubares Handwerk. Im Kern geht es um drei Formeln: Wärme über Masse, Wärme über Luft und Wärme über Wasser. Wer sie beherrscht, kann die meisten Kühllasten selbst abschätzen, bevor der Fachplaner die Kältelastberechnung übernimmt.",
        [sec("Die Grundformel",
             "Q = m × c × ΔT: Wärmeleistung gleich Masse mal spezifische Wärmekapazität mal Temperaturdifferenz.",
             "Für Wasser gilt vereinfacht: Ein Kubikmeter pro Stunde mit einem Kelvin Differenz entspricht 1,16 Kilowatt Kälteleistung."),
         sec("Luftkühlung abschätzen",
             "Für Luft: 1.000 Kubikmeter pro Stunde mit 10 Kelvin Abkühlung entsprechen rund 3,3 Kilowatt.",
             "Damit lassen sich Hallenlasten grob abschätzen – die genaue Rechnung übernimmt die Kältelastberechnung."),
         sec("Wärmequellen addieren",
             "Maschinenabwärme, Personen, Beleuchtung und Transmission werden addiert. Die Summe ist die Kühllast, auf die der Kaltwassersatz oder das Dachklimagerät dimensioniert wird.")]),
    art("kaeltemittelleckagen-erkennen", "Kältemittelleckagen: Erkennen und vermeiden",
        "wartung", ["kaeltemittel", "kuehlung"], "2026-03-18",
        "Kältemittelleckagen kosten Leistung, Geld und die Umwelt – so erkennt man sie früh.",
        "Eine Kältemittelleckage ist der häufigste Grund für schleichenden Leistungsverlust bei Klimaanlagen. Das Gerät kühlt immer schlechter, der Verdichter arbeitet immer länger, und der Stromverbrauch steigt. Wer Leckagen früh erkennt, spart Geld und erfüllt die F-Gas-Verordnung.",
        [sec("Symptome einer Leckage",
             "Sinkende Kälteleistung, steigende Laufzeiten, Eisbildung am Verdampfer und zischende Geräusche sind typische Anzeichen.",
             "Der erste Verdacht gehört in ein Messprotokoll: Kältemittelfüllstand und Überhitzung dokumentieren."),
         sec("Dichtigkeitsprüfung nach F-Gas",
             "Die F-Gas-Verordnung schreibt regelmäßige Dichtigkeitsprüfungen vor – abhängig von Kältemittelmenge und Treibhauspotenzial.",
             "Prüfungen werden dokumentiert; die Unterlagen sind im Zweifel das erste, was der Prüfer verlangt."),
         sec("Leckagen vermeiden",
             "Saubere Verschraubungen, qualifizierte Monteure und Vibrationsentkopplung verhindern die meisten Leckagen. Die Wartung ist hier günstiger als die Reparatur.")]),
    art("hochregallager-klimatisierung", "Hochregallager klimatisieren: Anforderungen und Lösungen",
        "branchen", ["logistik", "kuehlung"], "2026-05-18",
        "Hochregallager stellen besondere Anforderungen an die Klimatisierung – von der Luftschichtung bis zur Brandschutzkopplung.",
        "Hochregallager sind keine normalen Hallen: Die Regale reichen bis unter die Decke, die Luftschichtung ist extrem, und die Kältelast kommt aus dem Betrieb der Fördertechnik. Die Klimatisierung muss diese Besonderheiten abbilden.",
        [sec("Luftschichtung beachten",
             "Warme Luft steigt nach oben – in Hochregallagern entsteht eine ausgeprägte Temperaturschichtung. Die Messung an einem Punkt reicht nicht aus.",
             "Umluftsysteme mit Luftauslass in Regalhöhe verteilen die Temperatur gleichmäßiger."),
         sec("Fördertechnik als Wärmequelle",
             "Shuttles, Stetigförderer und Automatiklager erzeugen Abwärme, die in die Kältelastberechnung einfließt. Der Gleichzeitigkeitsfaktor ist hier besonders wichtig.",
             "Dachklimageräte sind die häufigste Lösung, weil sie keine Regalfläche belegen."),
         sec("Brandschutzkopplung",
             "Die Klimatisierung muss mit der Brandmeldeanlage gekoppelt sein, damit sie im Alarmfall abschaltet. Das ist ein Planungsthema, kein Wartungsthema.")]),
    art("pharma-gmp-klima", "Pharmaproduktion: Klima nach GMP",
        "branchen", ["pharma", "kuehlung"], "2026-06-20",
        "GMP verlangt dokumentierte Klimakonstanz – die Pharmaklimatisierung liefert sie.",
        "In der Pharmaproduktion ist das Klima Teil der Produktqualität. GMP (Good Manufacturing Practice) verlangt, dass Temperatur, Feuchte und Partikelreinheit dokumentiert eingehalten werden. Die Klimatisierung ist damit ein validierungspflichtiges System.",
        [sec("Was GMP fordert",
             "Stabile Klimabedingungen mit dokumentierter Einhaltung, qualifizierte Anlagen und regelmäßige Revalidierung.",
             "Die Messdaten müssen über Jahre zurückverfolgbar sein – die Steuerung braucht entsprechenden Speicher."),
         sec("Reinraumklimatisierung und Druckstufen",
             "In Reinräumen kommen Reinraumklimatisierung nach ISO 14644 und Druckstufen zusammen. Die Lüftungsgeräte müssen den Differenzdruck halten.",
             "Die Prozessklimatisierung regelt Feuchte und Temperatur in engen Fenstern."),
         sec("Qualifizierung einplanen",
             "Die Anlage wird nach IQ/OQ/PQ qualifiziert. Das kostet Zeit und Geld, gehört aber zur GMP-Pflicht.")]),
    art("elektroindustrie-klima", "Elektroindustrie: Saubere Klimatisierung für die Fertigung",
        "branchen", ["produktion", "kuehlung"], "2026-03-26",
        "Elektronikfertigung verlangt saubere, feuchtestabile Luft – Klimatisierung als Qualitätsfaktor.",
        "In der Elektroindustrie ist Staub der Feind Nummer eins und Feuchte der zweite. Die Klimatisierung muss beides beherrschen: Partikel aus der Luft halten und die relative Feuchte im Prozessfenster stabilisieren. Ein Ausfall kostet Ausschuss und Messwiederholungen.",
        [sec("Reinheit sicherstellen",
             "Je nach Produktstufe greift die Reinraumklimatisierung nach ISO 14644. Schon einfache Filterstufen reduzieren Ausschuss durch Staubpartikel.",
             "Die Luftführung übernimmt die Partikelabfuhr aus dem kritischen Bereich."),
         sec("Feuchte als Ausschussfaktor",
             "Statische Aufladung durch trockene Luft zerstört empfindliche Bauteile. Die Befeuchtung hält die Feuchte im definierten Fenster.",
             "Sensoren und Regelung müssen schnell reagieren, weil Prozesse auf Feuchtesprünge sofort antworten."),
         sec("Präzise Kälte für Maschinen",
             "Bestückungsautomaten und Messplätze brauchen stabile Temperaturen. Die Schaltschrankklimatisierung schützt die Steuerungstechnik der Anlagen.")]),
    art("druckluftkuehler-maschinenparks", "Druckluftkühler für Maschinenparks",
        "branchen", ["produktion", "kuehlung"], "2026-04-16",
        "Druckluftkühler senken die Temperatur der Druckluft und schützen die gesamte Maschinenperipherie.",
        "Druckluft ist in vielen Betrieben die vierte Versorgungssparte – und sie ist heiß. Druckluftkühler senken die Temperatur nach der Verdichtung, trocknen die Luft und schützen Ventile, Zylinder und Werkzeuge vor Verschleiß. Die Kühlung der Druckluft ist Wartung an der Quelle.",
        [sec("Warum Druckluft gekühlt werden muss",
             "Verdichtung erhitzt Luft auf über 100 Grad. Ohne Nachkühlung wandert die Wärme in die Leitungen und die Feuchte kondensiert an den ungünstigsten Stellen.",
             "Kälte senkt den Drucktaupunkt und damit die Korrosion im Netz."),
         sec("Technische Lösung",
             "Nachkühler, Kältetrockner oder Kaltwassersätze mit Druckluftanbindung: Die Variante hängt von Luftmenge und Drucktaupunkt ab.",
             "Die Kältelastberechnung berücksichtigt die Abwärme der Kompressoren."),
         sec("Energieeffizienz",
             "Moderne Kompressoren geben Abwärme ab, die über Wärmerückgewinnung nutzbar ist. Druckluftkühlung und Hallenheizung lassen sich so koppeln.")]),
    art("sommer-wartungscheckliste", "Sommer-Checkliste für Kälteanlagen",
        "wartung", ["saison", "kuehlung"], "2026-05-02",
        "Die Sommer-Checkliste verhindert Ausfälle genau dann, wenn die Kälte am meisten gebraucht wird.",
        "Kälteanlagen fallen nie im Januar aus, sondern immer in der ersten Hitzewelle. Die Sommer-Checkliste nimmt die Schwachstellen vorweg: Filter, Wärmetauscher, Kältemittel und Regelung werden geprüft, bevor die Last kommt.",
        [sec("Die wichtigsten Punkte",
             "Filter und Wärmetauscher reinigen, Kältemittelfüllstand prüfen, Kondensatorlüfter testen, Regelung und Sollwerte kontrollieren.",
             "Dazu gehört die Dichtigkeitsprüfung nach der F-Gas-Verordnung."),
         sec("Messprotokoll führen",
             "Vor und nach der Wartung werden Drücke, Temperaturen und Ströme gemessen. Der Vergleich zeigt, ob die Anlage tatsächlich wieder auf Soll arbeitet.",
             "Das Protokoll ist gleichzeitig die Dokumentationsgrundlage für den Wartungsvertrag."),
         sec("Saisonstart früh planen",
             "Der Wartungstermin sollte vor der ersten Hitzewelle liegen, nicht während sie tobt. Die saisonale Inbetriebnahme im Frühjahr ist der richtige Zeitpunkt.")]),
    art("saisonale-inbetriebnahme", "Saisonale Inbetriebnahme von Kälteanlagen",
        "wartung", ["saison", "kuehlung"], "2026-04-02",
        "Die saisonale Inbetriebnahme bringt Kälteanlagen sicher und effizient in die Saison.",
        "Nach der Winterpause laufen Kälteanlagen nicht einfach wieder an. Die saisonale Inbetriebnahme prüft jeden Baustein – vom Kältemittel über die Regelung bis zur Luftseite – bevor die Last steigt. Wer das überspringt, riskiert den Ausfall in der ersten Hitzewelle.",
        [sec("Ablauf der Inbetriebnahme",
             "Sichtprüfung, Dichtigkeitsprüfung, Füllstand, elektrische Anschlüsse, Funktionstest der Ventilatoren und der Regelung.",
             "Danach folgt der Probebetrieb über mehrere Stunden mit Messprotokoll."),
         sec("Kältemittel und Öl",
             "Nach der Standzeit müssen Füllstand und Ölzustand geprüft werden. Bei Bestandsanlagen mit fluorierten Kältemitteln ist die F-Gas-Dokumentation Pflicht.",
             "Leckagen aus dem Winter werden bei der Inbetriebnahme sichtbar – besser jetzt als im Sommer."),
         sec("Dokumentation",
             "Das Inbetriebnahmeprotokoll ist die Basis für den Vergleich mit späteren Messungen. Es gehört in das Anlagendossier des Betreibers.")]),
    art("fernwartung-kaelteanlagen", "Fernwartung von Kälteanlagen",
        "wartung", ["smart", "kuehlung"], "2026-06-08",
        "Fernwartung überwacht Kälteanlagen in Echtzeit und verhindert Ausfälle, bevor sie entstehen.",
        "Fernwartung macht aus einer Kälteanlage ein meldendes System: Drücke, Temperaturen und Betriebsstunden fließen in Echtzeit an den Dienstleister. Abweichungen werden erkannt, bevor sie zum Ausfall werden. Für Produktionsbetriebe ist das die günstigste Form der Ausfallversicherung.",
        [sec("Was überwacht wird",
             "Kältemitteldruck, Verdichterstrom, Vor- und Rücklauftemperaturen, Laufzeiten und Alarme der Regelung.",
             "Aus den Verläufen lassen sich Verschleiß und Leistungsabfall prognostizieren."),
         sec("Wirtschaftlichkeit",
             "Die Fernwartung kostet einen Bruchteil eines ungeplanten Stillstands. Sie verlängert die Intervalle der Vor-Ort-Wartung nicht, ergänzt sie aber.",
             "Im Wartungsvertrag wird die Fernwartung meist als Option angeboten."),
         sec("Daten und Sicherheit",
             "Die Daten bleiben beim Betreiber oder werden verschlüsselt an den Dienstleister übertragen. Die Anbindung an die Gebäudeautomation erfolgt über standardisierte Schnittstellen.")]),
    art("iot-klimasteuerung", "Smarte Steuerung: Klimaanlagen im IoT-Netz",
        "effizienz", ["smart", "kuehlung"], "2026-06-26",
        "IoT-Steuerung optimiert Klimaanlagen in Echtzeit – und spart Energie, ohne Komfort zu kosten.",
        "Eine Klimaanlage, die immer auf Volllast läuft, verschwendet Energie. Die smarte Steuerung im IoT-Netz passt die Kälteleistung an die tatsächliche Last an: Außentemperatur, Belegung und Strompreis fließen in die Regelung ein. Das Ergebnis sind Einsparungen von 10 bis 20 Prozent ohne Komfortverlust.",
        [sec("Daten statt Schätzwerte",
             "Sensoren messen Temperatur, Feuchte und Belegung in Echtzeit. Die Steuerung fährt die Anlage nur so hoch, wie es die Last verlangt.",
             "Die Verbrauchsdaten liefern zugleich die Grundlage für die Amortisationsrechnung."),
         sec("Lastmanagement",
             "In Zeiten hoher Strompreise kann die Kälteanlage Lastspitzen glätten, indem sie Pufferspeicher nutzt. Das senkt die Leistungspreise des Netzbetreibers.",
             "Die Nachtspeicher-Kühlung ist eine Variante dieses Prinzips."),
         sec("Integration",
             "Die IoT-Steuerung integriert Kältemaschinen, Lüftung und Befeuchtung zu einem System. Die Fernwartung nutzt dieselben Daten.")]),
    art("amortisation-effizienz", "Amortisationsrechnung für Effizienzmaßnahmen",
        "foerderung", ["kuehlung", "smart"], "2026-05-26",
        "Amortisationsrechnung: Wann sich Effizienzmaßnahmen an Kälteanlagen wirklich rechnen.",
        "Jede Effizienzmaßnahme an einer Kälteanlage hat einen Preis und eine Einsparung. Die Amortisationsrechnung stellt beide gegenüber: Investition, jährliche Einsparung, Förderung und Lebensdauer ergeben zusammen die Antwort auf die Frage, ob sich die Maßnahme lohnt.",
        [sec("Die Rechnung",
             "Amortisationszeit = (Investition minus Förderung) geteilt durch jährliche Einsparung.",
             "Die Einsparung wird aus den Verbrauchsdaten vor der Maßnahme hochgerechnet – nicht aus Herstellerangaben."),
         sec("Nicht nur den Strompreis rechnen",
             "Einsparungen entstehen auch durch weniger Wartung, längere Lebensdauer und geringere Ausfallrisiken. Diese Posten machen oft ein Drittel der Amortisation aus.",
             "Die BAFA-Förderung verkürzt die Amortisationszeit zusätzlich."),
         sec("Vergleich mit der Erneuerung",
             "Die Amortisationsrechnung der Nachrüstung wird mit der Ersatzinvestition verglichen. Bei alten Anlagen gewinnt oft der Neubau, bei jungen die Nachrüstung.")]),
    art("bafa-foerderung-kaelteanlagen", "BAFA-Förderung für Kälteanlagen 2026",
        "foerderung", ["kuehlung", "saison"], "2026-06-16",
        "Die BAFA-Förderung bezuschusst effiziente Kälteanlagen – so kommt man an das Geld.",
        "Die BAFA-Förderung unterstützt Unternehmen bei der Umstellung auf energieeffiziente Kältetechnik. Bezuschusst werden unter anderem neue Kaltwassersätze, Wärmerückgewinnung und die Umstellung auf natürliche Kältemittel. Der Zuschuss senkt die Investition deutlich – wenn der Antrag vor dem Kauf gestellt wird.",
        [sec("Was gefördert wird",
             "Effiziente Kälteanlagen, Kaltwassersätze mit hohem SEER, Wärmerückgewinnung, Free-Cooling-Nachrüstung und Umstellungen auf R290 oder R744.",
             "Die genauen Konditionen ändern sich regelmäßig – der Antrag sollte auf den aktuellen Förderaufruf abgestimmt sein."),
         sec("Antrag vor der Bestellung",
             "Die BAFA-Förderung wird vor der Auftragsvergabe beantragt. Nachträgliche Anträge scheitern.",
             "Das Angebot des Dienstleisters sollte bereits die förderfähigen Positionen ausweisen."),
         sec("Förderung und Amortisation",
             "Der Zuschuss fließt direkt in die Amortisationsrechnung ein. In Kombination mit der KfW-Finanzierung sinkt die Investitionshürde deutlich.")]),
    art("kfw-energetische-sanierung", "KfW-Kredit für energetische Sanierung",
        "foerderung", ["foerderung", "kuehlung"], "2026-07-02",
        "Der KfW-Kredit finanziert energetische Sanierung – auch Kältetechnik gehört dazu.",
        "Die KfW unterstützt energetische Sanierungen mit zinsgünstigen Krediten. Auch Maßnahmen an der Kältetechnik – effizientere Kaltwassersätze, Wärmerückgewinnung, Dämmung der Kälteverteilung – sind förderfähig. Die Kombination aus KfW-Kredit und BAFA-Förderung senkt die Finanzierungskosten doppelt.",
        [sec("Voraussetzungen",
             "Die Maßnahme muss zu einer messbaren Energieeinsparung führen. Energieberater und Fachplaner erstellen die Nachweise.",
             "Die Kältelastberechnung und die Amortisationsrechnung gehören zur Antragsunterlage."),
         sec("Kombination mit BAFA",
             "KfW und BAFA lassen sich kombinieren: Der Zuschuss senkt die Investitionssumme, der Kredit finanziert den Rest. Die Anträge laufen getrennt.",
             "Die Reihenfolge ist wichtig: erst die Förderung, dann die Finanzierung."),
         sec("Dokumentation",
             "Nach der Umsetzung wird die Einsparung nachgewiesen. Die Verbrauchsdaten der Fernwartung eignen sich dafür ideal.")]),
    art("notkuehlung-redundanz", "Notkühlung bei Ausfall: Redundanz planen",
        "planung", ["notfall", "rechenzentrum", "kuehlung"], "2026-07-10",
        "Redundanz ist keine Technikfrage, sondern eine Risikofrage: Wie lange darf die Kälte ausfallen?",
        "Jede Kälteanlage fällt irgendwann aus. Die Notkühlung beantwortet die Frage, wie lange der Betrieb ohne sie weiterlaufen darf. Für Rechenzentren sind das Minuten, für Produktionshallen Stunden. Die Redundanzplanung richtet die Investition an dieser Zahl aus.",
        [sec("Die Risikoanalyse",
             "Ausfallkosten je Stunde, Wiederanlaufzeit und Wartungsfenster bestimmen das Redundanzniveau. Ein Serverraum ohne Redundanz ist keine Planung, sondern ein Glücksspiel.",
             "Klassische Stufen: N (keine Redundanz), N+1 (ein Ersatzgerät), 2N (doppelte Auslegung)."),
         sec("Technische Optionen",
             "Mobile Klimageräte als flexible Reserve, fest installierte Reservegeräte oder ein zweiter unabhängiger Kältepfad.",
             "Die Container-Klimatisierung liefert Notkühlung als Mietlösung für den Ernstfall."),
         sec("Redundanz testen",
             "Eine Redundanz, die nie getestet wird, existiert nicht. Der jährliche Lasttest gehört in den Wartungsvertrag.")]),
    art("winterbetrieb-kaltwassersaetze", "Winterbetrieb von Kaltwassersätzen",
        "wartung", ["saison", "kuehlung"], "2026-01-05",
        "Kaltwassersätze im Winter: Frostschutz, Freikühlung und Teillast – die Stolperfallen.",
        "Kaltwassersätze arbeiten auch im Winter – nur unter anderen Bedingungen. Frostschutz, minimaler Volumenstrom und die Grenzen der Freikühlung bestimmen den Winterbetrieb. Wer diese Stolperfallen kennt, spart Energie und verhindert Frostschäden.",
        [sec("Frostschutz",
             "Außen aufgestellte Geräte brauchen Glykol im Kühlkreis oder eine Beheizung. Die Glykolkonzentration wird vor dem Winter geprüft.",
             "Frostschäden an Verdampfern sind teurer als jede Wartung."),
         sec("Freikühlung nutzen",
             "Bei niedrigen Außentemperaturen übernimmt die Freikühlung die Kälteerzeugung. Der Verdichter schaltet ab, der Stromverbrauch sinkt.",
             "Die Umschaltung muss sauber geregelt sein, sonst taktet der Kaltwassersatz im ineffizienten Bereich."),
         sec("Teillast im Winter",
             "Im Winter läuft die Anlage meist bei minimaler Last. Der Pufferspeicher verhindert, dass der Verdichter ständig startet und stoppt.")]),
    art("glykol-kuehlkreislaeufe", "Glykol in Kühlkreisläufen: Dosierung und Wartung",
        "wartung", ["kuehlung", "saison"], "2026-02-01",
        "Glykol schützt den Kühlkreislauf im Winter – falsch dosiert kostet es Leistung und Pumpe.",
        "Glykol senkt den Gefrierpunkt des Kühlwassers und schützt Anlagen im Winterbetrieb. Falsch dosiert, kostet es aber Leistung: Zu viel Glykol erhöht die Viskosität, die Pumpe verbraucht mehr Strom, und die Wärmeübertragung verschlechtert sich.",
        [sec("Die richtige Dosierung",
             "Die Glykolkonzentration richtet sich nach der tiefsten zu erwartenden Außentemperatur. Ein Frostschutz bis minus 20 Grad reicht für Mitteleuropa meist aus.",
             "Die Konzentration wird mit einem Refraktometer gemessen – nicht geschätzt."),
         sec("Wirkung auf die Leistung",
             "Glykol reduziert die Wärmeübertragung im Vergleich zu reinem Wasser. Die Kältelastberechnung und die Pumpenauslegung müssen diesen Faktor berücksichtigen.",
             "Nach Jahren im Kreis baut sich Glykol ab – der Zustand gehört in die Wartung."),
         sec("Korrosionsschutz",
             "Glykolkreisläufe enthalten Inhibitoren gegen Korrosion. Nach langer Standzeit oder bei Verfärbung wird die Konzentration geprüft und aufgefrischt.")]),
    art("pufferspeicher-kaelte", "Pufferspeicher für Kälteanlagen",
        "planung", ["kuehlung"], "2026-03-12",
        "Pufferspeicher entkoppeln Erzeugung und Verbrauch – und machen Kälteanlagen effizienter.",
        "Ein Pufferspeicher ist der Puffer zwischen Kälteerzeugung und Kälteverbrauch. Er gleicht Lastspitzen aus, reduziert Takten und erlaubt es dem Kaltwassersatz, in effizienten Betriebspunkten zu laufen. Für Prozesse mit schwankender Last ist er fast immer wirtschaftlich.",
        [sec("Warum Puffern",
             "Ohne Puffer folgt der Kaltwassersatz jeder Lastspitze – ineffizient und verschleißintensiv. Mit Puffer läuft er stabil in einem günstigen Betriebspunkt.",
             "Die Nachtspeicher-Kühlung nutzt den Puffer zusätzlich zur Lastverschiebung."),
         sec("Dimensionierung",
             "Die Puffergröße richtet sich nach Lastspitze, Dauer und erlaubter Temperaturdifferenz. Die Kältelastberechnung liefert die Randbedingungen.",
             "Faustformel: Je größer die Lastspitzen, desto größer der Speicher."),
         sec("Hydraulik",
             "Die Einbindung erfolgt über eine hydraulische Weiche oder ein separates Speicherbecken. Die Schaltung bestimmt, wie viel des Speichers tatsächlich nutzbar ist.")]),
    art("nachtspeicher-kuehlung", "Nachtspeicher-Kühlung: Spitzenlast glätten",
        "effizienz", ["smart", "kuehlung"], "2026-07-18",
        "Nachtspeicher-Kühlung erzeugt Kälte, wenn der Strom günstig ist – und nutzt sie am Tag.",
        "Nachtspeicher-Kühlung folgt einer einfachen Logik: Nachts, wenn Strom günstig und die Außentemperatur niedrig ist, wird Kälte erzeugt und gespeichert. Tagsüber deckt der Speicher die Last, ohne dass die Kältemaschine in der teuren Spitzenzeit läuft. Das glättet Last und Kosten.",
        [sec("Speichertechnologien",
             "Eisspeicher, Kaltwasserspeicher oder Betonkernaktivierung: Die Technologie bestimmt, wie viel Kälte pro Kubikmeter gespeichert wird.",
             "Eisspeicher haben die höchste Energiedichte, weil die Schmelzwärme genutzt wird."),
         sec("Wirtschaftlichkeit",
             "Die Ersparnis entsteht durch günstigere Nachtstrompreise und niedrigere Leistungspreise. Die Amortisationsrechnung vergleicht beide Effekte mit den Investitionskosten.",
             "Die smarte Steuerung optimiert den Ladezeitpunkt anhand des aktuellen Strompreises."),
         sec("Kombination mit Free Cooling",
             "Nachts ist es kalt genug für Free Cooling – die Speicherladung kann also nahezu kostenlos erfolgen. Die Kombination senkt die Betriebskosten weiter.")]),
    art("branchenloesungen-industrie", "Branchenlösungen: Klima für jede Industrie",
        "branchen", ["produktion", "kuehlung"], "2026-07-25",
        "Industrie-Klimageräte sind keine Standardware: Jede Branche stellt eigene Anforderungen.",
        "Eine Kunststofffabrik braucht andere Kälte als ein Rechenzentrum, eine Lebensmittelproduktion andere als eine Metallbearbeitung. Branchenlösungen übersetzen die Prozessanforderungen in konkrete Kältetechnik: Temperaturfenster, Redundanz, Hygiene und Wartung werden branchenspezifisch ausgelegt.",
        [sec("Die Branchen im Überblick",
             "Kunststoff: präzise Werkzeugkühlung über Kaltwassersätze. Rechenzentren: Serverraum-Klimatisierung mit Free Cooling und Redundanz. Logistik: Dachklimageräte für große Hallen. Pharma: Reinraumklimatisierung nach GMP.",
             "Jede Branche hat eigene Normen und eigene kritische Prozesse."),
         sec("Der Weg zur Lösung",
             "Der Einstieg ist immer dieselbe Kältelastberechnung. Daraus entsteht das Lastenheft, auf dessen Basis Geräte, Kreise und Redundanz dimensioniert werden.",
             "Wer die Branchenlogik versteht, spart bei der Planung – nicht bei den Geräten."),
         sec("Betrieb und Wartung",
             "Branchenlösungen enden nicht bei der Montage. Der Wartungsvertrag bildet die branchenspezifischen Intervalle und Dokumentationspflichten ab.")]),
]

# ---------------------------------------------------------------------------
# 20 Seiten
# ---------------------------------------------------------------------------
def page(slug, title, date, intro, sections, menu_order=0):
    return {
        "slug": slug, "title": title, "cat": None, "tags": [], "date": date,
        "excerpt": intro, "intro": intro, "sections": sections,
        "max_links": None, "auto_links": None, "menu_order": menu_order,
    }

PAGES = [
    page("startseite", "Industrie-Klimageräte für Produktion, Logistik und Rechenzentren", "2026-01-01",
         "Industrie-Klimageräte sind keine Komfortkühlung, sondern Prozesswerkzeug. Wir planen, liefern und warten Kältetechnik für Produktionshallen, Logistikzentren und Rechenzentren – von der Kältelastberechnung bis zum Wartungsvertrag.",
         [sec("Leistungen",
              "Kälteplanung und Dimensionierung, Installation und Inbetriebnahme, Wartung und Notdienst rund um die Uhr.",
              "Prozessklimatisierung, Kaltwassersätze, Dachklimageräte, Schaltschrankklimatisierung, Serverraum-Klimatisierung und Mobile Klimageräte aus einer Hand."),
          sec("Warum Betreiber auf uns setzen",
              "Jede Anlage beginnt mit einer echten Kältelastberechnung. Jede Anlage wird mit Redundanzkonzept ausgeliefert. Jede Anlage wird dokumentiert gewartet.",
              "Förderung über die BAFA-Förderung wird bei der Planung mitgedacht.")]),
    page("leistungen-planung", "Leistungen: Kälteplanung und Dimensionierung", "2026-01-02",
         "Gute Kältetechnik beginnt mit guter Planung. Wir erstellen Kältelastberechnungen, Lastenhefte und Redundanzkonzepte – bevor ein Gerät bestellt wird.",
         [sec("Planungsleistungen",
              "Kältelastberechnung nach den Lasten der Maschinen, Beleuchtung und Gebäudehülle.",
              "Technologievergleich: Kaltwassersatz, Dachklimagerät oder dezentrale Lösung. Dazu Amortisationsrechnung und Förderberatung (BAFA-Förderung)."),
          sec("Redundanz und Ausfallsicherheit",
              "Notkühlkonzepte, Redundanzstufen N, N+1 und 2N, hydraulische Verschaltung mit Pufferspeicher.",
              "Die Planung endet mit einem Lastenheft, das als Ausschreibungsgrundlage dient.")]),
    page("leistungen-installation", "Leistungen: Installation und Inbetriebnahme", "2026-01-03",
         "Montage, Anschluss und Inbetriebnahme aus einer Hand – mit Protokoll und Übergabe.",
         [sec("Installation",
              "Aufstellung, Kältemittelleitungen, Hydraulik und Elektroanschluss durch zertifizierte Monteure.",
              "Arbeiten an Kältemittelkreisen nach EN 378 und F-Gas-Verordnung mit vollständiger Dokumentation."),
          sec("Inbetriebnahme",
              "Funktionstest, Einregelung, Messprotokoll und Unterweisung des Betriebspersonals.",
              "Die saisonale Inbetriebnahme im Frühjahr ist im Wartungsvertrag enthalten.")]),
    page("leistungen-wartung", "Leistungen: Wartung und Service", "2026-01-04",
         "Regelmäßige Wartung hält Kälteanlagen effizient und sicher – mit dokumentierten Durchgängen.",
         [sec("Wartungsvertrag",
              "Zwei Wartungsdurchgänge pro Jahr, Dichtigkeitsprüfung nach F-Gas-Verordnung, Filterwechsel, Reinigung der Wärmetauscher.",
              "Optional mit Fernwartung und garantierter Reaktionszeit im Notfall."),
          sec("Leistungen im Detail",
              "Kältemittelleckagen finden und beheben, Glykolkonzentration prüfen, Effizienzwerte dokumentieren.",
              "Alle Durchgänge werden protokolliert und bilden das Anlagendossier.")]),
    page("leistungen-notdienst", "Leistungen: Notdienst 24/7", "2026-01-05",
         "Wenn die Kälte ausfällt, zählt jede Stunde. Der Notdienst reagiert rund um die Uhr.",
         [sec("Garantierte Reaktionszeiten",
              "Für Produktionsbetriebe und Rechenzentren garantieren wir Reaktionszeiten von wenigen Stunden – auch am Wochenende.",
              "Mobile Klimageräte überbrücken die Zeit bis zur Reparatur."),
          sec("Notkühlung als Service",
              "Mietgeräte für die Notkühlung stehen auf Abruf bereit, inklusive Anschluss und Inbetriebnahme.",
              "Das Notkühlkonzept wird gemeinsam mit dem Redundanzkonzept geplant.")]),
    page("produkte-dachklimageraete", "Dachklimageräte", "2026-01-06",
         "Dachklimageräte kühlen Hallen, ohne wertvolle Fläche zu belegen – modular erweiterbar.",
         [sec("Einsatz",
              "Produktionshallen, Logistikzentren, Hochregallager. Die komplette Kältetechnik steht auf dem Dach.",
              "Modulare Bauweise: Bei Hallenerweiterung wird ein weiteres Gerät aufgestellt."),
          sec("Ausstattung",
              "Wärmerückgewinnung, adiabatische Zusatzkühlung und Anbindung an die IoT-Steuerung.",
              "Die Auslegung basiert auf der Kältelastberechnung der Halle.")]),
    page("produkte-kaltwassersaetze", "Kaltwassersätze", "2026-01-07",
         "Kaltwassersätze versorgen mehrere Verbraucher zentral mit präziser Kälteleistung.",
         [sec("Einsatz",
              "Zentrale Kälte für Maschinenkreise, Prozessklimatisierung und Kaltwasserbereitung.",
              "Leistungen von wenigen Kilowatt bis in den Megawattbereich, mit oder ohne Free-Cooling-Register."),
          sec("Ausstattung",
              "Drehzahlgeregelte Verdichter, Pufferspeicher-Anbindung, Fernwartungsschnittstelle.",
              "Betrieb mit R290, R744 oder fluorierten Kältemitteln nach F-Gas-Verordnung.")]),
    page("produkte-schaltschrankklimatisierung", "Schaltschrankklimatisierung", "2026-01-08",
         "Schaltschrankklimatisierung schützt die Steuerungstechnik vor Überhitzung und Ausfall.",
         [sec("Einsatz",
              "Steuerungsschränke in Produktion und Maschinenparks, bei denen Wärme die Lebensdauer verkürzt.",
              "Filterlüfter, Kompaktklimageräte oder Kältesysteme mit Wärmetauscher – je nach Verlustleistung."),
          sec("Vorteile",
              "Höhere Verfügbarkeit, längere Bauteillebensdauer, weniger Stillstand.",
              "Die Geräte werden auf die tatsächliche Verlustleistung des Schranks ausgelegt.")]),
    page("produkte-serverraum-klimatisierung", "Serverraum-Klimatisierung", "2026-01-09",
         "Serverraum-Klimatisierung mit Free Cooling hält die IT kühl – effizient und redundant.",
         [sec("Einsatz",
              "Serverräume, Rechenzentren, Container-Rechenzentren und Technikräume.",
              "Indirekte Free-Cooling-Systeme für stabile Luftfeuchte und saubere Trennung der Luftströme."),
          sec("Redundanz",
              "N+1- oder 2N-Auslegung mit Notkühlung, Fernwartung und Lasttests.",
              "Container-Klimatisierung als mobile Erweiterung oder Reserve.")]),
    page("produkte-mobile-klimageraete", "Mobile Klimageräte", "2026-01-10",
         "Mobile Klimageräte liefern Kälte in Stunden – als Miete oder Kauf, ohne Umbau.",
         [sec("Einsatz",
              "Notkühlung nach Ausfall, Überbrückung während Sanierungen, Zusatzkühlung für Lastspitzen.",
              "Leistungen bis in den Containerbereich für große Hallen und Rechenzentren."),
          sec("Miete",
              "Befristete Miete inklusive Wartung und Notdienst. Keine Kapitalbindung, flexible Laufzeiten.",
              "Ideal für das Notkühlkonzept: Geräte stehen auf Abruf bereit.")]),
    page("branchen-produktion", "Branche: Produktion", "2026-01-11",
         "Produktionshallen brauchen Prozessklimatisierung, keine Komfortkühlung. Wir liefern beides.",
         [sec("Anforderungen",
              "Stabile Temperaturen für Prozesse, Kühlung von Maschinen und Schaltschrankklimatisierung für die Steuerung.",
              "Branchenlösungen für Kunststoff, Metall, Elektronik und Lebensmittel."),
          sec("Leistungen",
              "Kältelastberechnung, Kaltwassersätze, Dachklimageräte, Wärmerückgewinnung und Notkühlung.",
              "Wartungsverträge mit kurzen Reaktionszeiten, damit der Stillstand ausbleibt.")]),
    page("branchen-logistik", "Branche: Logistik und Lager", "2026-01-12",
         "Logistikzentren und Hochregallager werden mit Dachklimageräten effizient klimatisiert.",
         [sec("Anforderungen",
              "Große Flächen, keine Stellfläche für Geräte, gleichmäßige Temperaturverteilung bis in die Regale.",
              "Berücksichtigung der Luftschichtung und der Abwärme der Fördertechnik."),
          sec("Leistungen",
              "Dachklimageräte mit textiler Luftverteilung, Kältelastberechnung und Sommer-Checklisten.",
              "Kopplung mit der Brandmeldeanlage gemäß Vorgabe.")]),
    page("branchen-rechenzentren", "Branche: Rechenzentren", "2026-01-13",
         "Rechenzentren brauchen Kälte rund um die Uhr – effizient, redundant, überwacht.",
         [sec("Anforderungen",
              "Serverraum-Klimatisierung mit Free Cooling, enge Temperaturfenster, Notkühlung als Pflicht.",
              "Container-Klimatisierung für mobile oder erweiterte Kapazität."),
          sec("Leistungen",
              "Kaltwassersätze mit Freikühlregister, Redundanzplanung N+1/2N, Fernwartung und Lasttests.",
              "Amortisationsrechnung für Free-Cooling-Nachrüstung an Bestandsanlagen.")]),
    page("branchen-lebensmittel", "Branche: Lebensmittel und Pharma", "2026-01-14",
         "Lebensmittel- und Pharmaproduktion verlangen Kälte, die nie ausfällt – und dokumentiert ist.",
         [sec("Anforderungen",
              "HACCP-konforme Temperaturbereiche, Redundanz, kurze Reaktionszeiten. Für Pharma zusätzlich Reinraumklimatisierung nach ISO 14644 und GMP-Dokumentation.",
              "Trennung von Prozessklimatisierung und Lagerkühlung."),
          sec("Leistungen",
              "Redundante Kältepfade, Notkühlung, Dichtigkeitsprüfungen und dokumentierte Wartung.",
              "Wartungsverträge mit garantierter Reaktionszeit von wenigen Stunden.")]),
    page("ueber-uns", "Über uns", "2026-01-15",
         "Wir sind ein Team aus Kältefachplanern, Monteuren und Servicetechnikern für Industriekälte.",
         [sec("Unsere Haltung",
              "Jede Anlage beginnt mit einer Kältelastberechnung – nicht mit einem Angebot. Jede Anlage wird mit Redundanzkonzept geplant, nicht als Einzelgerät verkauft.",
              "Wir dokumentieren, was wir bauen, und warten, was wir dokumentieren."),
          sec("Qualifikation",
              "Zertifizierte Monteure nach F-Gas-Verordnung, Planung nach EN 378, Erfahrung in Produktion, Logistik und Rechenzentren.")]),
    page("referenzen", "Referenzen", "2026-01-16",
         "Ausgewählte Projekte aus Produktion, Logistik und Rechenzentren. (Demodaten)",
         [sec("Projektbeispiele",
              "Produktionshalle: Kaltwassersatz mit Free Cooling und Wärmerückgewinnung, 24/7-Betrieb.",
              "Logistikzentrum: Dachklimageräte mit adiabatischer Zusatzkühlung für 12.000 Quadratmeter.",
              "Serverraum: N+1-Klimatisierung mit indirektem Free Cooling und Fernwartung."),
          sec("Ihre Referenz",
              "Referenzen auf Anfrage – wir zeigen gerne Anlagen mit laufender Wartung.")]),
    page("kontakt", "Kontakt", "2026-01-17",
         "Sprechen Sie mit uns über Ihre Kältelastberechnung, Ihre Bestandsanlage oder Ihren Wartungsvertrag.",
         [sec("Kontaktwege",
              "Telefon, E-Mail oder das Kontaktformular – wir antworten innerhalb eines Arbeitstages.",
              "Für Notfälle: Notdienst 24/7 mit garantierter Reaktionszeit.")]),
    page("karriere", "Karriere", "2026-01-18",
         "Werden Sie Teil des Teams: Kältefachplaner, Monteure und Servicetechniker gesucht.",
         [sec("Offene Stellen",
              "Kältefachplaner für Kältelastberechnung und Projektdimensionierung.",
              "Servicetechniker für Wartung, Inbetriebnahme und Notdienst.",
              "Monteure für Installation und Umrüstung von Kälteanlagen."),
          sec("Bewerbung",
              "Bewerbungen mit Lebenslauf und Zeugnissen über das Kontaktformular.")]),
    page("faq", "FAQ: Industrie-Klimageräte", "2026-01-19",
         "Die häufigsten Fragen zu Industrie-Klimageräten – kurz beantwortet.",
         [sec("Allgemein",
              "Was kostet die Klimatisierung einer Produktionshalle? Die Kosten hängen von Kältelast, Technologie und Redundanz ab – die Kältelastberechnung liefert die belastbare Zahl.",
              "Wie lange hält ein Kaltwassersatz? Bei dokumentierter Wartung sind 15 bis 20 Jahre realistisch.",
              "Brauche ich eine Genehmigung? Für Anlagen mit fluorierten Kältemitteln greifen die Pflichten der F-Gas-Verordnung."),
          sec("Betrieb",
              "Wie oft muss eine Anlage gewartet werden? Mindestens zweimal jährlich, plus Dichtigkeitsprüfung nach F-Gas-Verordnung.",
              "Was tun bei Kältemittelleckage? Anlage abschalten, Fachbetrieb rufen, Leckage dokumentieren.",
              "Lohnt sich Free Cooling? In Deutschland fast immer – die Amortisationsrechnung zeigt es im Einzelfall.")]),
    page("impressum", "Impressum und Datenschutz", "2026-01-20",
         "Impressum und Hinweise zum Datenschutz. (Demodaten)",
         [sec("Impressum",
              "Industrie-Klima Demo GmbH, Musterstraße 1, 00000 Musterstadt. Vertreten durch: Musterfrau Muster.",
              "Alle Inhalte dieser Demo-Website sind fiktive Beispieldaten."),
          sec("Datenschutz",
              "Diese Demo erhebt keine personenbezogenen Daten. Kontaktformulare existieren nur zu Demonstrationszwecken.")]),
]

# ---------------------------------------------------------------------------
# Renderer
# ---------------------------------------------------------------------------
def wxr_esc(text):
    return html.escape(str(text), quote=False)

def render_item(post, post_id, is_page=False):
    body = render_body(post["intro"], post["sections"])
    post_type = "page" if is_page else "post"
    date = post["date"] + " 09:00:00"
    date_gmt = post["date"] + " 08:00:00"
    pub = datetime.strptime(post["date"], "%Y-%m-%d").strftime("%a, %d %b %Y 09:00:00 +0100")
    creator = "admin"
    slug = post["slug"]

    out = []
    out.append("  <item>")
    out.append("    <title>%s</title>" % wxr_esc(post["title"]))
    out.append("    <link>http://127.0.0.1:8080/%s/%s/</link>" % (post_type, slug))
    out.append("    <pubDate>%s</pubDate>" % pub)
    out.append("    <dc:creator><![CDATA[%s]]></dc:creator>" % creator)
    out.append("    <guid isPermaLink=\"false\">http://127.0.0.1:8080/?p=%d</guid>" % post_id)
    out.append("    <description></description>")
    out.append("    <content:encoded><![CDATA[%s]]></content:encoded>" % body)
    out.append("    <excerpt:encoded><![CDATA[%s]]></excerpt:encoded>" % wxr_esc(post["excerpt"]))
    out.append("    <wp:post_id>%d</wp:post_id>" % post_id)
    out.append("    <wp:post_date>%s</wp:post_date>" % date)
    out.append("    <wp:post_date_gmt>%s</wp:post_date_gmt>" % date_gmt)
    out.append("    <wp:comment_status>open</wp:comment_status>")
    out.append("    <wp:ping_status>open</wp:ping_status>")
    out.append("    <wp:post_name><![CDATA[%s]]></wp:post_name>" % slug)
    out.append("    <wp:status>publish</wp:status>")
    out.append("    <wp:post_parent>0</wp:post_parent>")
    out.append("    <wp:menu_order>%d</wp:menu_order>" % post.get("menu_order", 0))
    out.append("    <wp:post_type>%s</wp:post_type>" % post_type)
    out.append("    <wp:post_password></wp:post_password>")
    out.append("    <wp:is_sticky>0</wp:is_sticky>")
    if not is_page and post["cat"]:
        out.append("    <category domain=\"category\" nicename=\"%s\"><![CDATA[%s]]></category>" % (post["cat"], CATS[post["cat"]]))
    for tag in post["tags"]:
        out.append("    <category domain=\"post_tag\" nicename=\"%s\"><![CDATA[%s]]></category>" % (tag, tag))
    if post.get("max_links"):
        out.append("    <wp:postmeta><wp:meta_key>_elink_max_links</wp:meta_key><wp:meta_value>%d</wp:meta_value></wp:postmeta>" % post["max_links"])
    if post.get("auto_links") is not None:
        out.append("    <wp:postmeta><wp:meta_key>_elink_auto_links</wp:meta_key><wp:meta_value>%d</wp:meta_value></wp:postmeta>" % post["auto_links"])
    out.append("  </item>")
    return "\n".join(out)

def build_wxr():
    lines = []
    lines.append('<?xml version="1.0" encoding="UTF-8" ?>')
    lines.append('<rss version="2.0"')
    lines.append('    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"')
    lines.append('    xmlns:content="http://purl.org/rss/1.0/modules/content/"')
    lines.append('    xmlns:wfw="http://wellformedweb.org/CommentAPI/"')
    lines.append('    xmlns:dc="http://purl.org/dc/elements/1.1/"')
    lines.append('    xmlns:wp="http://wordpress.org/export/1.2/">')
    lines.append('<channel>')
    lines.append('    <title>Demo Import Industrie-Klimageräte</title>')
    lines.append('    <link>http://127.0.0.1:8080</link>')
    lines.append('    <description>Demo-Content: Industrie-Klimageräte (50 Artikel, 20 Seiten)</description>')
    lines.append('    <pubDate>Sat, 15 Aug 2026 10:00:00 +0000</pubDate>')
    lines.append('    <language>de-DE</language>')
    lines.append('    <wp:wxr_version>1.2</wp:wxr_version>')
    lines.append('    <wp:base_site_url>http://127.0.0.1:8080</wp:base_site_url>')
    lines.append('    <wp:base_blog_url>http://127.0.0.1:8080</wp:base_blog_url>')
    lines.append('    <wp:author><wp:author_id>1</wp:author_id><wp:author_login><![CDATA[admin]]></wp:author_login><wp:author_email><![CDATA[e@example.test]]></wp:author_email><wp:author_display_name><![CDATA[Admin]]></wp:author_display_name><wp:author_first_name><![CDATA[]]></wp:author_first_name><wp:author_last_name><![CDATA[]]></wp:author_last_name></wp:author>')
    # Kategorien
    for slug, name in CATS.items():
        lines.append('    <wp:category><wp:term_id>%d</wp:term_id><wp:category_nicename><![CDATA[%s]]></wp:category_nicename><wp:category_parent><![CDATA[]]></wp:category_parent><wp:cat_name><![CDATA[%s]]></wp:cat_name></wp:category>' % (hash(slug) % 100 + 1, slug, name))
    # Tags
    for i, tag in enumerate(TAGS):
        lines.append('    <wp:tag><wp:term_id>%d</wp:term_id><wp:tag_slug><![CDATA[%s]]></wp:tag_slug><wp:tag_name><![CDATA[%s]]></wp:tag_name></wp:tag>' % (200 + i, tag, tag))
    # Items: Seiten zuerst (fuer spaetere Entity-Ziele ist die Reihenfolge egal, IDs sind deterministisch)
    pid = 101
    for pg in PAGES:
        lines.append(render_item(pg, pid, is_page=True))
        pid += 1
    for ps in POSTS:
        lines.append(render_item(ps, pid, is_page=False))
        pid += 1
    lines.append('</channel>')
    lines.append('</rss>')
    return "\n".join(lines) + "\n"

def build_entities_php():
    lines = []
    lines.append("<?php")
    lines.append("/**")
    lines.append(" * Demo-Entities fuer Entity Link Engine importieren.")
    lines.append(" * Aufruf: wp eval-file demo-entities.php --allow-root")
    lines.append(" * Setzt elink_entities_manual (Ziele per Slug aufgeloest) und baut den Index neu.")
    lines.append(" */")
    lines.append("defined( 'ABSPATH' ) || die( 'WP context required.' );")
    lines.append("")
    lines.append("$entities = array();")
    for label, aliases, slug, prio in ENTITIES:
        lines.append("$entities[] = array(")
        lines.append("    'id' => 'demo_%s'," % re.sub(r'[^a-z0-9]', '_', slug))
        lines.append("    'entity_label' => %s," % php_str(label))
        lines.append("    'aliases' => array(%s)," % ", ".join(php_str(a) for a in aliases))
        lines.append("    'target_post_id' => 0,")
        lines.append("    'priority' => %d," % prio)
        lines.append("    '_target_slug' => %s," % php_str(slug))
        lines.append(");")
    lines.append("")
    lines.append(r"foreach ( $entities as $i => $entity ) {")
    lines.append(r"    $post = get_page_by_path( $entity['_target_slug'], OBJECT, array( 'post', 'page' ) );")
    lines.append(r"    if ( ! $post ) {")
    lines.append(r"        $posts = get_posts( array( 'name' => $entity['_target_slug'], 'post_type' => array( 'post', 'page' ), 'post_status' => 'publish', 'numberposts' => 1 ) );")
    lines.append(r"        $post = $posts ? $posts[0] : null;")
    lines.append(r"    }")
    lines.append(r"    if ( ! $post ) {")
    lines.append(r"        echo 'WARN: Ziel nicht gefunden: ' . $entity['_target_slug'] . PHP_EOL;")
    lines.append(r"        unset($entities[ $i ]);")
    lines.append(r"        continue;")
    lines.append(r"    }")
    lines.append(r"    $entities[ $i ]['target_post_id'] = (int) $post->ID;")
    lines.append(r"    unset($entities[ $i ]['_target_slug']);")
    lines.append(r"}")
    lines.append("")
    lines.append("update_option( 'elink_entities_manual', array_values( $entities ) );")
    lines.append("echo 'Entities gesetzt: ' . count( $entities ) . PHP_EOL;")
    lines.append("")
    lines.append(r"$map = new ELE_Entity_Map();")
    lines.append(r"$count = $map->rebuild();")
    lines.append("echo 'Index neu aufgebaut: ' . $count . ' Posts' . PHP_EOL;")
    lines.append("")
    return "\n".join(lines) + "\n"

def php_str(s):
    return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"

def main():
    # demo-content.xml
    xml = build_wxr()
    xml_path = os.path.join(OUT, "demo-content.xml")
    with open(xml_path, "w", encoding="utf-8") as f:
        f.write(xml)

    # demo-entities.php
    php = build_entities_php()
    php_path = os.path.join(OUT, "demo-entities.php")
    with open(php_path, "w", encoding="utf-8") as f:
        f.write(php)

    # DEMO.md
    md = """# Demo-Content: Industrie-Klimageräte

Demodaten fuer Entity Link Engine: 50 Blog-Artikel + 20 Seiten (WXR 1.2, Deutsch).

**Wichtig:** Alle Zahlen in den Texten sind fiktive Beispieldaten. Keine echten Messwerte, keine echten Preise, keine echten Referenzen.

## Import

```bash
# 1) WordPress-Importer aktivieren (einmalig)
wp plugin install wordpress-importer --activate --allow-root

# 2) Content importieren
wp import demo-content.xml --authors=create --allow-root

# 3) Entity-Vokabular setzen + Index aufbauen
wp eval-file demo-entities.php --allow-root

# 4) Engine ueber alle Posts laufen lassen (Beispiel: 8 Posts)
wp eval '\\$e = new ELE_Engine(); foreach (range(1,8) as \\$i) { \\$r = \\$e->run(\\$i); echo \\$i . ": " . count(\\$r["inserted"]) . " links\\n"; }' --allow-root
```

## Was enthalten ist

- **Kategorien:** Planung, Produkte, Kältemittel, Effizienz, Wartung, Sicherheit, Branchen
- **Tags:** kuehlung, rechenzentrum, logistik, produktion, foerderung, saison, smart, lebensmittel, pharma, notfall
- **Manuelles Entity-Vokabular (28 Einträge):** Industrie-Klimageräte, Kaltwassersatz, Schaltschrankklimatisierung, Serverraum-Klimatisierung, Free Cooling, F-Gas-Verordnung, Kältemittel R290, Adiabatische Kühlung, Kältelastberechnung, Dachklimageräte, Mobile Klimageräte, Wartungsvertrag, BAFA-Förderung, EN 378, ATEX-Klimageräte, Wärmerückgewinnung, Kälteleistung, Notkühlung, Container-Klimatisierung, ErP-Richtlinie, CO2-Kältemittel R744, Hybridkühler, Abluft-Wärmepumpe, Reinraumklimatisierung, Kältemittelleckage, Glykol, Pufferspeicher, Prozessklimatisierung
- **Post-Meta-Demos:** einige Artikel mit `_elink_max_links`-Override

## Erwartung nach dem Lauf

- Artikel mit Entitaets-Mentions (z. B. "Kaltwassersatz", "Free Cooling") bekommen automatisch interne Links
- Der Report zeigt ausgehende/eingehende Links und Waisen
- Undo im Editor stellt den Zustand vor dem Lauf wieder her
"""
    md_path = os.path.join(OUT, "DEMO.md")
    with open(md_path, "w", encoding="utf-8") as f:
        f.write(md)

    print("OK: %s" % xml_path)
    print("OK: %s" % php_path)
    print("OK: %s" % md_path)
    print("Posts: %d, Pages: %d, Entities: %d" % (len(POSTS), len(PAGES), len(ENTITIES)))

if __name__ == "__main__":
    main()
