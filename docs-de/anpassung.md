# Das Frontend anpassen

Das Plugin bringt Standardstile mit und stellt CSS Custom Properties bereit, damit du die Farben an dein Theme anpassen kannst, ohne das Plugin anzufassen. Typografie und Abstände kommen aus deinem Theme. Die Navigation ist ein eigenständiger, aufklappbarer Seitenbaum, den das Plugin rendert; alles, was das Plugin zeigt, ist sein eigenes Markup, gestaltet über die `--lh-*`-Variablen weiter unten.

Leg deine Regeln in das plugin-eigene Feld **Eigenes CSS** unter **Handbuch → Einstellungen**: Es wird nur auf den Handbuch-Seiten geladen, beim Plugin gespeichert und beim Löschen des Plugins mit entfernt. Alternativ nutzt du den Site-Editor unter **Stile → Zusätzliches CSS** oder das Stylesheet deines Themes; beachte aber, dass CSS im Theme nach dem Entfernen des Plugins bestehen bleibt.

## Ohne CSS

**Handbuch → Einstellungen → Darstellung** hat die elf Farben, auf die es ankommt, und eine Schriftgrösse, für den Fall, dass ein Theme daneben liegt: eines, dessen Farbwerte nicht zu dem passen, was es tatsächlich anzeigt, oder eines, dessen Kontrast zu schwach ist. Die Farbauswahl bietet die Palette des Themes als Felder an. Die Einstellungsseite ist in fünf Reiter geteilt, und jeder Reiter ist eine eigene Settings-Gruppe. Das ist nicht kosmetisch: `options.php` läuft über die Gruppe des abgeschickten Formulars und ruft für jede Option darin `update_option()` auf, mit `null` für die, die das Formular nicht mitgeschickt hat. Eine gemeinsame Gruppe über fünf Reiter würde also bei jedem Speichern die vier Reiter leeren, die gerade nicht auf dem Bildschirm sind.

Zwei Farben sind bewusst keine Felder. Die Textfarbe auf einem gefüllten Akzent-Knopf (`--lh-on-accent`) wird aus dem Akzent abgeleitet, Schwarz oder Weiss, je nachdem, was den höheren Kontrast hat. Und das Seitentyp-Abzeichen nimmt den Akzent selbst (`--lh-accent-soft` auf `--lh-accent`). Deshalb färbt sich beim Setzen der Schlagwort-Fläche genau eines der drei Abzeichen unter einer Seite: Sie sind mit Absicht farblich unterscheidbar.

| Feld | Variable | Wo es landet |
| --- | --- | --- |
| Fläche | `--lh-surface` | Karten, Navigation, Inhaltsverzeichnis, Filterleiste, Suchfeld |
| Text auf der Fläche | `--lh-surface-text` | der Text darauf; Linien und Nebentext werden daraus gemischt |
| Akzent | `--lh-accent` | Links, aktuelle Seite, gefüllte Bedienelemente **und das Seitentyp-Abzeichen** |
| Schlagwort-Abzeichen, Fläche und Text | `--lh-badge-bg`, `--lh-badge-text` | nur der Schlagwort-Chip |
| Zielgruppen-Abzeichen, Fläche und Text | `--lh-badge-audience-bg`, `--lh-badge-audience-text` | nur der Chip „Zielgruppe: …" |
| Geprüft, fällig, überfällig | `--lh-ok`, `--lh-due`, `--lh-overdue` | die Chips „Geprüft", „Prüfung fällig" und „Prüfung überfällig" und ihre Punkte |
| Nicht geprüft | `--lh-none` | der Chip „Nicht geprüft" und sein Punkt: eine Seite ohne Prüfdatum, bewusst neutral, weil eine Seite, die noch niemand angeschaut hat, nicht überfällig ist |

