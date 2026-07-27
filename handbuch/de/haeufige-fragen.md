# Häufige Fragen

Kurze Antworten auf die häufigsten Fragen zu Living Handbook. Jede Antwort verweist auf die Seite, auf der die Information ausführlich lebt.

<details>
<summary>Warum wird meine Seite auf der Website nicht angezeigt?</summary>

Fast immer ist es eine von drei Ursachen. Prüfe sie in dieser Reihenfolge: Die Seite hat kein Handbuch zugewiesen. Das Handbuch ist für dich oder deine Testperson nicht sichtbar. Die Seite ist noch ein Entwurf. Details: [Zugriff verstehen](zugriff/zugriff-verstehen.md) und [Erste Seite anlegen](erste-schritte/erste-seite-anlegen.md).

</details>

<details>
<summary>Warum sehen abgemeldete Besucher mein Handbuch nicht?</summary>

Ein neues Handbuch startet mit der Sichtbarkeit „Alle Mitglieder (angemeldet)“. Stelle es auf „Öffentlich“, wenn alle es lesen sollen. Details: [Sichtbarkeit einstellen](zugriff/sichtbarkeit-einstellen.md).

</details>

<details>
<summary>Warum sehen die Handbuch-Seiten in meinem Theme kaputt aus?</summary>

Das Plugin braucht ein Block-Theme und WordPress 6.7 oder neuer. Ein älteres, klassisches Theme kann die Handbuch-Seiten nicht richtig darstellen. Details: [Installation](erste-schritte/installation.md).

</details>

<details>
<summary>Wie lade ich dieses Handbuch in meine eigene Installation?</summary>

Über **Handbuch → Import**, Reiter **App-Handbuch**, mit einem Klick. Das Handbuch liegt im Plugin und passt damit zur installierten Version. Nach einem Plugin-Update lädst du es einfach erneut. Details: [App-Handbuch laden](erste-schritte/app-handbuch-laden.md).

</details>

<details>
<summary>Wie ändere ich die Reihenfolge der Seiten in der Navigation?</summary>

Über die Seiten-Attribute jeder Seite: Eltern-Seite und Reihenfolge. Bei importierten Handbüchern ordnest du stattdessen in den Quelldateien. Dort zählt die Zeile „Reihenfolge“ in den Transport-Metadaten. Details: [Seiten ordnen](erste-schritte/seiten-ordnen.md).

</details>

<details>
<summary>Kann ich eine von GitHub synchronisierte Seite in WordPress bearbeiten?</summary>

Nein, ihr Editor ist gesperrt. Sonst würde der nächste Abgleich deine Änderungen überschreiben. Bearbeite die Markdown-Datei auf GitHub. Oder stelle die Quelle der Seite auf „In WordPress gepflegt“ um. Details: [GitHub-Synchronisation](inhalte/github-synchronisation.md).

</details>

<details>
<summary>Was passiert, wenn ich dieselbe Quelle noch einmal importiere?</summary>

Die bestehenden Seiten werden aktualisiert, nicht verdoppelt. Adresse und Veröffentlichungsstatus bleiben erhalten. Nur ein eingefügter Text-Entwurf erzeugt immer eine neue Seite. Details: [Markdown importieren](inhalte/markdown-importieren.md).

</details>

<details>
<summary>Bedeutet „Prüfung fällig“, dass die Seite falsch ist?</summary>

Nein. Es bedeutet nur: Niemand hat die Seite innerhalb ihres Prüfintervalls bestätigt. Lies die Seite. Stimmt sie noch, setze das Prüfdatum neu. Details: [Der Prüfzyklus](pflege/der-pruefzyklus.md).

</details>

<details>
<summary>Warum sehe ich die Feedback-Knöpfe „War das hilfreich?“ nicht?</summary>

Standardmäßig erscheinen die Knöpfe nur für angemeldete Personen, mit einer Stimme pro Person und Seite. In den [Einstellungen](die-einstellungen.md) lässt sich öffentliches Feedback einschalten; dann stimmen auf öffentlichen Seiten auch Abgemeldete ab. Details: [Feedback auswerten](pflege/feedback-auswerten.md).

</details>

<details>
<summary>Wird beim Löschen des Plugins mein Inhalt gelöscht?</summary>

Nein, standardmäßig bleiben alle Handbücher und Seiten erhalten. Entfernt werden nur die Einstellungen des Plugins. Das vollständige Aufräumen ist eine bewusste Option unter **Handbuch → Einstellungen**. So kostet ein versehentliches Löschen nie das Handbuch. Details: [Die Einstellungen](die-einstellungen.md).

</details>

<details>
<summary>Sendet das Plugin Daten an GitHub oder sonst wohin?</summary>

Nein. Es liest nur die Adressen, die du selbst einträgst, beim Import oder in der Quelle einer Seite. Es sendet nichts nach draußen. Nutzt du weder Import noch Synchronisation, macht das Plugin gar keine externen Anfragen. Details: [GitHub-Synchronisation](inhalte/github-synchronisation.md).

</details>

## Verwandte Seiten

* [Über dieses Handbuch](ueber-dieses-handbuch.md)
* [Erste Schritte](erste-schritte/README.md)

## Transport-Metadaten
* Seitentyp: FAQ
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Überblick
* Zielgruppe: Alle Mitglieder
* Reihenfolge: 9
* Textauszug: Kurze Antworten auf die häufigsten Fragen zu Living Handbook, jede mit Verweis auf die ausführliche Seite.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
