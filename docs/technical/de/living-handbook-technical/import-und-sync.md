# Import und GitHub-Sync

Wie Markdown ins Handbuch kommt und wie eine Seite aus einem GitHub-Repository synchron bleibt.

## Der Import-Bildschirm

Unter **Handbuch → Import** hat jede Quelle ihren eigenen Reiter, und alles, was diese Quelle braucht, steht darin: das Feld, ihre Optionen und ihr Import-Knopf. Nur die gewählte Quelle ist sichtbar. So wird ein eingefügter Entwurf nie übergangen, weil noch eine URL in einem anderen Feld steht, und es gibt immer nur einen Knopf. Ein kurzer Abschnitt „Wie der Import funktioniert" zuoberst erklärt die Einzelheiten bei Bedarf.

Die Quellen sind:

1. **Text einfügen**: einen Markdown-Entwurf einfügen, dann **Markdown importieren**.
2. **ZIP-Datei**: ein ZIP mit `.md`-Dateien hochladen, dann **ZIP importieren**. Das ZIP kann eine flache Sammlung von Dateien sein oder ein strukturiertes MkDocs-Projekt (siehe unten).
3. **GitHub**: eine GitHub-URL eingeben, dann **Von GitHub importieren**. Es funktioniert eine einzelne Datei wie ein ganzer Ordner:
   - eine Datei, entweder als `raw.githubusercontent.com`-URL oder als `github.com/.../blob/...`-URL (die Blob-URL wird automatisch in die Raw-Form gebracht);
   - ein Ordner, als `github.com/.../tree/...`-URL. Jede `.md`-Datei unter diesem Ordner wird importiert, Unterordner eingeschlossen, und die Ordnerstruktur wird zur Seitenhierarchie (siehe unten).
4. **Bündel**: ein Bündel hochladen, das auf einer anderen Seite mit dem Plugin exportiert wurde (siehe unten). Verlangt `edit_others_posts`, also Redaktion oder höher.
5. **App-Handbuch**: das eigene Handbuch der App, die mit dem Plugin ausgelieferte Kopie, mit einem Klick laden (siehe unten). Verlangt Bearbeitungsrechte.

Seiten, die ohne Handbuch landen, sind im Frontend unsichtbar, denn der Zugriff ist fail-closed.