Ein leeres Feld heisst: das Theme entscheidet. So wird ausgeliefert, und so ist das Plugin gedacht. Für ein leeres Feld wird nichts ausgegeben. Was gesetzt ist, wird als `--lh-user-*` auf `:root` geschrieben, und das Stylesheet liest jede Variable als `var(--lh-user-x, <Theme-Vorgabe>, <Rückfallwert>)`. Das ergibt drei Ebenen, in dieser Reihenfolge:

1. die Voreinstellungen des Plugins, die dem Theme folgen,
2. die Einstellungsfelder, die ohne Spezifitätskampf gegen die Voreinstellungen gewinnen,
3. selbst geschriebenes CSS, das gegen beides gewinnt, weil es `--lh-x` direkt benennt und zuletzt ausgegeben wird.

**Schriftgrösse** ist ein Prozentwert. Jede Schriftgrösse im Stylesheet ist ein Vielfaches von `--lh-base`, das bewusst nirgends deklariert ist und deshalb auf `1rem` zurückfällt: 100 Prozent ist genau das, womit das Plugin ausgeliefert wird. Die Einstellung schreibt `--lh-base` auf `:root`, 125 Prozent sind `1.25rem`, aus 16px werden also 20px, und alle Grössen verschieben sich mit, in dem Verhältnis, in dem sie aufeinander abgestimmt sind. Das zählt bei einem Theme, dessen eigener Text nicht 16px gross ist: Das Plugin rechnet in `rem` und ignoriert damit die Schriftgrösse des Themes, wodurch es daneben klein wirken kann. Du kannst `--lh-base` auch selbst setzen, in jeder Einheit:

```css
body { --lh-base: 20px; }
```

Die Grösse fasst den Text einer Seite bewusst nicht an. Der gehört deinem Theme, und das Plugin hat ihn nicht zu skalieren.

## Farben und ein paar Grössen

Die Custom Properties sind auf den Frontend-Wrappern des Plugins deklariert. Am schnellsten gestaltest du alles um, indem du sie überschreibst:

