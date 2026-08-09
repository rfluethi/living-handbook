# Erste Schritte

Von der frischen Installation bis zur ersten Seite, die Besucherinnen wirklich sehen. Das geht den ganzen Weg einmal durch; die tieferen Nachschlagewerke (Blöcke, Vorlagen, Import, Wartung) sind am Ende verlinkt.

## Bevor du anfängst

- **WordPress 6.8 oder neuer, mit einem Block-Theme.** Einstiegsseite, Einzelseite und Navigation bestehen aus Block-Vorlagen, ein klassisches Theme rendert sie deshalb nicht.
- **PHP 8.1 oder neuer.**
- **Eine Einzelinstallation.** Living Handbook ist für eine Seite gebaut; die netzwerkweite Aktivierung in einem Multisite wird nicht unterstützt. In einem Netzwerk aktivierst du es pro Seite.

## 1. Installieren und aktivieren

Lade das Plugin-ZIP unter **Plugins → Installieren → Plugin hochladen** hoch, oder leg den Ordner nach `wp-content/plugins/`, und aktiviere es dann. (Wenn du aus dem Quellcode baust, führe zuerst `composer install` aus, damit `vendor/` vorhanden ist; im Release-ZIP ist es bereits enthalten.)

Die Aktivierung erledigt drei Dinge für dich:

- Sie registriert den Handbuch-Inhaltstyp und die Taxonomien und legt in den vier Einordnungs-Taxonomien (Seitentyp, Thema, verantwortliche Rolle, Zielgruppe) Startbegriffe an.
- Sie erstellt eine normale WordPress-Seite namens **Handbuch** mit dem Block **Handbuch-Übersicht** darauf, damit eine frische Installation etwas zeigt statt eines leeren Archivs. Du kannst diese Seite später verschieben, umgestalten oder ersetzen.
- Sie erneuert die Permalink-Regeln, damit die Handbuch-URLs sofort funktionieren.

## Abkürzung: zuerst das App-Handbuch laden

Das Plugin bringt ein eigenes Handbuch mit: die Dokumentation der App, als Living Handbook geschrieben, sodass sie zugleich ein erstes Beispiel für eines ist. Geh auf **Handbuch → Import**, öffne den Reiter **App-Handbuch** und drücke **App-Handbuch laden**. Es wird im Plugin mitgeliefert, als Markdown unter `docs/user/`, und von dort importiert; du liest also die Dokumentation, die zu deiner Version passt, und siehst gleichzeitig ein echtes Handbuch.

Ein paar Einzelheiten, die du kennen solltest:

