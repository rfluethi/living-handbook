# Dateien schützen

Bilder, PDFs und andere Dateien eines Handbuchs, das nicht öffentlich ist, bekommt nur, wer das Handbuch lesen darf. Das gilt auch für jemanden, der die Adresse einer Datei kennt oder weitergegeben bekommt. Diese Anleitung zeigt, wie du prüfst, dass der Schutz auf deinem Server greift.

<details>
<summary>Konzept: Wie der Schutz funktioniert</summary>

Normalerweise liefert der Webserver jede Datei aus `wp-content/uploads` direkt aus, ohne WordPress zu fragen. Living Handbook legt deshalb die Dateien von Seiten aus nicht öffentlichen Handbüchern in einen eigenen Ordner, `wp-content/uploads/living-handbook-protected`, samt allen Bildgrößen. Eine Regel für den Webserver schickt jede Anfrage an diesen Ordner zu WordPress. Das Plugin prüft dann denselben Zugriff wie bei der Seite und gibt die Datei nur heraus, wenn er passt.

* **Wer die Seite lesen darf,** bekommt die Datei.
* **Wer angemeldet ist und die Seite nicht lesen darf,** bekommt eine Fehlermeldung.
* **Wer nicht angemeldet ist** und die Datei direkt öffnet, kommt zur Anmeldung. Ein eingebettetes Bild bleibt für diese Person einfach leer.

Die Datei folgt der Seite, an die sie hochgeladen wurde. Wechselt eine Seite in ein anderes Handbuch oder ändert sich die Sichtbarkeit eines Handbuchs, verschiebt das Plugin die Dateien im Hintergrund und passt die Adressen in den Handbuch-Seiten an. Dateien öffentlicher Handbücher und Dateien, die zu keiner Handbuch-Seite gehören, bleiben, wo sie sind, und sind so schnell wie bisher.

</details>

## Voraussetzungen

* Du bist als Administratorin oder Administrator angemeldet.
* Auf Apache und LiteSpeed braucht es nichts weiter: Das Plugin schreibt die Regel selbst, sofern der Server Regeln in `.htaccess` zulässt.
* Auf nginx muss die Regel in die Serverkonfiguration. Das Plugin zeigt sie dir zum Kopieren. Kannst du die Konfiguration nicht selbst ändern, gib sie deinem Hosting.
* In einem Netzwerk aus mehreren Websites hat jede Website ihren eigenen Ordner und ihre eigene Regel. Auf nginx trägst du den Block jeder Website ein, auf der das Plugin läuft; der Tab **Zugriff** jeder Website zeigt ihren eigenen.

## Schritte

1. **Öffne die Einstellungen.** Gehe zu **Handbuch → Einstellungen** und wähle den Reiter **Zugriff**.
2. **Teste den Schutz.** Klicke unter **Geschützte Dateien** auf **Schutz testen**. Das Plugin ruft eine Testdatei im geschützten Ordner ohne Anmeldung ab.
3. **Lies das Ergebnis.**
	* **Grün, „Der Schutz greift“:** Alles ist eingerichtet.
	* **Rot, „Der Schutz greift nicht“:** Der Ordner gibt seine Dateien an alle heraus. Auf nginx steht darunter die Regel. Füge sie in den `server`-Block deiner Website ein und lade nginx neu. Auf Apache lässt der Server die `.htaccess` nicht gelten. Frag dein Hosting, ob es `AllowOverride` für den Upload-Ordner erlauben kann.
	* **Rot, „Der Ordner ist gesperrt, aber die Dateien kommen nicht an“:** Der Server sperrt den Ordner, lässt aber WordPress nicht antworten. Auf Apache fehlt meist `mod_rewrite`. Niemand sieht die Dateien, auch wer sie sehen dürfte.
	* **Gelb:** Der Test hat die Website nicht erreicht. Manche Server können sich nicht selbst aufrufen. Frag in diesem Fall dein Hosting.
4. **Teste nach jeder Änderung am Server erneut.** Bis der Test grün ist, gilt der Schutz als nicht eingerichtet.
5. **Verschiebe wartende Dateien, wenn du nicht warten willst.** Nach dem Update auf diese Version und nach einem Wechsel der Sichtbarkeit verschiebt das Plugin die Dateien im Hintergrund. Solange Dateien warten, zeigt der Reiter ihre Anzahl und den Button **Jetzt verschieben**.

## Ergebnis

Bilder und Dateien deiner internen Handbücher sind nur noch für die Personen erreichbar, die das Handbuch lesen dürfen. Für sie ändert sich nichts: Bilder erscheinen wie bisher, Links auf Dateien funktionieren.

<details>
<summary>Hinweis: Was du wissen solltest</summary>

* **Eine Datei, ein Handbuch.** Der Schutz folgt der Seite, an die eine Datei hochgeladen wurde. Zeigst du ein geschütztes Bild auch auf einer öffentlichen Seite, sehen es dort nur Personen, die das interne Handbuch lesen dürfen.
* **Weitergegebene Adressen.** Wird eine Datei verschoben, ändert sich ihre Adresse. Eine früher weitergegebene Adresse führt dann ins Leere. Das ist gewollt.
* **Zwischenspeicher und CDN.** Geschützte Dateien kommen mit dem Hinweis, dass nur der Browser sie zwischenspeichern darf. Ein falsch eingestellter Seiten-Cache oder ein CDN kann das trotzdem übergehen. Nimm den Ordner `living-handbook-protected` dort aus.
* **Dateien bei einem anderen Dienst.** Liefert ein Plugin deine Uploads von einem anderen Dienst aus, etwa von einem Cloud-Speicher, schützt der Ordner diese Kopien nicht. Der Reiter **Zugriff** weist darauf hin.
* **Deaktivieren und Löschen.** Ist das Plugin deaktiviert, bleiben die geschützten Dateien gesperrt: Die Regel bleibt, und niemand beantwortet sie. Löschst du das Plugin mit dem Häkchen, das alle Inhalte entfernt, wandern die Dateien vorher zurück in den normalen Ordner, weil die Mediathek stehen bleibt.

</details>

<details>
<summary>Hintergrund: Für die Technik</summary>

Die Regel für nginx, das Verschieben über WP-CLI (`wp living-handbook protect-files`) und die Übergabe der Datei an den Webserver beschreibt die [Entwickler-Dokumentation zum Dateischutz](https://github.com/rfluethi/living-handbook/blob/main/docs/technical/de/living-handbook-technical/dateischutz.md).

</details>

## Verwandte Seiten

* [Zugriff verstehen](zugriff-verstehen.md)
* [Sichtbarkeit einstellen](sichtbarkeit-einstellen.md)
* [Die Einstellungen](../die-einstellungen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Slug: dateien-schuetzen
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Zugriff
* Zielgruppe: Technik
* Eltern-Seite: Zugriff
* Reihenfolge: 4
* Textauszug: Wie Living Handbook die Dateien interner Handbücher schützt und wie du prüfst, dass der Schutz auf deinem Server greift.
* Letzte Aktualisierung: 2026-11-10
* Letzte Prüfung: 2026-11-09
* Prüfintervall: 180 Tage