**Wie die Umwandlung abläuft:** league/commonmark rendert das Markdown zu HTML, und die eigene Einfüge-Umwandlung von WordPress macht daraus bearbeitbare Blöcke. Ein ```` ```mermaid ````-Codeblock wird zu einem live gerenderten Mermaid-Block, aufklappbare `<details>`-Abschnitte werden zu Details-Blöcken, und Bilder aus einem `assets`-Ordner werden in die Mediathek übernommen. Sobald alle Seiten existieren, wendet ein gemeinsamer Nachbearbeiter die Transport-Metadaten an und löst Eltern-Seiten und interne `.md`-Links auf, sowohl das Linkziel als auch den sichtbaren Linktext.

### Einen Ordner mit Unterordnern importieren

Ein Ordner-Import liest den ganzen Repository-Baum in **einer** Anfrage an die Git-Trees-API, nicht in einer Anfrage pro Ordner. Die nicht angemeldete GitHub-API erlaubt 60 Anfragen pro Stunde, ein Durchlauf mit einer Anfrage je Ordner ginge bei einem Doku-Repository jeder Grösse mittendrin aus.

Die Ordnerstruktur wird zur Seitenhierarchie, und ein Ordner wird zu einer Seite:

- Ein Ordner mit einer **`index.md`** (ersatzweise **`README.md`**) wird durch diese Datei vertreten: sie wird die Seite des Ordners, alles andere im Ordner hängt darunter.
- Ein Ordner **ohne** beides bekommt eine Seite aus seinem Namen, mit dem Block Bereichs-Einträge, listet also auf, was darin steckt. Eine Ebene, die im Repository existiert, aber nicht im Handbuch, würde sonst ein Loch in der Navigation lassen.
- Die **`README.md` des Ordners, auf den du zeigst**, bleibt eine gewöhnliche Seite. Über ihr gibt es keine Ordnerseite, die sie ersetzen könnte.
- Ebenen, die nur als Pfadsegment existieren, werden aufgefüllt, `docs/one/two/three/page.md` ergibt also drei Ebenen und nicht eine.

Höchstens **200 Dateien** werden auf einmal importiert. Ist diese Grenze erreicht, oder ist das Repository zu gross, als dass GitHub seinen Baum in einem Stück zurückgibt, sagt die Ergebnisliste das; importiere die übrigen Unterordner einzeln.

**Ein Ordner-Import läuft in Durchgängen.** Eine einzelne Anfrage würde ein paar hundert Seiten nicht überstehen, der Import hört deshalb nach `IMPORT_BUDGET` Sekunden auf (20, gedeckelt auf 60 Prozent von `max_execution_time`, filterbar über `living_handbook_import_time_budget`), sichert den Rest seiner Arbeitsliste in einem Transient und antwortet mit einer Job-ID; der Import-Bildschirm fragt mit dieser ID nach, bis die Warteschlange leer ist. Er hört zwischen zwei Seiten auf, nie mitten in einer, und ein Job gehört der Person, die ihn gestartet hat.

Das Auflösen der internen Links ist die **zweite Phase desselben Jobs**, kein abschliessender Schritt. Es läuft, wenn jede Seite des Imports existiert, und pausiert nach demselben Budget, mit der Meldung `phase: links`, damit der Bildschirm sagen kann, was er gerade tut. Solange noch Seiten angelegt werden, bleibt ein Link, dessen Ziel noch nicht existiert, unangetastet, statt zu Text zu werden; erst die Schlussphase entscheidet. Sonst behielte eine Seite, die vor ihrem Ziel importiert wurde, für immer einen toten Link, und ob ein Link überlebt, hinge von der Reihenfolge der Arbeitsliste ab.

**Das GitHub-Anfragekontingent** wird aus jeder API-Antwort gelesen (`X-RateLimit-Remaining`, `X-RateLimit-Reset`). Ist es aufgebraucht, hält der Import auf einer ganzen Seite an und meldet, wie viele Seiten er geschafft hat und wann die Grenze zurückgesetzt wird, statt weiterzumachen und auf jede verbleibende Seite einen Fehler zu schreiben. Die Antwort trägt `retry_after`, damit der Bildschirm aufhört nachzufragen. Ein erneut gestarteter Import aktualisiert die vorhandenen Seiten, statt sie zu verdoppeln. Diese Header kommen von `api.github.com`; der Raw-Datei-Host und der Archiv-Download haben eigene, undurchsichtige Grenzen und melden nichts, was ein weiterer Grund dafür ist, dass es den Archiv-Weg gibt.

Bei einem erneuten Import bestimmt wieder das Repository die Struktur, eine von Hand gesetzte Elternseite wird also zurückgesetzt. Das ist derselbe Handel wie beim Inhalt einer abgeglichenen Seite: für den Ordner-Import ist das Repository das Original.

**Reihenfolge.** Die Reihenfolge von Seiten und Bereichen kommt aus den Transport-Metadaten: Eine Seite mit einer Zeile `Reihenfolge` in ihrem Transport-Block (siehe unten) wird nach dieser Zahl einsortiert. Eine Seite ohne fällt auf ihre Position im Import zurück, die tief-zuletzt und alphabetisch ist, und steht immer hinter den nummerierten Seiten. Nummeriere also nur die Seiten, deren Reihenfolge zählt, halte die Zahlen klein (1, 2, 3), und lass den Rest. Die Reihenfolge eines Bereichs steht in seiner `README.md`; ein Bereichsordner ohne eine solche ordnet sich über den Rückfall ein.

Eine `index.md` oder `README.md`, die für einen Ordner steht, nimmt ihren Slug vom **Ordnernamen**, nicht vom Dateinamen, die Bereichsseite bekommt also eine saubere URL statt `readme`.

**Bilder.** Ein Bild, das eine Seite über einen relativen Pfad referenziert (etwa `../assets/x.svg`), wird aus dem Repository geholt und in die Mediathek eingespielt, die gespeicherte Seite zeigt also auf die Mediathek-Kopie statt auf einen Pfad, der auf der Website ins 404 liefe. Das passiert beim Import und bei jedem späteren Sync; das Einspielen dedupliziert über Dateiname und Inhalt, ein gemeinsam genutztes Bild wird also einmal gespeichert und wiederverwendet. Eine absolute Bild-URL bleibt unangetastet.

## Dieselbe Quelle zweimal importieren

Ein erneuter Import derselben Quelle **aktualisiert die vorhandenen Seiten, statt Duplikate anzulegen**. Woran eine Seite erkannt wird, hängt vom Import ab:

- **ZIP- und MkDocs-Importe** werden über den gespeicherten Quellpfad zugeordnet. Hat eine Seite keinen Quellpfad, wird sie über den Slug innerhalb des gewählten Handbuchs zugeordnet.
- **GitHub-Importe** werden über die Markdown-Quell-URL zugeordnet.
- **Ein eingefügter Entwurf legt immer eine neue Seite an.** Er trägt weder Quellpfad noch ausdrücklichen Slug, und eine vorhandene Seite still zu überschreiben, nur weil ein Titel passte, wäre schlimmer als ein Duplikat.

Wird eine Seite so aktualisiert, behält sie **Slug und Veröffentlichungsstatus**, damit URLs stabil bleiben und eine veröffentlichte Seite nicht heimlich zum Entwurf zurückfällt. Titel, Inhalt und Eltern-Seite kommen frisch aus der Quelle.

## Ein Handbuch exportieren

Unter **Handbuch → Export** kann jede Person mit `edit_others_posts` (Redaktion oder höher) ein **Handbuch als Bündel exportieren**: ein einzelnes ZIP mit einer `manifest.json` und einem Ordner `media/`. Wähle zuerst das **Handbuch**; das zweite Feld führt dann dessen **Bereiche** auf (ein Bereich ist eine oberste Seite und wird zusammen mit ihren Unterseiten exportiert). Lass es auf *das ganze Handbuch*, um alles zu exportieren. Dann **Bündel exportieren**, und das ZIP wird heruntergeladen. Es ist selbsttragend und lässt sich deshalb auf eine andere Seite mit dem Plugin übertragen, ohne auf diese hier zurückzugreifen. Auch ein Bereichs-Bündel trägt die Konfiguration des Handbuchs mit, damit die Zielseite weiss, wohin die Seiten gehören.

Das Bündel enthält die Konfiguration des Handbuchs (Sichtbarkeit und erlaubte Rollen), jede Seite als Abbild ihres Block-Markups samt Platz in der Hierarchie, die vier Einordnungs-Taxonomien, die Prüf-Metadaten und die referenzierten Medien. Es trägt bewusst **nicht** die Liste einzeln erlaubter Personen: Das sind E-Mail-Adressen, ein Bündel ist eine Datei, die heruntergeladen und weitergegeben wird, und die Zielseite hat ohnehin andere Benutzer. Ist ein Handbuch auf benannte Personen beschränkt, setze sie nach dem Import neu. Eine aus GitHub gespeiste Seite behält ihre Quell-URL und nimmt auf der Zielseite den Sync aus demselben Repository wieder auf. Lokale, seitenspezifische Daten bleiben draussen: Die Feedback-Zähler und der Sync-Status gehören der jeweiligen Seite.

## Ein Handbuch als statische Website exportieren

Derselbe Bildschirm bietet einen zweiten Export, für Leserinnen und Leser ganz ohne WordPress: **Als Website exportieren** baut ein ZIP aus reinen HTML-Dateien, das sich aus dem Dateisystem heraus öffnen lässt, ohne Server und ohne Netz. Wieder `edit_others_posts`, und der Bildschirm nennt die Folge beim Namen: eine statische Kopie trägt keine Zugriffsregeln mehr, wer die Datei hat, liest jede Seite darin.

Vier Entscheidungen sollte kennen, wer den Code liest (`Export/StaticSite.php`, `Export/SiteRenderer.php`):

- **Im Prozess gerendert, nicht über HTTP geholt.** Die naheliegende Umsetzung, ein `wp_remote_get()` je Seite, kommt als abgemeldeter Besucher an und exportiert bei einem fail-closed-Handbuch einen Ordner voller Seiten «kein Zugriff», technisch erfolgreich. Stattdessen wird hier gerendert, und jede Seite läuft durch `AccessController::can_view_post()` gegen die exportierende Person. Ein Export enthält damit genau das, was seine Urheberin lesen darf.
- **Durchgänge mit Zeitbudget.** Ein grosses Handbuch passt nicht in eine Anfrage. Ein Durchgang rendert, bis `living_handbook_static_export_time_budget` aufgebraucht ist, schreibt das Fertige ins Archiv, sichert den Auftrag in einem Transient und meldet, was übrig ist; der Browser holt den nächsten. Ein Auftrag gehört der Person, die ihn gestartet hat. Die Auftrags-ID behält ihre Gross- und Kleinschreibung: der Transient-Name geht durch eine Datenbankspalte, die Schreibweise ignoriert, und durch einen Objekt-Cache, der sie nicht ignoriert; kleingeschrieben liest ein Durchgang einen veralteten Stand seines eigenen Auftrags.
- **Pfade statt URLs.** Jeder Link zwischen Seiten und jedes Bild wird auf einen relativen Pfad im ZIP umgeschrieben; die Datei einer Seite liegt am Slug-Pfad ihrer Vorfahren (`pflege/seiten-pruefen.html`), demselben Schlüssel, den schon der Bündel-Export benutzt. Links nach draussen bleiben absolut, mit Absicht.
- **Eine Suche ohne Server.** Der Index ist eine JavaScript-Datei, die eine globale Variable setzt, kein JSON, das eine Seite nachladen müsste: ein über `file://` geöffnetes Dokument ist in den meisten Browsern seine eigene Herkunft und darf die Datei daneben nicht lesen. Das Inhaltsverzeichnis entsteht serverseitig aus den Überschriften-Ankern, nicht im Browser aus dem DOM.

