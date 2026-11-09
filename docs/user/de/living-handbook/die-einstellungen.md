# Die Einstellungen

Alle Optionen des Plugins auf einen Blick. Du findest sie unter **Handbuch → Einstellungen**; die Seite dort hat sieben Reiter. Gespeichert wird immer nur der Reiter, den du gerade siehst, die übrigen bleiben unberührt.

## GitHub-Sync

**Automatischer Sync:** Wie oft WordPress die [mit GitHub verbundenen Seiten](inhalte/github-synchronisation.md) im Hintergrund aktualisiert. Zur Wahl stehen: aus, stündlich, zweimal täglich, täglich oder wöchentlich (Standard). Unabhängig davon wird eine Seite immer beim Speichern und über den Knopf **Jetzt synchronisieren** abgeglichen. Die Seite zeigt auch an, wann der nächste automatische Abgleich geplant ist.

## Einordnung

**Wonach Seiten eingeordnet werden:** Vier Ankreuzfelder, eines je Gruppe. Neben jedem steht, wie viele Begriffe die Gruppe enthält, darunter in einem Satz, wofür sie da ist:

* **Seitentyp:** Welche Art von Seite das ist, und damit, wie man sie liest: Anleitung, Prozessbeschreibung, Hintergrund, FAQ, Bereichsübersicht. Einer je Seite.
* **Themen:** Worum es inhaltlich geht, in euren eigenen Worten: Onboarding, Rechnungsstellung, Sicherheit. Die Gruppe, die ihr selbst füllt, und die, nach der am meisten gefiltert wird.
* **Verantwortung:** Welche Rolle die Seite aktuell hält: Handbuch-Redaktion, GitHub-Fachperson. Eine Funktion, keine Person, damit die Angabe einen Weggang übersteht.
* **Zielgruppen:** Für wen die Seite geschrieben ist: Alle Mitglieder, Content-Team, Koordination, Technik. Wer sie lesen sollte, nicht wer sie lesen darf; das regelt der Zugriff am Handbuch.

Ab Werk sind alle vier eingeschaltet. Nutzt dein Team nur Themen, schalte die anderen drei ab, dann verschwinden sie überall: die Spalte und der Filter in der Seitenliste, die Facette auf der Einstiegsseite, das Abzeichen auf Seite und Karte, das Feld in der Editor-Seitenleiste und, bei der Verantwortung, die Zeile „Verantwortliche Rolle“ in der Fußzeile mit den Seitenangaben. Auch ein Import liest die zugehörige Zeile nicht mehr.

**Gelöscht wird dabei nichts.** Die Begriffe bleiben bestehen, die Seiten behalten ihre Zuordnung, und sobald du eine Gruppe wieder einschaltest, ist alles unverändert da. Auch ein Bündel-Export nimmt weiterhin alle vier mit, damit beim Umzug auf eine andere Website nichts verloren geht, was diese hier nur ausblendet.

Ausführlicher, mit Beispielen und Stolpersteinen: [Einordnung und Rollen pflegen](pflege/schlagworte-und-rollen.md).

Das Handbuch selbst steht nicht in dieser Liste und lässt sich nicht abschalten. An ihm hängt der Zugriff, und eine Seite ohne Handbuch bleibt im Frontend unsichtbar.

## Lernpfade

**Lernpfade:** Ein Häkchen, **Lernpfade in dieser Handbuch-Installation verwenden**. Standardmäßig aus. Ein Lernpfad ist eine geordnete Auswahl von Seiten, die es im Handbuch schon gibt, für das Onboarding oder für eine Pflichtlektüre. Ist das Häkchen gesetzt, erscheint im Handbuch-Menü der Eintrag **Lernpfade**. Der Stand einer lesenden Person wird in dieser ersten Etappe nur in ihrem Browser gemerkt, es werden also keine Personendaten gespeichert. Ausschalten blendet alles wieder aus und löscht nichts. Wie du einen Pfad anlegst, steht unter [Lernpfade anlegen](inhalte/lernpfade-anlegen.md).

## Darstellung

**Schriftgröße:** Ein Prozentwert für die Schrift, die das Plugin selbst setzt: Navigation, Inhaltsverzeichnis, Abzeichen, Kacheln und die Metadaten-Fußzeile. Der Text einer Seite bleibt unberührt, der gehört deinem Theme. 100 Prozent sind 16 Pixel, die Größe, für die das Plugin gestaltet ist. Setzt dein Theme größere Schrift, wirkt das Handbuch daneben klein; dann hilft ein Wert um 120 bis 130 Prozent. Alle Größen verschieben sich gemeinsam, ihr Verhältnis zueinander bleibt erhalten.