- Es wird nie bei der Aktivierung geladen, sondern nur, wenn du es verlangst. Es folgt der Backend-Sprache.
- Es ist ein Import aus einem lokalen Ordner, es wird also nichts über das Netz geholt: Die Seiten passen immer zur installierten Version, und ein erneutes Laden nach einem Plugin-Update frischt sie auf. Ein Fork kann den Reiter stattdessen über den Filter `living_handbook_app_handbook_url` auf ein GitHub-Repository richten.
- Wähle unter **Laden nach**, in welches Handbuch es kommt; leg vorher eines an (zum Beispiel „App-Handbuch") und setz dort, wer es lesen darf.

Der Rest dieser Anleitung baut ein Handbuch von Grund auf, und genau das machst du für echte Inhalte.

## 2. Das erste Handbuch anlegen

Ein Handbuch ist der Behälter, in dem deine Seiten leben. Geh auf **Handbuch → Handbücher → Neu**, gib ihm einen Namen (zum Beispiel „Allgemein") und eine kurze Beschreibung, und speichere.

Setz auf demselben Bildschirm die **Sichtbarkeit**. Ein neues Handbuch steht standardmässig auf **Alle Mitglieder (angemeldet)**, eine ausgeloggte Besucherin sieht also nichts, bis du entweder auf **Öffentlich** stellst oder bestimmten Rollen und Personen Zugriff gibst. Das ist Absicht: Das Plugin ist fail-closed, es verbirgt eine Seite lieber, als sie preiszugeben.

## 3. Die erste Seite anlegen

Geh auf **Handbuch → Neue Seite** und schreib die Seite wie jede Block-Editor-Seite: ein klarer Titel, ein kurzer Einstieg, dann der Inhalt.

Zwei Einstellungen in der Editor-Seitenleiste zählen, bevor du veröffentlichst:

- **Die Seite einem Handbuch zuordnen.** Das ist der Schritt, den die meisten übersehen. Eine Handbuch-Seite ohne Handbuch ist im Frontend unsichtbar, denn der Zugriff ist fail-closed und eine Seite ohne Handbuch hat keine Sichtbarkeitsregel, die sie erfüllen könnte. Wähle das Handbuch, das du gerade angelegt hast.
- **Die Seite einordnen.** Setz den Seitentyp (Anleitung, Prozess, Referenz, Rolle, Hintergrund, FAQ), ein oder mehrere Themen, die Zielgruppe und die verantwortliche Rolle. Daraus entstehen die Badges und die Filter auf der Einstiegsseite.

> **Das häufigste „es erscheint nichts".** Wenn eine veröffentlichte Seite nicht auftaucht, ist ihr fast immer kein Handbuch zugeordnet, oder ihr Handbuch ist für die aktuelle Besucherin nicht sichtbar. Ordne das Handbuch zu und prüfe dessen Sichtbarkeit.

Ob eine neue Handbuch-Seite Kommentare erlaubt, entscheidet die Voreinstellung unter **Einstellungen → Diskussion**. Das Plugin schaltet Kommentare nicht mehr hart aus, auch nicht beim Import. Für eine einzelne Seite stellst du das im Feld **Diskussion** um, und unter **Handbuch → Handbücher** lässt sich es für ein ganzes Handbuch setzen: **erben** (jede Seite entscheidet selbst), **offen** oder **geschlossen**. Geschlossen blendet nur das Kommentarformular aus, vorhandene Kommentare bleiben stehen und werden nicht gelöscht.

## 4. Zuständigkeit und Aktualität setzen

Unten im Editor trägt die Meta-Box **Handbuch-Wartung** die Prüffelder: die verantwortliche Rolle, wann die Seite zuletzt geprüft wurde, und das Prüfintervall. Füll das jetzt aus, auch grob. Daraus speisen sich der Aktualitäts-Badge auf der Seite und das Überfällig-Dashboard, und genau darum geht es bei einem lebendigen Handbuch. Die Einzelheiten stehen in [wartung.md](wartung.md).

Die Zuständigkeit wird über die **Rolle** vergeben, nicht über die Person, ein Personalwechsel bedeutet also nicht, jede Seite zu bearbeiten. Welche Person eine Rolle gerade innehat, wird an einer Stelle gepflegt.

## 5. Der Seite einen Platz in der Navigation geben

Die Navigation je Handbuch entsteht aus der Seitenhierarchie, nicht aus einem Menü, das du von Hand pflegst. Setz in der Editor-Seitenleiste unter **Seiten-Attribute** eine **Eltern-Seite** und eine **Reihenfolge**. Oberste Seiten werden zu den Bereichen, die auf der Einstiegsseite erscheinen; ihre Unterseiten werden zur verschachtelten Navigation. Eine Seite ohne Eltern-Seite ist ein oberster Bereich.

## 6. Anschauen

Dein Handbuch hat jetzt drei Flächen:

- Die **Übersichtsseite** („Handbuch") listet jedes Handbuch auf, das die Besucherin lesen darf.
- Die **Einstiegsseite** unter `/handbook-set/<handbook-slug>/` ist die Startseite eines Handbuchs: Suche, Filter, Bereichskacheln und zuletzt aktualisierte Seiten.
- Jede **Einzelseite** unter `/handbook/<page-slug>/` zeigt die Navigation, den Inhalt, das Inhaltsverzeichnis, die Badges, die Feedback-Frage und die Metadaten-Fusszeile.

Einstiegsseite und Einzelseite bringen Block-Vorlagen mit, die die richtigen Blöcke bereits platzieren, du musst sie also selten von Hand bauen. Was jeder Block tut und wo er erscheint, steht in [bloecke.md](bloecke.md).

## Wie es weitergeht

- **Schnell Inhalt einfüllen:** Markdown einfügen, ein ZIP hochladen oder aus GitHub synchronisieren. Siehe [Import und Sync](import-und-sync.md).
- **Ein Handbuch auf eine andere Seite übertragen:** unter **Handbuch → Export** als Bündel exportieren und diese Datei dort importieren. Siehe [Import und Sync](import-und-sync.md).
- **Es vor dem Veralten bewahren:** Prüfdaten, Intervalle und das Überfällig-Dashboard. Siehe [Wartung](wartung.md).
- **Das Aussehen ändern:** die Blöcke und ihre CSS-Variablen. Siehe [Blöcke](bloecke.md) und [Anpassung](anpassung.md).
- **Den Code verstehen:** die [Code-Übersicht](code-uebersicht.md) in einfacher Sprache.
