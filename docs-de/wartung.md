# Wartung und Aktualität

Die meisten Plugins für interne Dokumentation helfen beim Veröffentlichen. Living Handbook ist um den Teil herum gebaut, der danach kommt: Seiten richtig zu halten, wenn sie erst einmal existieren. Dokumentation ohne Pflege wird falsch, und falsche Dokumentation ist schlimmer als gar keine. Diese Seite erklärt die Aktualitäts-Funktion und den Arbeitsablauf, den sie stützt.

## Die Idee

Jede Seite trägt zwei Daten und ein Intervall:

- **Zuletzt aktualisiert** setzt sich beim Speichern selbst und bildet deshalb immer den echten Stand des Inhalts ab.
- **Zuletzt geprüft** ist der Zeitpunkt, an dem zuletzt jemand die Seite auf Richtigkeit durchgesehen hat, auch wenn nichts zu ändern war. Das setzt du von Hand, denn nur ein Mensch kann sagen „ich habe das gelesen und es stimmt noch".
- **Prüfintervall** ist, wie lange eine Prüfung für diese Seite gilt. Schnelllebige Themen (Werkzeuge, externe Dienste) bekommen ein kurzes Intervall, stabile Themen (Grundsätze, Organisationsstruktur) ein langes.

Aus Prüfdatum und Intervall berechnet das Plugin einen **Prüfstatus** und zeigt ihn als Badge in der Metadaten-Fusszeile der Seite.

## Die vier Zustände

| Badge | Bedeutung |
| --- | --- |
| **Geprüft** | Die letzte Prüfung liegt innerhalb des Prüfintervalls der Seite. |
| **Prüfung fällig** | Das Intervall ist abgelaufen. Die Seite ist nicht falsch, aber niemand hat sie zuletzt bestätigt. |
| **Prüfung überfällig** | Das doppelte Intervall ist abgelaufen. Das ist der Eskalationszustand, er soll auffallen. |
| **Nicht geprüft** | Die Seite hat kein Prüfdatum oder kein Prüfintervall. Bewusst neutral gefärbt (`--lh-none`) statt alarmierend, denn eine Seite, die noch niemand angeschaut hat, ist nicht veraltet; ihr Punkt ist ein leerer Kreis statt eines gefüllten. |

Der Badge arbeitet nicht mit Farbe allein: Jeder Zustand hat eine eigene Form und eine Textbeschriftung, er ist also auch ohne Farbsehen und mit einem Screenreader lesbar.

## Die Felder setzen

Öffne eine Seite und suche unten im Editor die Meta-Box **Handbuch-Wartung**. Dort setzt du die verantwortliche Rolle, das letzte Prüfdatum und das Prüfintervall. Wenn du eine Seite prüfst und sie stimmt noch, aktualisiere das Prüfdatum trotzdem, obwohl sich der Inhalt nicht geändert hat: Genau dieses Signal soll der Prüfstatus tragen.

Für den häufigen Fall „ich habe das heute geprüft" musst du die Seite nicht öffnen: Nutze **QuickEdit** in der Handbuch-Liste (auf eine Zeile zeigen, dann QuickEdit). Dort stehen Prüfdatum, Prüfer und Prüfintervall direkt in der Liste, vorbelegt mit den aktuellen Werten, sodass du die Prüfung mehrerer Seiten zügig nachtragen kannst.

Beim Import reisen Prüfdatum und Intervall im Markdown-Transport-Block mit (`Letzte Prüfung`, `Prüfintervall`) und werden in diese Felder geschrieben. Siehe [Import und Sync](import-und-sync.md).

## Seiten in der Liste finden

Die Handbuch-Liste bringt ein paar Spalten und Filter mit, die beim Abarbeiten helfen. Die Spalte **Zuletzt geprüft** sortiert nach Prüfdatum (älteste zuerst ist die nützliche Richtung fürs Aufräumen), und die Spalte **Feedback** sortiert nach dem Netto-Feedback, also Ja-Stimmen minus Nein-Stimmen, sodass die am besten und am schlechtesten aufgenommenen Seiten einen Klick entfernt sind. Über der Liste filtert je ein Auswahlfeld pro Taxonomie: Handbuch, Seitentyp, Thema, Verantwortung und Zielgruppe, dazu ein Filter für die Quelle (GitHub oder WordPress), genau so, wie der Kategorienfilter bei Beiträgen arbeitet. Die Taxonomie-Spalten selbst sortieren bewusst nicht, denn eine Seite kann zu mehreren Terms gehören und hat damit keine eindeutige Reihenfolge; die Auswahlfelder sind der verlässliche Weg, die Liste einzugrenzen.

Daneben steht ein **Prüfstatus-Filter** (geprüft, fällig, überfällig, nie geprüft). Der Status ist kein gespeichertes Feld, er wird aus Prüfdatum und Prüfintervall jeder Seite berechnet und ist deshalb ein eigener Filter statt einer sortierbaren Spalte. Sortiere die Spalte „Zuletzt geprüft" nach Datum, um die ältesten Prüfungen zu sehen, oder filtere nach Status, um alles Überfällige auf einmal herauszuziehen.