- **Die Gestaltung wird gewählt, nicht festgelegt.** In einem ZIP gibt es kein Theme, also bringt der Export ein kleines eigenes Layout mit und dazu einen Satz Farben, der auf dem Bildschirm gewählt wird: die Einstellungen der Website, ein neutrales Hell, ein Dunkel oder eine Papier-Variante fürs Drucken. Jede besteht aus einer Handvoll `--lh-user-*`-Eigenschaften, mehr braucht es nicht, weil das Stylesheet des Plugins jede Farbe über diese Kette liest; `living_handbook_static_export_themes` ergänzt eine eigene. Jede Gestaltung trägt dieselben Druckregeln, damit eine gedruckte Seite ein Dokument ergibt und nicht das Abbild einer Website.
- **Zwei Dinge aus dem Frontend übernommen.** Mermaid-Diagramme stehen als `<pre class="mermaid">` ohnehin im exportierten Markup, also liefert der Export `mermaid.min.js` und sein Ansichts-Skript mit und zeichnet sie; die Bibliothek ist 3,5 MB gross, reist nur mit, wenn tatsächlich ein Diagramm vorkommt, und wird nur auf den Seiten geladen, die eins haben. Und `frontend.js` wird unverändert mitgeliefert, mit der Klasse `living-handbook-page` an der Seite und den drei Beschriftungen für die Vergrösserung, damit Bilder und Diagramme sich per Klick vergrössern wie auf der Website. Seine Such-, Filter- und Feedback-Teile finden hier keine Elemente und tun nichts, weshalb keine zweite Umsetzung desselben Verhaltens geschrieben wurde.

