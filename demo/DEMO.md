# Demo-Content: Industrie-Klimageräte

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
wp eval '\$e = new ELE_Engine(); foreach (range(1,8) as \$i) { \$r = \$e->run(\$i); echo \$i . ": " . count(\$r["inserted"]) . " links\n"; }' --allow-root
```

## Was enthalten ist

- **Kategorien:** Planung, Produkte, Kältemittel, Effizienz, Wartung, Sicherheit, Branchen
- **Tags:** kuehlung, rechenzentrum, logistik, produktion, foerderung, saison, smart, lebensmittel, pharma, notfall
- **Manuelles Entity-Vokabular (28 Einträge):** Industrie-Klimageräte, Kaltwassersatz, Schaltschrankklimatisierung, Serverraum-Klimatisierung, Free Cooling, F-Gas-Verordnung, Kältemittel R290, Adiabatische Kühlung, Kältelastberechnung, Dachklimageräte, Mobile Klimageräte, Wartungsvertrag, BAFA-Förderung, EN 378, ATEX-Klimageräte, Wärmerückgewinnung, Kälteleistung, Notkühlung, Container-Klimatisierung, ErP-Richtlinie, CO2-Kältemittel R744, Hybridkühler, Abluft-Wärmepumpe, Reinraumklimatisierung, Kältemittelleckage, Glykol, Pufferspeicher, Prozessklimatisierung
- **Post-Meta-Demos:** einige Artikel mit `_ele_max_links`-Override

## Erwartung nach dem Lauf

- Artikel mit Entitaets-Mentions (z. B. "Kaltwassersatz", "Free Cooling") bekommen automatisch interne Links
- Der Report zeigt ausgehende/eingehende Links und Waisen
- Undo im Editor stellt den Zustand vor dem Lauf wieder her