```css
.living-handbook-overview,
.living-handbook-entry,
.living-handbook-cards,
.living-handbook-card,
.living-handbook-nav,
.living-handbook-toc,
.living-handbook-meta,
.living-handbook-feedback,
.living-handbook-badge {
	/* Fläche, Text und Akzent greifen auf die Farbvorgaben deines Themes zurück. */
	--lh-surface: var(--lh-user-surface, var(--wp--preset--color--base, #fff));
	--lh-surface-text: var(--lh-user-surface-text, var(--wp--preset--color--contrast, #1d2327));
	--lh-accent: var(--lh-user-accent, var(--wp--preset--color--accent, #2c5f8a));
	--lh-on-accent: var(--lh-user-on-accent, #fff);
	                           /* Text auf einem gefüllten Akzent-Knopf; die Einstellungen leiten ihn ab */

	/* Linien und sekundärer Text werden aus Fläche und Textfarbe gemischt. */
	--lh-border: color-mix(in srgb, var(--lh-surface-text) 14%, transparent);
	--lh-border-strong: color-mix(in srgb, var(--lh-surface-text) 48%, transparent);
	                           /* die stärkere Linie: Tabellenkopf-Linie, Zitatbalken, Rahmen von Eingabefeldern und Umschaltern */
	--lh-muted: color-mix(in srgb, var(--lh-surface-text) 62%, var(--lh-surface));
	--lh-accent-soft: color-mix(in srgb, var(--lh-accent) 12%, var(--lh-surface));
	                           /* eine getönte Akzentfläche: Seitentyp-Badge, Zeilen bei Hover und die aktuelle Zeile */

	/* Die Prüfstatus-Farben bleiben fest. */
	--lh-ok: var(--lh-user-ok, #176e3c);          /* „Geprüft" */
	--lh-due: var(--lh-user-due, #8a5200);        /* „Prüfung fällig" */
	--lh-overdue: var(--lh-user-overdue, #c0392b); /* „Prüfung überfällig" (der Eskalationszustand) */
	/* Dazu --lh-none für den vierten Zustand „Nicht geprüft" (kein Prüfdatum
	   oder kein Intervall), bewusst neutral statt alarmierend. Und dort, wo eine
	   Statusfarbe ohne eigenen Hintergrund direkt auf die Fläche des Themes
	   gezeichnet wird (der Frische-Punkt auf einer Karte, die zwei
	   Fehlermeldungen), gilt nicht die feste Farbe, sondern
	   --lh-ok-on-surface, --lh-due-on-surface, --lh-overdue-on-surface und
	   --lh-none-on-surface: aus der Statusfarbe und der Textfarbe des Themes
	   gemischt, damit sie auch auf dunklem Grund lesbar bleiben. */

	/* Auch die Badge-Chips behalten feste Werte: kleine Aufkleber, die auf jeder
	   Fläche lesbar bleiben müssen. Jeder Prüfstatus-Chip kombiniert seinen
	   Hintergrund mit der passenden Prüfstatus-Farbe von oben als Beschriftung. */
	--lh-badge-text: var(--lh-user-badge-text, #5f6b75);
	                                   /* Beschriftung eines neutralen Badges */
	--lh-badge-bg: var(--lh-user-badge-bg, #eef1f4);
	                                   /* Hintergrund eines neutralen Badges */
	--lh-badge-audience-bg: #f3eafc;   /* Hintergrund des Zielgruppen-Badges */
	--lh-badge-audience-text: #6b3fa0; /* Beschriftung des Zielgruppen-Badges */
	--lh-badge-ok-bg: #e7f6ec;         /* Hintergrund des Badges „Geprüft" */
	--lh-badge-due-bg: #fdf0e0;        /* Hintergrund des Badges „Prüfung fällig" */
	--lh-badge-overdue-bg: #fdecea;    /* Hintergrund des Badges „Prüfung überfällig" */

	--lh-sticky-top: 2rem;     /* Versatz für klebende Navigation und Inhaltsverzeichnis unter einem fixierten Header */
	/* --lh-base steht hier bewusst nicht: Jede Schriftgrösse liest es als
	   var(--lh-base, 1rem). Setz es auf body, um die Schrift des Handbuchs zu skalieren. */
	--lh-nav-top-weight: 700;  /* Schriftstärke des Navigationstitels */
}
```

Der Dunkelmodus folgt deinem Theme automatisch. Weil `--lh-surface`, `--lh-surface-text` und `--lh-accent` auf die Farbvorgaben des Themes zurückgreifen, werden Karten, Navigation und Inhaltsverzeichnis mit einem dunklen Theme, oder einer dunklen Stilvariante, die eine Besucherin wählt, ebenfalls dunkel; Rahmen und sekundärer Text ziehen mit, weil sie aus der Fläche gemischt werden. Themes ohne solche Vorgaben (viele klassische Themes) behalten den hellen Rückfallwert. Willst du eine feste Palette unabhängig vom Theme, setz `--lh-surface`, `--lh-surface-text` und `--lh-accent` im Feld „Eigenes CSS" auf feste Farben, oder nutz die Farbfelder aus dem Abschnitt darüber.

`--lh-sticky-top` steuert den oberen Versatz der klebenden Navigation und des Inhaltsverzeichnisses sowie den Scroll-Versatz beim Sprung zu einer Überschrift. Erhöhe den Wert, wenn dein Theme einen fixierten Header hat. `--lh-nav-top-weight` legt fest, wie fett der Navigationstitel dargestellt wird.

### Ein Hinweis zu den Prüfstatus-Namen

Die vier Prüfstatus-Farben tragen eine Bedeutung. Die Namen der Variablen und Klassen verwenden kurze interne Wörter, die meist zu den Badges passen:

