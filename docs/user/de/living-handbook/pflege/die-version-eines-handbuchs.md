# Die Version eines Handbuchs

Jedes Handbuch trägt eine Version, etwa „Handbuch-Version 2.1.14“. Sie zeigt, welcher Stand eines Handbuchs gerade vor dir liegt: auf der Website, in einem Export oder in einem Bündel. Diese Seite erklärt, woraus sie besteht, was sie weiterzählt und wie du sie selbst setzt, auch aus einem Tag auf GitHub.

<details>
<summary>Konzept: Ausgabe und Zähler</summary>

Die Version hat zwei Teile:

* **Die Ausgabe**, im Beispiel „2.1“. Du setzt sie am Handbuch von Hand, oder sie kommt aus einem Tag auf GitHub. Eine neue Ausgabe nimmst du, wenn du einen Stand bewusst freigibst, etwa nach einer Überarbeitung.
* **Der Zähler**, im Beispiel „14“. Ihn führt das Plugin selbst. Er steigt um eins an jedem Tag, an dem sich der veröffentlichte Inhalt des Handbuchs ändert. Mit einer neuen Ausgabe beginnt er wieder bei 0.

Als Änderung zählt, wenn eine Seite veröffentlicht, im Text oder im Titel geändert, zurückgezogen, in ein anderes Handbuch verschoben oder gelöscht wird. Mehrere Änderungen am selben Tag ergeben eine einzige Nummer. Nicht gezählt werden Entwürfe, ein neuer Textauszug, eine andere Reihenfolge und das Bestätigen einer Prüfung: Das ändert nicht, was Leserinnen und Leser lesen.

Die Version ist nicht die Version des Plugins. Die steht, nur für Angemeldete, als „Living Handbook 0.82.0“ in einer eigenen Zeile.

</details>

## Wo die Version steht

* **Auf der Website:** unten auf der Einstiegsseite des Handbuchs und im Footer jeder Seite, für alle Leserinnen und Leser. Ein Datum steht nicht dabei, damit ein Handbuch, das lange stimmt, nicht veraltet wirkt.
* **Im Website-Export:** im Footer jeder Seite, mit dem Stand vom Moment des Exports.
* **Im Markdown-Export:** in `EXPORT.txt` und in der `mkdocs.yml`.
* **Im Bündel:** Ein Import, der dabei ein neues Handbuch anlegt, übernimmt die Version. Ein bestehendes Handbuch zählt einfach weiter.
* **In der Verwaltung:** in der Spalte **Version** der Handbuch-Liste.

## Die Ausgabe von Hand setzen

1. Öffne **Handbuch → Handbücher** und klicke auf den Namen des Handbuchs.
2. Trag im Feld **Ausgabe** die neue Ausgabe ein: eine bis drei Zahlen mit Punkt, etwa `2` oder `2.1`.
3. Klicke auf **Aktualisieren**. Die Version steht jetzt auf `2.1.0`, die nächste Änderung macht daraus `2.1.1`.

## Die Ausgabe aus GitHub nehmen

Hat das Handbuch Seiten von GitHub, kann die Ausgabe aus einem Tag des Repositorys kommen.

1. Wähle am Handbuch unter **Ausgabe kommt** den Eintrag **aus dem GitHub-Tag**. Stammen die Seiten aus mehreren Repositorys, wähle daneben das maßgebende.
2. Klicke auf **Aktualisieren**. Das Plugin sucht den Tag sofort und zeigt darunter, aus welchem Tag die Ausgabe kommt. Das Feld **Ausgabe** ist ab jetzt gesperrt.

Der Tag wird so gefunden:

* **Folgen die Seiten einem Tag**, etwa `…/v2.1/docs/…` in ihrer Adresse, gilt dieser Tag. GitHub wird dafür nicht gefragt.
* **Folgen die Seiten einem Zweig**, etwa `main`, fragt die regelmäßige Synchronisation am Ende jedes Durchgangs nach den Tags des Repositorys. Es gilt der höchste, der wie eine Versionsnummer aussieht: `v2.1` wird zur Ausgabe `2.1`, Tags wie `beta` werden übergangen.

Ein neuer Tag ist eine neue Ausgabe, der Zähler beginnt bei 0. Danach zählt er die Änderungen seit diesem Tag: „2.1.3“ heißt, seit `v2.1` an drei Tagen geändert.

<details>
<summary>Stolpersteine: Tags für Code und Doku</summary>

* **Ein Tag gilt für das ganze Repository.** Liegt die Doku neben dem Code, springt die Ausgabe auch bei einem Release, das nur den Code betrifft.
* **GitHub antwortet nicht immer.** Ohne Anmeldung erlaubt GitHub einer Website 60 Anfragen pro Stunde, für alle Abrufe zusammen. Bleibt eine Antwort aus, bleibt die bisherige Ausgabe stehen, und das Handbuch zeigt in der Verwaltung einen Hinweis.
* **Zwei Exporte vom selben Tag** können dieselbe Nummer tragen, auch wenn dazwischen etwas geändert wurde.

</details>

## Verwandte Seiten

* [Der Prüfzyklus](der-pruefzyklus.md)
* [Handbuch umziehen](../inhalte/handbuch-umziehen.md)
* [Handbuch als Markdown exportieren](../inhalte/handbuch-als-markdown-exportieren.md)

## Transport-Metadaten
* Seitentyp: Hintergrund / Konzept
* Slug: die-version-eines-handbuchs
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Pflege
* Zielgruppe: Alle Mitglieder
* Eltern-Seite: Pflege
* Reihenfolge: 5
* Textauszug: Woraus die Version eines Handbuchs besteht, was sie weiterzählt, wo sie steht und wie du sie von Hand oder aus einem GitHub-Tag setzt.
* Letzte Aktualisierung: 2026-11-10
* Letzte Prüfung: 2026-11-10
* Prüfintervall: 180 Tage
