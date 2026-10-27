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
<summary>Warum fehlt einer Redakteurin ein Handbuch, auch in der Verwaltung?</summary>

Alle Handbücher lesen nur Administratorinnen und Administratoren. Die Redaktion liest, was ein Handbuch ihr freigibt. Steht ein Handbuch auf „Eingeschränkt“, ohne dass die Rolle Redakteur oder die Person angehakt ist, fehlt es für sie überall: auf der Website, in der Handbuch-Liste, in der Seitenliste und beim Import und Export. Hake die Rolle oder die Person beim Handbuch an. Details: [Sichtbarkeit einstellen](zugriff/sichtbarkeit-einstellen.md).

</details>

<details>
<summary>Warum sehen die Handbuch-Seiten in meinem Theme kaputt aus?</summary>

Das Plugin braucht ein Block-Theme und WordPress 6.8 oder neuer. Ein älteres, klassisches Theme kann die Handbuch-Seiten nicht richtig darstellen. Details: [Installation](erste-schritte/installation.md).

</details>

<details>
<summary>Warum ist eine Unterseite meiner Handbuch-Übersicht nicht erreichbar?</summary>

Vermutlich liegt deine Übersicht noch unter `/handbook/`, wie bei Installationen von vor Version 0.76.0. Unter `/handbook/…` sucht WordPress nach Handbuch-Seiten. Eine gewöhnliche Unterseite der Übersicht, etwa `/handbook/team/`, findet es deshalb nicht. Gib der Übersicht eine andere Adresse, zum Beispiel `/handbooks/`, wie sie neue Installationen ohnehin bekommen. Details: [Die drei Oberflächen](oberflaeche/die-drei-oberflaechen.md).

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

Nein, standardmäßig bleiben alle Handbücher und Seiten erhalten. Entfernt werden nur die Einstellungen des Plugins. Das vollständige Aufräumen ist eine bewusste Option unter **Handbuch → Einstellungen**. Auch sie lässt die Dateien in der Mediathek stehen. So kostet ein versehentliches Löschen nie das Handbuch. Details: [Die Einstellungen](die-einstellungen.md).

</details>

<details>
<summary>Sendet das Plugin Daten an GitHub oder sonst wohin?</summary>

Inhalte deiner Website sendet es nirgendwohin. Zwei Dienste fragt es aber an. GitHub: Beim Import und bei der Synchronisation liest es nur die Adressen, die du selbst einträgst; mitgeschickt wird dabei nur, was in diesen Adressen steht. Und Gravatar: Die Fußzeile einer Handbuch-Seite zeigt Profilbilder, und die lädt WordPress standardmäßig bei Gravatar, also bei jedem Seitenaufruf im Browser der Lesenden. Das schaltest du unter **Einstellungen → Diskussion → Avatare anzeigen** für die ganze Website ab, oder im Block „Handbuch-Seiten-Meta“ mit **Personen anzeigen (Avatar und Name)**. Details: [GitHub-Synchronisation](inhalte/github-synchronisation.md).

</details>

## Verwandte Seiten

* [Living Handbook](README.md)
* [Erste Schritte](erste-schritte/README.md)

## Transport-Metadaten
* Seitentyp: FAQ
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Überblick
* Zielgruppe: Alle Mitglieder
* Eltern-Seite: Living Handbook
* Reihenfolge: 9
* Textauszug: Kurze Antworten auf die häufigsten Fragen zu Living Handbook, jede mit Verweis auf die ausführliche Seite.
* Letzte Aktualisierung: 2026-10-27
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