| Badge in der Oberfläche | Variable | Klassen-Modifikator | Bedeutung |
| --- | --- | --- | --- |
| Geprüft | `--lh-ok` | `--ok` | Innerhalb des Prüfintervalls |
| Prüfung fällig | `--lh-due` | `--due` | Das Intervall ist abgelaufen |
| Prüfung überfällig | `--lh-overdue` | `--overdue` | Das doppelte Intervall ist abgelaufen |
| Nicht geprüft | `--lh-none` | `--none` | Kein Prüfdatum oder kein Prüfintervall, bewusst neutral statt alarmierend |

`--lh-ok` ist also die Farbe des Badges „Geprüft" und `--lh-overdue` die des Badges „Prüfung überfällig". Die Namen der Variablen sind intern, die Badges sind das, was deine Leserinnen sehen.

Halte die vier voneinander unterscheidbar, idealerweise nicht allein über den Farbton, denn der Zustand wird auch über die Form eines kleinen Punkts auf den Karten vermittelt.

`--lh-muted` wird für kleinen sekundären Text verwendet. Der Standardwert erfüllt WCAG AA (4,5:1 auf Weiss), prüf also den Kontrast, wenn du ihn aufhellst.

## Nur auf das Handbuch beschränken

Jede Handbuch-Ansicht trägt die Body-Klasse `living-handbook-page`. Nutze sie, um Standardblöcke innerhalb des Handbuchs zu gestalten, ohne den Rest deiner Website anzufassen:

```css
.living-handbook-page .wp-block-quote { border-left-color: var(--lh-accent); }
.living-handbook-page .wp-block-table { font-size: 0.9rem; }
```

## Klassen

Jeder Handbuch-Block bietet ausserdem im Editor unter seinem Bereich **Erweitert** eine **zusätzliche CSS-Klasse** und einen **HTML-Anker**. Die Klasse wird dem Wurzelelement des Blocks hinzugefügt, der Anker wird dessen `id`. So sprichst du eine einzelne Instanz an (zum Beispiel genau einen Navigationsblock) oder verlinkst direkt auf einen Block, ohne die gemeinsamen Klassen unten anzufassen.

### Übersicht und Einstiegsseiten

- `.living-handbook-overview`, `.living-handbook-entry`: die Block-Wrapper. Beide tragen zusätzlich einen Modifikator `--list` oder `--cards`, der die Einstellung Anzeige des Blocks widerspiegelt, zum Beispiel `.living-handbook-entry--list`.
- `.living-handbook-start__search`, `.living-handbook-start__search-field`, `.living-handbook-start__search-label`, `.living-handbook-search__input`: die Suchleiste. Das Attribut `data-button-position` am Formular sagt, wo die Schaltfläche sitzt (`button-outside`, `button-inside`, `no-button`). Ihre eigenen Vorgaben stehen in `:where()`, das keine Spezifität hat: was in den Block-Einstellungen gesetzt wird, gewinnt ohne Kampf, und für eigenes CSS genügt eine einzelne Klasse.
- `.living-handbook-filterform`, `.living-handbook-facet`, `.living-handbook-facet__opt`, `.living-handbook-reset`: die Filterleiste, ihre Gruppen und der Zurücksetzen-Link. Der Kasten (Rahmen, Fläche, Innenabstand, klebender Versatz) steht aus demselben Grund in `:where()`. Die Filterleiste ist seit 0.66.0 ein eigener Block, der Kasten, den ihr früher die Seitenleiste der Einstiegsseite gab (`.living-handbook-aside`, zusammen mit `.living-handbook-layout` entfallen), sitzt deshalb am Formular selbst. Die Spalten der Einstiegsseite kommen aus dem Spalten-Block des Templates.
- `.living-handbook-main`: die Ergebnisspalte, die eingetauscht wird, sobald eine Facette oder die Suche die Liste filtert. Während des Ladens trägt sie `aria-busy="true"`, was die Standardstile zum Abdunkeln nutzen.
- `.living-handbook-entry__h`, `.living-handbook-count`, `.living-handbook-empty`: Abschnittsüberschriften, Ergebniszahl und der Leerzustand.
- `.living-handbook-anchor`: der `#`-Link neben einer Überschrift der Ebenen 2 bis 4. Blende ihn nicht mit `display: none` aus, sonst ist er mit der Tastatur nicht mehr erreichbar; die Standardstile nehmen `opacity` und zeigen ihn bei Hover und Fokus.