Weggelassen, jedes mit Grund: Kommentare und die Frage «War das hilfreich?» brauchen einen Server, die Facettenfilter brauchen Abfragen, und Namen und Profilbilder fallen aus der Fusszeile, weil die Datei das Haus verlässt.

## Ein Bündel importieren

Auf dem Import-Bildschirm nimmt der Reiter **Bündel** eine Datei entgegen, die auf einer anderen Seite exportiert wurde. Lade das ZIP hoch und wähle, was passieren soll, wenn eine Seite schon existiert:

- **Überspringen** (Standard): vorhandene Seiten bleiben vollständig unangetastet, nur neue werden angelegt.
- **Aktualisieren**: Titel, Inhalt, Struktur und Terms einer passenden Seite werden aus dem Bündel aufgefrischt.
- **Immer neu anlegen**: jede Seite im Bündel wird zu einer neuen Seite, nützlich zum Klonen in ein zweites Handbuch.

Eine Seite wird über die Herkunfts-ID erkannt, mit der sie exportiert wurde, danach über ihren Bündel-Schlüssel, danach über den Slug innerhalb des Ziel-Handbuchs. Zwei Regeln gelten unabhängig von deiner Wahl: Eine Seite mit dem Merkmal **geschützt** (`_lh_import_protected`) wird nie überschrieben, und **es wird nie etwas gelöscht**: Eine Seite, die es hier gibt, im Bündel aber fehlt, bleibt einfach bestehen.

