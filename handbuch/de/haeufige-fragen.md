# Häufige Fragen

Kurze Antworten auf die Fragen, die beim Arbeiten mit Living Handbook am häufigsten auftauchen. Jede Antwort verweist auf die Seite, auf der die Information ausführlich lebt.

<details>
<summary>Warum wird meine Seite im Frontend nicht angezeigt?</summary>

Fast immer eine von drei Ursachen, in dieser Reihenfolge prüfen: Die Seite hat kein Handbuch zugewiesen, das Handbuch ist für dich (oder deine Testperson) nicht sichtbar, oder die Seite ist noch ein Entwurf. Details: [Zugriff verstehen](zugriff/zugriff-verstehen.md) und [Erste Seite anlegen](erste-schritte/erste-seite-anlegen.md).

</details>

<details>
<summary>Warum sehen abgemeldete Besucher mein Handbuch nicht?</summary>

Ein neues Handbuch startet mit der Sichtbarkeit „Alle Mitglieder (angemeldet)“. Stelle es auf „Öffentlich“, wenn alle es lesen sollen. Details: [Sichtbarkeit einstellen](zugriff/sichtbarkeit-einstellen.md).

</details>

<details>
<summary>Warum sehen die Handbuch-Seiten in meinem Theme kaputt aus?</summary>

Das Plugin braucht ein Block-Theme (WordPress 6.7 oder neuer); ein klassisches Theme rendert die Handbuch-Templates nicht. Details: [Installation](erste-schritte/installation.md).

</details>

<details>
<summary>Wie ändere ich die Reihenfolge der Seiten in der Navigation?</summary>

Über die Seiten-Attribute jeder Seite: Eltern-Seite und Reihenfolge. Bei importierten Handbüchern ordnest du stattdessen in den Quelldateien über die Zeile „Reihenfolge“ in den Transport-Metadaten. Details: [Seiten ordnen](erste-schritte/seiten-ordnen.md).

</details>

<details>
<summary>Kann ich eine von GitHub synchronisierte Seite in WordPress bearbeiten?</summary>

Nein, ihr Editor ist gesperrt, damit der nächste Abgleich keine Änderungen überschreibt. Bearbeite die Markdown-Datei im Repository oder stelle die Quelle der Seite auf „In WordPress gepflegt“ um. Details: [GitHub-Synchronisation](inhalte/github-synchronisation.md).

</details>

<details>
<summary>Was passiert, wenn ich dieselbe Quelle noch einmal importiere?</summary>

Die bestehenden Seiten werden aktualisiert statt verdoppelt; Adresse und Veröffentlichungsstatus bleiben erhalten. Nur ein eingefügter Text-Entwurf erzeugt immer eine neue Seite. Details: [Markdown importieren](inhalte/markdown-importieren.md).

</details>

<details>
<summary>Bedeutet „Prüfung fällig“, dass die Seite falsch ist?</summary>

Nein. Es bedeutet nur, dass niemand die Seite innerhalb ihres Prüfintervalls bestätigt hat. Lies die Seite; stimmt sie noch, setze das Prüfdatum neu. Details: [Der Prüfzyklus](pflege/der-pruefzyklus.md).

</details>

<details>
<summary>Warum sehe ich die Feedback-Knöpfe „War das hilfreich?“ nicht?</summary>

Die Knöpfe erscheinen nur für angemeldete Personen, weil jede Stimme einem Konto zugeordnet wird, eine pro Person und Seite. Details: [Feedback auswerten](pflege/feedback-auswerten.md).

</details>

<details>
<summary>Wird beim Löschen des Plugins mein Inhalt gelöscht?</summary>

Nein, standardmäßig bleiben alle Handbücher und Seiten erhalten; entfernt werden nur die Einstellungen des Plugins. Das vollständige Aufräumen ist eine bewusste Option unter **Handbuch → Einstellungen**, damit ein versehentliches Löschen nie das Handbuch kostet. Details: [Entwickler-Dokumentation zum Deinstallieren](https://github.com/rfluethi/living-handbook/blob/main/docs/import-and-sync.md).

</details>

<details>
<summary>Sendet das Plugin Daten an GitHub oder sonst wohin?</summary>

Nein. Es liest nur die Adressen, die du selbst beim Import oder in der Quelle einer Seite einträgst, und sendet nichts nach draußen. Nutzt du weder Import noch Synchronisation, macht das Plugin gar keine externen Anfragen. Details: [GitHub-Synchronisation](inhalte/github-synchronisation.md).

</details>

## Verwandte Seiten

* [Über dieses Handbuch](ueber-dieses-handbuch.md)
* [Erste Schritte](erste-schritte/README.md)

## Transport-Metadaten
* Seitentyp: FAQ
* Reihenfolge: 7
* Textauszug: Kurze Antworten auf die häufigsten Fragen zu Living Handbook, jede mit Verweis auf die ausführliche Seite.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
