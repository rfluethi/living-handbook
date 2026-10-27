# Lernpfade anlegen

Jemand fängt neu an, und du willst nicht sagen «lies mal das Handbuch». Ein Lernpfad ist die Antwort darauf: eine geordnete Auswahl von Seiten, die es in deinem Handbuch schon gibt, mit einem Weiter-Knopf von einer zur nächsten. Aus «lies das Handbuch» wird «arbeite diese neun Seiten in dieser Reihenfolge durch».

<details>
<summary>Konzept: Warum das kein Kurssystem ist</summary>

Ein Lernpfad legt eine Reihenfolge über Seiten, die ohnehin gepflegt werden. Er kopiert nichts, er erfindet keine zweite Ablage für Inhalte, und eine Lektion bleibt eine ganz gewöhnliche Handbuchseite: dieselbe Adresse, dieselbe Navigation, derselbe Prüfstatus, dieselben Zugriffsregeln.

Was dieses Modul bewusst nicht ist: ein Lern-Management-System. Es gibt keine eigenen Kurstexte, keine Prüfungen, keine Noten, keine Zertifikate. Wer das braucht, ist mit einem der großen LMS-Plugins besser bedient. Was ein LMS umgekehrt nicht kann: wissen, wann eine Seite zuletzt geändert und zuletzt geprüft wurde. Genau daran wird die nächste Etappe anknüpfen.

</details>

## Einschalten

Lernpfade sind ab Werk aus. Unter **Handbuch → Einstellungen → Lernpfade** setzt du das Häkchen. Danach erscheint im Handbuch-Menü der Eintrag **Lernpfade**.

Ausschalten blendet alles wieder aus und löscht nichts. Deine Pfade, ihre Texte und ihre Lektionslisten bleiben stehen und sind unverändert da, sobald du das Häkchen wieder setzt.

## So geht es

1. Öffne **Handbuch → Lernpfade → Neuer Lernpfad**.
2. Gib ihm einen **Titel** («Onboarding Redaktion»), wähle rechts das **Handbuch**, zu dem er gehört, **speichere einmal und lade die Seite neu**. Erst danach weiß der Pfad, in welchem Handbuch er nach Lektionen suchen soll, und erst nach dem Neuladen zeigt der Kasten **Lektionen** das Suchfeld.
3. Schreib in den Textbereich ein paar Sätze: für wen der Pfad gedacht ist, was am Ende sitzen soll, wie lange es etwa dauert. Dieser Text ist die Einstiegsseite.
4. Im Kasten **Lektionen** tippst du in das Suchfeld. Angeboten werden veröffentlichte Seiten dieses Handbuchs. Ein Klick auf einen Treffer hängt ihn unten an die Liste an.
5. Mit **Nach oben** und **Nach unten** bringst du die Liste in die richtige Reihenfolge, mit **Entfernen** fliegt eine Lektion wieder raus. Die Knöpfe funktionieren mit Maus und Tastatur gleichermaßen.
6. **Veröffentlichen.**

## Was die Lesenden sehen

Auf der Einstiegsseite des Pfads steht dein Text, darunter die nummerierte Liste der Lektionen und ein Knopf **Start**. Auf jeder Lektion erscheint über dem Titel eine schmale Leiste: der Name des Pfads, «Lektion 3 von 9», und die Wege zur vorherigen und zur nächsten Seite.

Wer schon gelesen hat, sieht die erledigten Lektionen abgehakt, oben steht «3 von 9 Lektionen gelesen», und aus **Start** wird **Weiterlesen**, das direkt zur ersten offenen Lektion führt.

Gefunden werden die Pfade auf der Einstiegsseite ihres Handbuchs: dort stehen sie zuoberst, mit der Anzahl Lektionen. Hat ein Handbuch keine Pfade, ändert sich an der Seite nichts.