Beim Aktualisieren bleibt die eigene Pflege dieser Seite erhalten: Die Feedback-Zähler sowie Prüfdatum, Prüfintervall und Prüfer bleiben so, wie sie hier sind, denn das ist lokale Pflege. Eine vom Import neu angelegte Seite übernimmt diese Werte dagegen aus dem Bündel.

**Importieren nach** entscheidet, wo die Seiten landen. Standardmässig geht das Bündel in sein eigenes Handbuch, jenes, aus dem es exportiert wurde; existiert es hier noch nicht, wird es angelegt. Wähle stattdessen ein vorhandenes Handbuch, um die Seiten dort abzulegen; dieses behält seine eigene Zugriffs-Konfiguration.

Wird das Handbuch neu angelegt, bekommt es die Sichtbarkeit **Mitglieder**, auch wenn das Bündel „öffentlich" sagt. So kann ein Import nie unbemerkt Inhalte veröffentlichen; hebe sie danach von Hand an. Ein vorhandenes Handbuch behält seine eigene Zugriffs-Konfiguration. Benutzer werden über den Login zugeordnet; eine erlaubte Person ohne Konto hier fällt weg und wird gemeldet. Wer die frühere Zuordnung über die E-Mail-Adresse braucht, stellt sie mit dem Filter `living_handbook_export_user_identifier` wieder her (siehe [hooks.md](hooks.md)). Sind die Seiten drin, werden interne Links zwischen ihnen auf die neuen Seiten gerichtet, und aus GitHub gespeiste Seiten nehmen den Sync aus ihrem Repository wieder auf.

Ein kurzer Bericht oben auf dem Bildschirm nennt, wie viele Seiten angelegt, aktualisiert, übersprungen oder geschützt wurden, und führt auf, was sich nicht zuordnen liess.

Der Import verlangt `edit_others_posts`, also Redaktion oder höher. Ein Bündel ist eine Datei von einer anderen Seite, sein Inhalt gilt deshalb als fremd und wird beim Einlesen gereinigt, genau wie beim Markdown-Import und beim GitHub-Sync: Skripte, Event-Handler und unsichere URLs werden entfernt. Die Reinigung läuft Block für Block, die Blockstruktur bleibt also unangetastet. Auch Medien werden gereinigt, SVG eingeschlossen. Trotzdem bringt ein Bündel Inhalte mit, die jemand anderes geschrieben hat, es lohnt sich also, sie vor dem Veröffentlichen zu lesen.

## Ein Bündel als normale WordPress-Seiten importieren

Ein Bündel lässt sich auch als gewöhnliche WordPress-Seiten einlesen statt als Handbuchseiten, wenn der Inhalt das Handbuch verlassen soll. Solche Seiten werden **immer als Entwurf** angelegt. Es fallen weg: Handbuch, Zugriffsregel, Navigation, Inhaltsverzeichnis, Badges und Prüfdaten. Es kommen mit: Text, Bilder, Diagramme und die Struktur, also Eltern-Seite und Reihenfolge.

## Bestehende WordPress-Seiten ins Handbuch verschieben

Eine gewöhnliche WordPress-Seite muss nicht neu geschrieben werden. Wähle sie in der Seitenliste unter **Seiten** aus, nimm die Mehrfachaktion **In ein Handbuch verschieben…** und daneben das Ziel-Handbuch. Unterseiten kommen mit, und die alte Adresse leitet mit 301 auf die neue um, bestehende Links laufen also nicht ins Leere. Die verschobenen Seiten kommen als **Nicht geprüft** an: Prüfdatum und Prüfintervall setzt du danach in der Meta-Box **Handbuch-Wartung**.
## Das App-Handbuch