**Die Filterleiste folgt den Spalten.** WordPress lässt jede Benutzerin oben rechts unter „Ansicht anpassen" Spalten abschalten, die Spalten dieses Plugins eingeschlossen. Seit 0.64.0 geht ein Filter mit seiner Spalte: wer „Themen" ausblendet, verliert auch das Themen-Auswahlfeld. Ein Vokabular ohne einen einzigen Begriff fällt ebenfalls weg, sein Auswahlfeld könnte nur „Alle Themen" anbieten. Eine zweite Einstellung dafür gibt es bewusst nicht, und eine Ausnahme von der Regel: ein Filter, der die Liste gerade eingrenzt, bleibt auch bei ausgeblendeter Spalte sichtbar, denn der Query-Parameter steht in der Adresse, und ein Filter, den niemand sieht, ist ein Filter, den niemand zurücknehmen kann. Beide Fragen beantwortet `Admin/ListScreen.php`, gefragt wird von `Maintenance` und `GitSync`.

Über der Liste können zwei Warnungen erscheinen: Seiten, die zu keinem Handbuch gehören (und deshalb im Frontend unsichtbar bleiben), und GitHub-Seiten, deren letzter Sync fehlgeschlagen ist. Beide führen die betroffenen Seiten als direkte Links auf, du erreichst also jede mit einem Klick.

## Das Überfällig-Dashboard

Das Plugin ergänzt ein Dashboard-Widget, das die Seiten auflistet, deren Prüfung fällig oder überfällig ist, damit du sie nicht einzeln suchen musst. Es liest dieselben Prüfdaten und Intervalle. Das Widget ist die Triage-Fläche: von oben nach unten durcharbeiten, jede Seite prüfen und ihr Prüfdatum zurücksetzen.

## Wer prüft

Verantwortung ist bewusst **verteilt, nicht zentral**. Es gibt keine einzelne Rolle, der das ganze Handbuch gehört. Jede Seite nennt in ihren Metadaten eine **verantwortliche Rolle**, und diese Rolle pflegt die Seite. Ein Personalwechsel berührt die eine Stelle, die Rollen auf Personen abbildet, nicht die Seiten.

Die übergreifende Arbeit, die zu keiner einzelnen Seite gehört (Navigationsstruktur, Such-Protokolle und Feedback lesen, das Dashboard triagieren, Stichproben), liegt bei einer redaktionellen Rolle. Die Zuständigkeit für die einzelne Seite bleibt in beiden Fällen bei der verantwortlichen Rolle.

## Zwei Wege, wie eine Seite gepflegt wird

- **Nach Plan.** Jede Seite wird regelmässig von ihrer verantwortlichen Rolle geprüft. „Regelmässig" ist das Prüfintervall, das du pro Seite setzt. Wird eine Prüfung fällig, bringt das Dashboard sie hoch; bleibt sie über das doppelte Intervall hinaus liegen, eskaliert der Badge auf *Prüfung überfällig*, damit die Seite nicht still veraltet.
- **Aus Anlass.** Eine Seite wird sofort aktualisiert, wenn sich ein Prozess ändert, ein Werkzeug getauscht oder abgeschafft wird, ein Fehler auffällt, oder wenn mehrere Leute dieselbe Frage stellen, die das Handbuch nicht beantwortet. Das ist die gesündeste Pflege: die Seite im selben Arbeitsschritt wie die Änderung richtigstellen, nicht später als eigene Pflichtübung.

## Versionierung

Weil die Seiten in WordPress gepflegt werden, sind die WordPress-Revisionen die Versionsgeschichte: wer wann was geändert hat, mit der Möglichkeit, eine frühere Fassung wiederherzustellen. Es gibt kein separates Änderungsprotokoll pro Seite zu führen.

## Verwandtes Feedback-Signal

Die Frage **War das hilfreich?** auf jeder Seite (siehe [bloecke.md](bloecke.md)) zählt eine Stimme pro berechtigter Leserin und meldet die Summen ans Dashboard. Eine Seite, die dauerhaft „Nein" einsammelt, ist ein Wartungssignal: Sie beantwortet die Frage nicht, mit der die Leute ankommen.

Standardmässig stimmen nur angemeldete Leserinnen und Leser ab, die die Seite sehen dürfen, je eine Stimme. Unter Handbuch, Einstellungen lässt sich **Öffentliches Feedback** einschalten, dann stimmen auch abgemeldete Besuchende auf öffentlichen Seiten ab. Zum Schutz der Privatsphäre speichern solche Stimmen nichts Persönliches (kein Cookie, keine IP, keine Kennung), sie haben deshalb keine Begrenzung auf eine Stimme. Nach einer Überarbeitung kannst du die Zähler einer Seite zurücksetzen: die Handbuch-Liste zeigt bei jeder Seite mit Stimmen die Aktion **Feedback zurücksetzen**, die deren Ja- und Nein-Zähler leert.