> **Der Stand steckt im Browser, nicht auf dem Server.** Diese erste Etappe speichert nichts über einzelne Personen: keine Tabelle, kein Benutzerfeld, kein Cookie. Wer die Browserdaten löscht oder das Gerät wechselt, fängt bei der Anzeige von vorne an. Der Pfad selbst bleibt natürlich. Als Nachweis, wer was gelesen hat, taugt diese Etappe deshalb nicht, und die Oberfläche sagt das den Lesenden auch.

## Zugriff

Ein Lernpfad gehört zu genau einem Handbuch und ist so sichtbar wie dieses Handbuch. Für ein internes Handbuch heißt das: wer das Handbuch nicht lesen darf, sieht auch den Pfad nicht, weder in der Liste noch über seine Adresse.

Lektionen laufen durch dieselbe Prüfung. Eine Seite, die jemand nicht sehen darf, erscheint für diese Person weder in der Liste noch im Zähler. Der Pfad ist damit für zwei Personen unterschiedlich lang, und das ist Absicht: die Alternative wäre, im Zähler zu verraten, wie viel jemandem fehlt.

Einen Pfad im Entwurf sehen nur Personen, die ihn bearbeiten dürfen. In der Vorschau eines Lernpfad-Entwurfs zeigen die Lektionen die Pfadleiste, du kannst den Weg also vor dem Veröffentlichen durchklicken. Lernende sehen Entwürfe nicht.

## Wenn sich das Handbuch ändert

Am Pfad ist nichts nachzuführen. Gespeichert sind Verweise und eine Reihenfolge, alles andere wird beim Lesen ermittelt. Eine Lektion fällt automatisch aus Liste, Nummerierung und Weiter-Knopf, sobald sie gelöscht, in den Entwurf zurückgesetzt oder in ein anderes Handbuch verschoben wird. Umbenannte oder verschobene Seiten erscheinen einfach mit ihrem neuen Titel.

Was der Pfad in dieser Etappe **nicht** tut: melden, dass sich eine Lektion wesentlich geändert hat und jemand sie nochmals lesen sollte. Das ist die zweite Etappe.

## Grenzen dieser Etappe

* Kein Fortschritt auf dem Server, also kein Nachweis und keine Übersicht, wer wie weit ist.
* Ein Pfad kann nur Seiten aus seinem eigenen Handbuch enthalten.
* Höchstens 200 Lektionen je Pfad.
* Keine Wissensfragen und keine Abzeichen.

## Beim Umzug auf eine andere Website

Beim [Umzug eines Handbuchs](handbuch-umziehen.md) reisen seine Lernpfade im Bündel mit. Auf der Ziel-Website zeigen die Lektionen auf die importierten Seiten. Dabei gilt:

* Mit dem ganzen Handbuch reisen alle Pfade mit, auch Entwürfe und Pfade ohne Lektionen.
* Exportierst du nur einen Bereich, behält ein Pfad nur die Lektionen aus diesem Bereich. Ein Pfad ohne Lektion im Bereich bleibt zurück.
* Eine Lektion, die das Bündel nicht enthält, fällt weg. Der Import weist darauf hin.
* Gibt es den Pfad auf der Ziel-Website schon, gilt für ihn dieselbe Wahl wie für die Seiten: überspringen, aktualisieren oder neu anlegen.
* Ist das Lernpfad-Modul auf der Ziel-Website aus, werden die Pfade trotzdem importiert. Sie erscheinen, sobald du es einschaltest.
* Importierst du das Bündel als gewöhnliche WordPress-Seiten, kommen keine Pfade mit.

## Verwandte Seiten

* [Inhalte schreiben](inhalte-schreiben.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Slug: lernpfade-anlegen
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Inhalte
* Zielgruppe: Alle Mitglieder
* Eltern-Seite: Inhalte
* Reihenfolge: 6
* Textauszug: Eine geordnete Auswahl bestehender Handbuchseiten für Onboarding und Pflichtlektüre, mit Weiter-Navigation und einem Stand, den der Browser sich merkt.
* Letzte Aktualisierung: 2026-10-27
* Letzte Prüfung: 2026-08-14
* Prüfintervall: 180 Tage
