# Die Einstellungen

Alle Optionen des Plugins auf einen Blick. Du findest sie unter **Handbuch → Einstellungen**; die Seite dort hat sechs Reiter. Gespeichert wird immer nur der Reiter, den du gerade siehst, die übrigen bleiben unberührt.

## GitHub-Sync

**Automatischer Abgleich:** Wie oft WordPress die [mit GitHub verbundenen Seiten](inhalte/github-synchronisation.md) im Hintergrund aktualisiert. Zur Wahl stehen: aus, stündlich, zweimal täglich, täglich oder wöchentlich (Standard). Unabhängig davon wird eine Seite immer beim Speichern und über den Knopf **Jetzt synchronisieren** abgeglichen. Die Seite zeigt auch an, wann der nächste automatische Abgleich geplant ist.

## Vokabulare

**Vokabulare in Gebrauch:** Vier Ankreuzfelder, eines je Vokabular: Seitentyp, Thema, Verantwortung, Zielgruppe. Alle vier sind eingeschaltet. Nutzt dein Team nur Themen, schalte die anderen drei ab, dann verschwinden sie überall: die Spalte und der Filter in der Seitenliste, die Facette auf der Einstiegsseite, das Abzeichen auf Seite und Karte, und das Feld in der Editor-Seitenleiste. Auch ein Import liest die zugehörige Zeile nicht mehr.

**Gelöscht wird dabei nichts.** Die Begriffe bleiben bestehen, die Seiten behalten ihre Zuordnung, und sobald du ein Vokabular wieder einschaltest, ist alles unverändert da. Auch ein Bündel-Export nimmt weiterhin alle vier mit, damit beim Umzug auf eine andere Website nichts verloren geht, was diese hier nur ausblendet.

Das Handbuch selbst steht nicht in dieser Liste und lässt sich nicht abschalten. An ihm hängt der Zugriff, und eine Seite ohne Handbuch bleibt im Frontend unsichtbar.

## Darstellung

**Schriftgröße:** Ein Prozentwert für die Schrift, die das Plugin selbst setzt: Navigation, Inhaltsverzeichnis, Abzeichen, Kacheln und die Metadaten-Fußzeile. Der Text einer Seite bleibt unberührt, der gehört deinem Theme. 100 Prozent sind 16 Pixel, die Größe, für die das Plugin gestaltet ist. Setzt dein Theme größere Schrift, wirkt das Handbuch daneben klein; dann hilft ein Wert um 120 bis 130 Prozent. Alle Größen verschieben sich gemeinsam, ihr Verhältnis zueinander bleibt erhalten.

**Zehn Farbfelder:** Fläche, Text auf der Fläche, Akzent, je Fläche und Text für das Schlagwort- und das Zielgruppen-Abzeichen, dazu die drei Prüfstatus-Farben. Leer heißt: das Theme entscheidet. So wird das Plugin ausgeliefert, und so ist es gedacht. Fülle ein Feld nur dort aus, wo dein Theme daneben liegt, etwa weil seine Farbwerte nicht zu dem passen, was es tatsächlich anzeigt, oder weil der Kontrast zu schwach ist. Die Farbauswahl bietet dir die Palette deines Themes an, und mit **Löschen** kommst du zum Theme zurück. Die Schriftfarbe auf farbig gefüllten Knöpfen musst du nicht wählen: Das Plugin nimmt Schwarz oder Weiß, je nachdem, was auf deiner Akzentfarbe besser lesbar ist.

Eine Seite trägt bis zu drei dieser kleinen Abzeichen, und sie sind mit Absicht farblich unterscheidbar: Der **Seitentyp** nimmt die Akzentfarbe, das **Schlagwort** und die **Zielgruppe** je ihr eigenes Paar. Wenn du also nur die Schlagwort-Fläche änderst, färbt sich genau ein Abzeichen, das ist kein Fehler. Den Seitentyp änderst du über den Akzent.

**Eigenes CSS:** Gestaltungsregeln, die nur auf den Handbuch-Seiten laden. Sie werden mit dem Plugin gespeichert und beim Löschen des Plugins mit entfernt. Eigenes CSS gewinnt gegen die Farbfelder darüber, du kannst also beides mischen. Wie du damit die Farben änderst, zeigt [Gestaltung anpassen](oberflaeche/gestaltung-anpassen.md). Beispiele stehen direkt auf der Einstellungs-Seite im Reiter **Hilfe** oben rechts.

## Feedback

**Öffentliches Feedback:** Standardmäßig aus. Ist es an, sehen auch abgemeldete Besucherinnen und Besucher die Frage „War das hilfreich?“ auf öffentlichen Seiten und können abstimmen. Zum Schutz der Privatsphäre wird eine solche Stimme keiner Person zugeordnet: kein Cookie, keine IP, nichts anderes Persönliches. Dafür gibt es dort keine Begrenzung auf eine Stimme, dieselbe Person kann nach dem Neuladen erneut abstimmen. Auf internen Seiten stimmen unabhängig davon nur angemeldete Personen ab, je eine Stimme. Wie du die Stimmen auswertest und zurücksetzt, steht unter [Feedback auswerten](pflege/feedback-auswerten.md).

## Zugriff

**Seite ohne Zugriff:** Wohin eine angemeldete Person kommt, die ein Handbuch öffnet, das sie nicht lesen darf. Standard ist die eingebaute Meldung. Wähle hier eine eigene Seite, wenn du in eigenen Worten erklären willst, wer den Zugriff vergibt, etwa mit einem Kontaktformular. Abgemeldete Besucherinnen und Besucher gehen weiterhin zur Anmeldung und danach auf die gewünschte Adresse.

## Deinstallieren

**Beim Löschen des Plugins:** Standardmäßig bleiben beim Löschen alle Handbücher und Seiten erhalten. Entfernt werden nur die Einstellungen und Zwischenspeicher des Plugins. Ein versehentliches Löschen kostet dich so nie das Handbuch. Erst wenn du hier das Häkchen **„Auch alle Handbuch-Seiten, Handbücher und ihre Daten löschen“** setzt, räumt das Löschen wirklich alles weg, auch im Website-Editor bearbeitete Templates.

> **Hinweis:** Diese Einstellungs-Seite können nur Administratorinnen und Administratoren öffnen.

## Verwandte Seiten

* [GitHub-Synchronisation](inhalte/github-synchronisation.md)
* [Gestaltung anpassen](oberflaeche/gestaltung-anpassen.md)

## Transport-Metadaten
* Seitentyp: Tool-Übersicht
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Überblick
* Zielgruppe: Technik
* Eltern-Seite: Living Handbook
* Reihenfolge: 8
* Textauszug: Alle Optionen des Plugins auf einen Blick: automatischer GitHub-Abgleich, Schriftgröße, Farben und eigenes CSS, öffentliches Feedback, Seite ohne Zugriff und das Verhalten beim Deinstallieren.
* Letzte Aktualisierung: 2026-08-05
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