Das Plugin bringt ein eigenes Handbuch mit: die Dokumentation der App, geschrieben als Living Handbook, also zugleich ein erstes Beispiel für eines. Es **wird mit dem Plugin ausgeliefert**, als Markdown unter `docs/user/`, und der Reiter **App-Handbuch** importiert es von dort. Das Ausliefern heisst: es passt immer zur installierten Version, und keine Installation hängt davon ab, dass ein Repository erreichbar bleibt. Das Markdown wird in einem öffentlichen Repository geschrieben und beim Build ins Plugin kopiert, es hat also weiterhin eine Bearbeitungsquelle; es reist nur mit dem Release mit. Ein erneutes Laden nach einem Plugin-Update frischt die Seiten auf.

Die Seiten werden von der Platte gelesen und ihre Bilder in die Mediathek eingespielt, genau wie der GitHub-Ordner-Import ein Repository behandelt. Der Inhalt wird als bereinigtes HTML gespeichert, die plugin-eigenen Blöcke (Bereichsliste, Feedback, Abzeichen) können diesen Weg also nicht mitgehen; sie werden im Text beschrieben, nicht eingebettet. Mermaid-Diagramme gehen mit.

Ein Fork, oder wer lieber den neuesten Stand direkt von GitHub zieht, richtet den Reiter über den Filter `living_handbook_app_handbook_url` (siehe [hooks.md](hooks.md)) auf ein Repository: jede tree-URL, die er zurückgibt, wird als GitHub-Ordner importiert statt der mitgelieferten Kopie. Der Filter gibt standardmässig eine leere Zeichenkette zurück, was die mitgelieferte Kopie bedeutet. Nur dort, wo es nichts zu laden gibt, bei einem leeren Filter auf einem Source-Build ohne den Ordner `docs/user/`, werden der Reiter und der Einrichtungshinweis ausgeblendet, damit nie ein Knopf ins Leere führt.

Das App-Handbuch wird **sofort veröffentlicht** statt als Entwurf abgelegt: Es ist kuratierter Inhalt, und seine Sichtbarkeit im Frontend wird über das Handbuch geregelt, in das es geht. Steht dieses auf „Mitglieder", sehen es nur Angemeldete; öffentlich wird es erst, wenn du das Handbuch auf öffentlich stellst. Ein manueller GitHub-Import bleibt dagegen ein Entwurf, damit du die Seiten vor dem Veröffentlichen prüfen kannst.

**Laden in** wählt das Handbuch, zu dem die Seiten gehören. Lege zuerst eines an, zum Beispiel „App-Handbuch", und stelle dort ein, wer es lesen darf.

## Transport-Metadaten

Eine Seite kann einen Metadaten-Block tragen, der sie auf die Taxonomien und Prüffelder des Handbuchs abbildet. Der Block wird an der deutschen Überschrift `## Transport-Metadaten` erkannt; alles darüber ist der Seiteninhalt, und die erste `# H1` wird zum Titel. Eine englische Überschrift wird nicht als Markierung erkannt. Eine Markierung innerhalb eines Codeblocks gilt als Beispiel und wird übersprungen, und tritt die Markierung ausserhalb von Code mehrfach auf, gewinnt das letzte Vorkommen. So kann eine Seite die Markierung in ihrer Dokumentation zitieren, ohne in zwei Teile zerschnitten zu werden.

Die Felder (deutsche Beschriftungen, eine pro Listenpunkt):

```
## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Applikation
* Zielgruppe: Alle Mitglieder, Technik
* Eltern-Seite: Übersicht
* Reihenfolge: 3
* Textauszug: Kurz erklärt.
* Letzte Prüfung: 2026-07-08
* Prüfintervall: 90 Tage
```

Hinweise:

- **Das Themenfeld akzeptiert `Thema`, `Bereich` oder `Themengebiet`.** Die Taxonomie heisst in der Oberfläche „Themen". `Thema` passt zu dieser Beschriftung und ist für neue Entwürfe zu bevorzugen; `Bereich` und das ältere `Themengebiet` halten vorhandene Entwürfe lauffähig, du musst also einen Bestand, der vor der Umbenennung geschrieben wurde, nicht anfassen. Trägt ein Entwurf mehrere, gewinnt `Thema`, danach `Bereich`.
- `Zielgruppe` ist eine kommagetrennte Liste.
- `Eltern-Seite` wird über den Titel zugeordnet, nachdem alle Seiten des Imports existieren, die Eltern-Seite darf also später im selben Import auftauchen.
- Platzhalter in eckigen Klammern wie `[Rolle]` oder `[JJJJ-MM-TT]` gelten als leer; `[ANNAHME: FAQ]` wird zu seinem Wert aufgelöst (`FAQ`).
- Das Handbuch, zu dem eine Seite gehört, lässt sich ebenfalls im Transport-Block setzen, mit `Handbuch`. Das übersteuert das auf dem Import-Bildschirm gewählte Ziel-Handbuch.