**Elf Farbfelder:** Fläche, Text auf der Fläche, Akzent, je Hintergrund und Text für das Themen- und das Zielgruppen-Badge, dazu die vier Prüfstatus-Farben (Geprüft, Prüfung fällig, Prüfung überfällig, Nicht geprüft). Leer heißt: das Theme entscheidet. So wird das Plugin ausgeliefert, und so ist es gedacht. Fülle ein Feld nur dort aus, wo dein Theme daneben liegt, etwa weil seine Farbwerte nicht zu dem passen, was es tatsächlich anzeigt, oder weil der Kontrast zu schwach ist. Die Farbauswahl bietet dir die Palette deines Themes an, und mit **Löschen** kommst du zum Theme zurück. Die Schriftfarbe auf farbig gefüllten Knöpfen musst du nicht wählen: Das Plugin nimmt Schwarz oder Weiß, je nachdem, was auf deiner Akzentfarbe besser lesbar ist.

Eine Seite trägt bis zu drei dieser kleinen Abzeichen, und sie sind mit Absicht farblich unterscheidbar: Der **Seitentyp** nimmt die Akzentfarbe, das **Thema** und die **Zielgruppe** je ihr eigenes Paar. Wenn du also nur den Hintergrund des Themen-Badges änderst, färbt sich genau ein Abzeichen, das ist kein Fehler. Den Seitentyp änderst du über den Akzent.

**Eigenes CSS:** Gestaltungsregeln, die nur auf den Handbuch-Seiten laden. Sie werden mit dem Plugin gespeichert und beim Löschen des Plugins mit entfernt. Eigenes CSS gewinnt gegen die Farbfelder darüber, du kannst also beides mischen. Wie du damit die Farben änderst, zeigt [Gestaltung anpassen](oberflaeche/gestaltung-anpassen.md). Beispiele stehen direkt auf der Einstellungs-Seite im Reiter **Hilfe** oben rechts.

## Feedback

**Öffentliches Feedback:** Standardmäßig aus. Ist es an, sehen auch abgemeldete Besucherinnen und Besucher die Frage „War das hilfreich?“ auf öffentlichen Seiten und können abstimmen. Zum Schutz der Privatsphäre wird eine solche Stimme keiner Person zugeordnet: kein Cookie, keine IP, nichts anderes Persönliches. Dafür gibt es dort keine Begrenzung auf eine Stimme, dieselbe Person kann nach dem Neuladen erneut abstimmen. Eine Obergrenze von 200 anonymen Stimmen je Seite und Stunde hält bloß Skripte davon ab, den Zähler zu füllen. Auf internen Seiten stimmen unabhängig davon nur angemeldete Personen ab, je eine Stimme. Wie du die Stimmen auswertest und zurücksetzt, steht unter [Feedback auswerten](pflege/feedback-auswerten.md).

**Suchen ohne Treffer:** Standardmäßig an. Findet eine Suche in einem Handbuch keine Seite, zählt das Plugin den Suchbegriff, damit die Redaktion sieht, wonach Leserinnen und Leser vergeblich suchen. Gespeichert werden nur der Begriff in Kleinbuchstaben, das Handbuch, die Kalenderwoche und ob die Suche von der Website oder von einem KI-Werkzeug kam: kein Konto, keine IP, keine Uhrzeit, kein Cookie. Suchen mit gewähltem Filter zählen nicht. Nach 90 Tagen und beim Löschen des Plugins werden die Zählungen gelöscht. Ausgewertet werden sie über ein [KI-Werkzeug](zugriff/ki-werkzeug-anbinden.md).

## Zugriff

**Seite bei fehlendem Zugriff:** Wohin eine angemeldete Person kommt, die ein Handbuch öffnet, das sie nicht lesen darf. Standard ist die eingebaute Meldung (**Eingebaute Meldung verwenden**): „Kein Zugriff auf dieses Handbuch“, mit dem Namen der angemeldeten Person. Wähle hier eine eigene Seite, wenn du in eigenen Worten erklären willst, wer den Zugriff vergibt, etwa mit einem Kontaktformular. Nimm dafür eine gewöhnliche WordPress-Seite. Wählst du eine Handbuch-Seite oder einen Lernpfad, erscheint beim Speichern eine Fehlermeldung, und die Einstellung bleibt leer. Abgemeldete Besucherinnen und Besucher gehen weiterhin zur Anmeldung und danach auf die gewünschte Adresse.

## Deinstallation

**Beim Löschen des Plugins:** Standardmäßig bleiben beim Löschen alle Handbücher und Seiten erhalten. Entfernt werden nur die Einstellungen und Zwischenspeicher des Plugins. Ein versehentliches Löschen kostet dich so nie das Handbuch. Erst wenn du hier das Häkchen **„Auch alle Handbuch-Seiten, Handbücher und ihre Daten löschen“** setzt, räumt das Löschen alles weg, was das Plugin angelegt hat, auch im Website-Editor bearbeitete Templates. Die Dateien in der Mediathek bleiben aber stehen, auch die Bilder und Dateien, die zu Handbuch-Seiten hochgeladen wurden. Brauchst du sie nicht mehr, löschst du sie in der Mediathek.

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
* Textauszug: Alle Optionen des Plugins auf einen Blick: automatischer GitHub-Sync, Einordnung, Lernpfade, Schriftgröße, Farben und eigenes CSS, öffentliches Feedback, Suchen ohne Treffer, Seite bei fehlendem Zugriff und das Verhalten bei der Deinstallation.
* Letzte Aktualisierung: 2026-11-09
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