### Suche auf der Einzelseite

- `.living-handbook-page-search`: der Wrapper des Suche-Blocks auf einer Einzelseite.
- `.living-handbook-page-search__input`: das Suchfeld (volle Spaltenbreite).
- `.living-handbook-page-search__results`: die Liste der Treffer, die beim Tippen direkt unter dem Feld erscheint; `.living-handbook-page-search__empty` ist der Leerzustand.

### Kartenraster

- `.living-handbook-cards`, mit `--areas` oder `--books`: das responsive Raster.
- `.living-handbook-card`, mit `--area` oder `--book`, dazu `.living-handbook-card__link`, `__title`, `__excerpt`, `__meta`.
- `.living-handbook-card__dot`, mit `--ok`, `--due`, `--overdue` oder `--none`: der Prüfstatus-Punkt. Seine Form variiert je Zustand (gefüllter Kreis, gerundetes Quadrat, Raute, und ein leerer Kreis für „Nicht geprüft"), damit der Status nicht allein über die Farbe erkennbar ist. Gezeichnet wird er direkt auf die Fläche des Themes, er nimmt deshalb `--lh-ok-on-surface`, `--lh-due-on-surface`, `--lh-overdue-on-surface` beziehungsweise `--lh-none-on-surface`.

In der Anzeige als Liste verlieren die Karten ihren Rahmen und werden zu flachen Zeilen; sprich sie über den Modifikator des Elternelements an, zum Beispiel `.living-handbook-entry--list .living-handbook-card`.

### Handbuch-Menü

- `.living-handbook-menu`, `.living-handbook-menu__list`, `.living-handbook-menu__link`: die kompakte Handbuchliste für einen Header.
- `.living-handbook-menu__toggle`: der Knopf, der die Liste auf schmalen Bildschirmen zusammenklappt. Der geöffnete Zustand ist `.living-handbook-menu.is-open`.

### Navigation

Die Navigation ist kein natives `<details>`-Element mehr. Ihre Titelzeile hat dieselbe Form wie jede Zeile mit Unterseiten: links ein Umschalt-Knopf, daneben der Handbuchtitel als gewöhnlicher Link auf die Einstiegsseite. Den kleinen Pfeil-Link zur Startseite gibt es nicht mehr. Gestaltet wird alles vom Plugin über die `--lh-*`-Variablen weiter oben; ein weiteres Plugin ist nicht beteiligt.

- `.living-handbook-navwrap`: umschliesst die Navigation und hält sie linksbündig.
- `.living-handbook-nav`: die umrandete, klebende Navigation. Sie trägt `.living-handbook-nav--tree` (die Anzeige **Menü**, der ganze Baum offen) oder `.living-handbook-nav--accordion` (die Anzeige **Akkordeon**, die Äste klappen zu). Ist die ganze Navigation zugeklappt, kommt `.living-handbook-nav.is-collapsed` dazu.
- `.living-handbook-nav__top`: die Titelzeile mit dem Umschalt-Knopf und dem Handbuchtitel als Link auf die Einstiegsseite. Die Schriftstärke des Titels setzt `--lh-nav-top-weight`.
- `.living-handbook-nav__toggle--all`: der Umschalt-Knopf der Titelzeile, der die ganze Navigation auf- und zuklappt. Blende ihn nicht aus: auf schmalen Bildschirmen ist er die einzige Möglichkeit, die Navigation aus dem Weg zu räumen.
- `.living-handbook-nav__list`, `.living-handbook-nav__sublist`: der Baum und seine verschachtelten Ebenen. Die erste Ebene ist unter dem Titel eingerückt wie jede weitere Ebene.
- `.living-handbook-nav__item`: eine Seite. Sie trägt `.has-children` bei einem Ast, `.is-current` bei der aktuellen Seite und `.is-open` bei einem offenen Ast.
- `.living-handbook-nav__row`: die Zeile innerhalb eines Eintrags, die den Umschalter (oder einen Platzhalter) und den Seitenlink hält.
- `.living-handbook-nav__toggle`, `.living-handbook-nav__spacer`: der Auf- und Zuklapp-Knopf eines Astes und der gleich breite Platzhalter, der die Beschriftungen der Blätter bündig hält.

### Inhaltsverzeichnis

- `.living-handbook-toc`, mit `--desktop` oder `--mobile`: die aufklappbare Box.
- `.living-handbook-toc__summary`, `__list`, `__item`. Der Eintrag des Abschnitts, den du gerade liest, trägt `.is-active`.

### Badges, Metadaten-Fusszeile und Feedback

- `.living-handbook-badges`, `.living-handbook-badge`, mit `--type`, `--audience`, `--ok`, `--due`, `--overdue` oder `--none`.
- `.living-handbook-meta`, `.living-handbook-metagrid`, `.living-handbook-metagrid__item`, `__label`, `__date`. Die Fusszeile ist eine Beschreibungsliste (`dl`), Label und Wert sind `dt` und `dd`.
- `.living-handbook-person`, `__avatar`, `__name`: die verantwortliche Person in der Fusszeile.
- `.living-handbook-feedback`: die Zeile „War das hilfreich?" und ihre Knöpfe.
- `.living-handbook-visually-hidden`: Text, der nur für Screenreader sichtbar ist (zum Beispiel die Prüfstatus-Beschriftung auf einer Karte). Halte ihn visuell verborgen, aber für assistive Technik lesbar.

## Barrierefreiheit: was du nicht entfernen solltest

Die Standardstile enthalten einige Regeln, die es aus Gründen der Barrierefreiheit gibt. Wenn du sie überschreibst, behalte bitte eine gleichwertige Lösung bei.

- **Fokusringe.** Jedes interaktive Element erhält bei `:focus-visible` eine sichtbare Umrandung. Themes entfernen den Browser-Standard häufig, das Plugin stellt ihn wieder her. Wenn du ihn umgestaltest, behalte eine Umrandung bei, die sich klar von deinem Hintergrund abhebt.
- **Reduzierte Bewegung.** Die Hover-Bewegung der Karten und das Einblenden beim Laden werden unter `prefers-reduced-motion: reduce` abgeschaltet, und das Inhaltsverzeichnis springt dann, statt sanft zu scrollen. Wenn du eigene Animationen ergänzt, pack sie in dieselbe Media Query.
- **Der Vergrössern-Knopf.** Ein Bild oder ein Diagramm, das sich vergrössern lässt, steckt in einem echten `<button class="living-handbook-zoom">`, ist also mit der Tastatur erreichbar und auslösbar und sagt an, was es tut. Der Knopf eines Diagramms nimmt die volle Spaltenbreite ein, weil ein Diagramm auf die Breite gezeichnet wird, die es bekommt, und sonst zusammenfällt. Wenn du den Knopf umgestaltest, mach kein gewöhnliches Element daraus und lass die Breite von `.living-handbook-zoom--diagram` in Ruhe.
- **Die Klickflächen.** Die kleinen Bedienelemente behalten eine Mindesthöhe von 24 Pixeln (WCAG 2.5.8). Kleiner werden sie schwer zu treffen, auf einem Touchscreen und für alle mit unruhiger Hand.

```css
@media (prefers-reduced-motion: reduce) {
	.my-custom-animation { transition: none; animation: none; }
}
```

Jeder Block lässt sich zusätzlich über `theme.json` unter `styles.blocks` gestalten.
