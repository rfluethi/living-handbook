# Grundlagen guter Handbuch-Seiten

Diese Seite beantwortet die Frage: **Was macht eine Handbuch-Seite gut, unabhängig vom Werkzeug?** Sie richtet sich an alle, die Seiten schreiben oder prüfen. Wie du die Seite technisch anlegst, steht auf [Inhalte schreiben](inhalte-schreiben.md).

## Das Grundproblem: zwei Zielgruppen, ein Dokument

Ein Handbuch bedient zwei Gruppen mit gegensätzlichen Bedürfnissen. Neue Leute wollen lernen, verstehen und Zusammenhänge aufbauen. Erfahrene wollen schnell finden und schnell erledigen.

Schreibst du für beide getrennt, entstehen zwei Handbücher, die auseinanderdriften und doppelt gepflegt werden müssen. Die Antwort ist **ein** Dokument, das den Pflichtweg kurz und sichtbar hält und die Tiefe in Aufklappbereichen versteckt. Wer schnell nachschlägt, sieht sie nicht. Wer lernen will, klappt sie auf.

## Sechs Regeln, die tragen

**Eine Seite, ein Hauptzweck.** Jede Seite ist entweder Anleitung, Erklärung oder Nachschlagewerk, nie alles gleichzeitig. Die Frage «Warum machen wir das so?» gehört nicht neben die Schritte, sondern in einen Aufklappbereich oder auf eine eigene Seite. Das Muster stammt aus dem [Diátaxis-Framework](https://diataxis.fr/).

**Aktion vor Theorie.** Eine Anleitung beginnt mit dem ersten Schritt, nicht mit einem Absatz über den Hintergrund. Das Warum folgt darunter, wenn es gebraucht wird.

**Tiefe in Aufklappbereiche.** Pflichtinformationen stehen oben und sichtbar, Begründungen und Detailwissen darunter zugeklappt. Gib jedem Aufklappbereich einen sprechenden Titel nach dem Muster «Kategorie: worum es geht», etwa «Hintergrund: Wozu Prüfintervalle dienen». Ein Titel wie «Weitere Informationen» sagt niemandem, ob sich das Aufklappen lohnt. Das Prinzip heisst [Progressive Disclosure](https://www.nngroup.com/articles/progressive-disclosure/).

**Eine Quelle pro Information.** Jede Angabe steht an genau einer Stelle, alle anderen Stellen verlinken dorthin. Sonst musst du bei einer Änderung zwei Stellen finden, und eine davon wird vergessen.

**Nicht duplizieren, verlinken.** Fremde Dokumentationen verlinkst du, statt sie abzuschreiben. Beschreibe nur, was bei euch anders ist.

**Zuständig ist eine Rolle, keine Person.** Schreibe «Die Handbuch-Redaktion gibt frei», nicht «Anna gibt frei». Bei einem Wechsel musst du sonst das ganze Handbuch durchsuchen. Living Handbook hat dafür das Feld «Verantwortliche Rolle» auf jeder Seite.

<details>
<summary>Beispiel: Wie die Regeln an einer Seite zusammenwirken</summary>

Angenommen, du schreibst «So legst du eine Aufgabe an». Die Seite ist reine Anleitung. Sie beginnt mit dem ersten Klick statt mit Theorie. Die Begründung «Warum wir Aufgaben so führen» steht zugeklappt darunter und verlinkt auf die Konzeptseite, statt sie zu wiederholen. Wie das benutzte Werkzeug grundsätzlich funktioniert, verlinkst du. Zuständig ist eine Rolle. Unten stehen Prüfdatum und Verantwortung. Ändert sich der Ablauf, ziehst du genau diese Seite im selben Arbeitsgang nach.

</details>

## Aufbau einer Seite

Halte dich an dieselbe Reihenfolge auf jeder Seite, dann finden sich Leserinnen und Leser ohne nachzudenken zurecht:

1. **Titel und ein Einleitungssatz.** Der Satz sagt, was die Seite leistet und für wen. Er eignet sich zugleich als Textauszug für die Übersichts-Kacheln und die Suche.
2. **Der Hauptteil,** gegliedert mit Überschriften ab Ebene 2. Daraus baut sich das Verzeichnis «Auf dieser Seite» von selbst.
3. **Aufklappbereiche** mit Hintergrund, Voraussetzungen und Stolpersteinen.
4. **Verwandte Seiten** als Liste am Ende.
5. Bei Bedarf ein **Seiten-Glossar**, das die Fachbegriffe genau dieser Seite erklärt. Ein zentrales Glossar für das ganze Handbuch veraltet schneller, als es gepflegt wird.

## Aktualität sichtbar machen

Eine Handbuchseite ohne Datum ist wertlos, weil niemand weiss, ob sie noch stimmt oder nur vergessen wurde. Living Handbook zeigt deshalb auf jeder Seite, wann sie zuletzt geändert und zuletzt geprüft wurde und wer dafür zuständig ist.

Setze das Prüfdatum auch dann neu, wenn du inhaltlich nichts geändert hast. «Ich habe das gelesen und es stimmt noch» ist eine Aussage, und sie ist die wertvollste, die eine Handbuchseite tragen kann. Wie der Prüfzyklus arbeitet, steht auf [Der Prüfzyklus](../pflege/der-pruefzyklus.md).

Und die Regel dahinter: Ändert sich ein Ablauf oder ein Werkzeug, ziehst du die betroffene Seite im selben Arbeitsgang nach. Ein Handbuch, das der Wirklichkeit hinterherläuft, verliert das Vertrauen seiner Leserinnen und Leser schneller, als es Inhalte aufbaut.

## Verwandte Seiten

* [Inhalte schreiben](inhalte-schreiben.md)
* [Erste Seite anlegen](../erste-schritte/erste-seite-anlegen.md)
* [Der Prüfzyklus](../pflege/der-pruefzyklus.md)

## Seiten-Glossar

| Begriff | Definition |
|---|---|
| Aufklappbereich | Abschnitt, der zugeklappt beginnt und sich auf Klick öffnet. In Markdown ein `<details>`-Element. |
| Diátaxis | Strukturansatz für Dokumentation, der Anleitung, Erklärung, Nachschlagewerk und Lernpfad als getrennte Seitentypen behandelt. |
| Progressive Disclosure | Gestaltungsprinzip, das nur die nötigen Informationen zeigt und die Tiefe auf Abruf bereithält. |
| Rolle | Funktionsbezeichnung im Team, der Verantwortlichkeiten zugeordnet sind, statt einer konkreten Person. |

## Transport-Metadaten
* Seitentyp: Hintergrund / Konzept
* Slug: grundlagen-guter-seiten
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Inhalte
* Zielgruppe: Inhalts-Erstellende
* Eltern-Seite: Inhalte
* Reihenfolge: 0
* Textauszug: Was eine Handbuch-Seite gut macht, unabhängig vom Werkzeug: ein Hauptzweck pro Seite, Aktion vor Theorie, Tiefe in Aufklappbereichen, eine Quelle pro Information.
* Letzte Aktualisierung: 2026-08-30
* Letzte Prüfung: 2026-08-30
* Prüfintervall: 365 Tage