## Strukturierter Import aus MkDocs

Enthält das ZIP eine `mkdocs.yml`, ist deren `nav`-Abschnitt massgeblich für die Struktur: Seitentitel, Reihenfolge und Eltern-Kind-Verschachtelung folgen der Navigation, und die `index.md` eines Abschnitts wird zur Seite dieses Abschnitts. So bleibt die Form einer Dokumentationsseite beim Import erhalten. Dafür wird die Bibliothek symfony/yaml gebraucht, die in `vendor/` mitgeliefert wird.

## GitHub-Sync (eine Seite synchron halten)

Jede Seite hat eine Quelle, einstellbar in der Box **Quelle** im Editor:

- **In WordPress gepflegt** (Standard): wird normal in WordPress bearbeitet.
- **Aus GitHub synchronisiert**: Die Seite trägt eine Markdown-Quell-URL. Beim Speichern, über den Knopf **Jetzt synchronisieren** und nach Zeitplan wird die Seite aus dem Repository geholt und neu zu HTML gerendert (vor dem Speichern durch `wp_kses` gefiltert). Ihr Inhaltseditor ist gesperrt, sie lässt sich also nicht von Hand bearbeiten. Die Seitenliste zeigt die Quelle in einer eigenen Spalte, und der Block „GitHub-Quellhinweis" markiert die öffentliche Seite als auf GitHub gepflegt.

### Eine synchronisierte Seite in eine WordPress-Seite wandeln

Eine aus GitHub synchronisierte Seite lässt sich lösen und in WordPress weiterpflegen. Stelle sie in der Box **Quelle** von **Aus GitHub synchronisiert** auf **In WordPress gepflegt** und speichere. Der aktuelle Inhalt bleibt genau so erhalten, der Hintergrund-Sync fasst die Seite nicht mehr an, der Inhaltseditor ist wieder frei, und der „GitHub-Quellhinweis" erscheint nicht mehr auf der öffentlichen Seite. Es wird nichts erneut geholt, die Seite fällt beim nächsten Sync also nicht zurück. In der Praxis ist der Schritt einseitig: um zurückzukehren, stellst du die Quelle wieder auf GitHub und trägst die Markdown-Quell-URL erneut ein, der nächste Sync überschreibt die Seite dann mit der Repository-Fassung.

### Wie der Sync von Änderungen erfährt

Es gibt keinen Webhook. WordPress holt aktiv: beim Speichern, auf Zuruf (Jetzt synchronisieren) und nach einem Hintergrund-Zeitplan (WordPress-Cron, der bei Seitenaufrufen auslöst). Den Zeitplan stellst du unter **Handbuch → Einstellungen** ein: aus, stündlich, zweimal täglich, täglich oder wöchentlich (der Standard bei einer neuen Installation). „Aus" synchronisiert weiterhin beim Speichern und über „Jetzt synchronisieren".

Ein grosses Handbuch wird in Teilmengen synchronisiert statt auf einmal, eine einzelne Anfrage muss also nie jede Seite holen.

Das Holen beim Speichern läuft innerhalb der Speicher-Anfrage: Beim Speichern einer aus GitHub synchronisierten Seite wird die Quelle geholt und neu gerendert, bevor das Speichern zurückkehrt, der neue Inhalt ist also sofort sichtbar. Der Preis dafür ist, dass das Speichern auf den Netzwerk-Umlauf zu GitHub wartet. Bei normalen Seitengrössen fällt das nicht auf; nur wenn eine Quelle ungewöhnlich gross oder das Netz sehr langsam wäre, könnte sich ein Speichern zäh anfühlen. Dieses Holen in ein Hintergrund-Ereignis zu verlegen (das Speichern kehrte sofort zurück, der Inhalt aktualisierte sich einen Moment später) ist als mögliche künftige Änderung vermerkt; heute ist es nicht so, weil es das Verhalten „geholt, wenn du speicherst" bräche, das den Editor den geholten Inhalt sofort zeigen lässt.

### Wenn ein Sync fehlschlägt

Ein fehlgeschlagenes Holen wird an der Seite vermerkt und markiert. Ein Hinweis auf den Handbuch-Bildschirmen nennt, wie viele Seiten nicht synchronisiert werden konnten; öffne eine Seite und schau in der Quelle-Box unter „Letzter Sync" nach dem Grund (ein Rate-Limit, ein HTTP-Fehler, ein nicht erreichbarer Host). Die Seite behält ihren bisherigen Inhalt, ein fehlgeschlagener Sync leert also nie eine Seite.

### Öffentliche und private Repositories

Der Live-Sync holt das rohe Markdown über HTTP, funktioniert also bei öffentlichen Repositories. Für ein privates Repository importierst du stattdessen aus einem ZIP-Export (die MkDocs-Struktur bleibt erhalten), statt einen Zugriffs-Token zu hinterlegen.

Aus Sicherheitsgründen muss die Quell-URL über https auf einen erlaubten Host zeigen (standardmässig `raw.githubusercontent.com`), damit niemand den Server auf eine interne Adresse richten kann. Die Liste erweiterst du mit dem Filter `living_handbook_sync_allowed_hosts`, siehe [hooks.md](hooks.md).

## Was das Plugin wohin sendet

Nur das, was du abrufen lässt. Import und Sync lesen von `github.com`, `raw.githubusercontent.com` und der GitHub-Contents-API unter `api.github.com`, und ausschliesslich von den Adressen, die du selbst einträgst. Es wird nichts irgendwohin gesendet, und wenn du keine der Funktionen nutzt, macht das Plugin überhaupt keine externen Aufrufe.

Die nicht angemeldete GitHub-API erlaubt etwa 60 Anfragen pro Stunde. Ein grosser Ordner-Import kann daran stossen; der Import meldet das klar, und du kannst es später erneut versuchen.

## Deinstallieren

Standardmässig behält das Löschen des Plugins deine Inhalte und entfernt nur die eigenen Einstellungen und Caches des Plugins. Unter **Handbuch → Einstellungen** kannst du dich dafür entscheiden, alles zu entfernen, was das Plugin angelegt hat, einschliesslich Handbuch-Seiten, Handbüchern, deren Metadaten, der angelegten Taxonomie-Begriffe und aller plugin-eigenen Templates, die du im Site-Editor bearbeitet hast. Die Option ist bewusst standardmässig aus: Ein versehentliches Löschen soll dich nicht das Handbuch kosten.

## Grenzen

- Der Ordner-Import erfasst Unterordner, aber höchstens 200 Dateien auf einmal. Ist die Grenze erreicht, importierst du die übrigen Unterordner einzeln.
- Ein ZIP wird innerhalb von Grenzen gelesen (höchstens 2000 Einträge, 5 MB pro Datei, 100 MB unkomprimiert insgesamt), ein präpariertes Archiv kann den Speicher des Servers also nicht erschöpfen. Die unkomprimierte Gesamtgrösse ist im Code über den Filter `living_handbook_zip_max_bytes` einstellbar (siehe [hooks.md](hooks.md)); die tatsächliche Obergrenze bleiben die PHP-Upload- und Speichergrenzen des Servers.
- Die Transport-Markierung und ihre Feldbeschriftungen sind deutsch.
- Synchronisierter Inhalt wird als gerendertes HTML gespeichert, nicht als bearbeitbare Blöcke, weil ein Cron-Lauf keinen Browser hat, um HTML in Blöcke zu wandeln.
- MkDocs-Admonitions (`!!! note`, `??? tip`) werden in einen Zitatblock mit vorangestelltem Titel gewandelt, der Hinweis bleibt also abgesetzt, statt in losen Text zu zerfallen. Andere MkDocs-eigene Syntax fällt weiterhin auf reinen Text zurück: pymdownx-Tabs etwa gehören nicht zu GitHub Flavored Markdown, der Konverter versteht sie also nicht.
- Living Handbook ist für Einzelinstallationen gebaut; in einem Multisite-Netzwerk importierst du pro Seite.
