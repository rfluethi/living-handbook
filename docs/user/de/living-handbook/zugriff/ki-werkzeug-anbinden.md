# KI-Werkzeug anbinden

Mit Living Handbook kann ein KI-Werkzeug wie Claude, Cursor oder VS Code deine Handbücher lesen: Es sucht, liest Seiten und nennt dabei die Quelle und den Prüfstatus. Diese Anleitung zeigt, wie du ein solches Werkzeug mit deiner Website verbindest und was es danach sieht.

<details>
<summary>Konzept: Was das Werkzeug kann und was nicht</summary>

Living Handbook stellt sieben Fähigkeiten bereit, in WordPress „Abilities“ genannt. Ein KI-Werkzeug nutzt sie über das Plugin **MCP Adapter**, das die Verbindung herstellt.

* **Für alle:** die Handbücher auflisten, den Aufbau eines Handbuchs lesen, suchen und eine Seite lesen.
* **Für die Redaktion** (wer fremde Beiträge bearbeiten darf): Seiten, deren Prüfung fällig ist oder fehlt, Seiten mit vielen „Nicht hilfreich“-Stimmen und die häufigsten Suchen ohne Treffer.

Alle sieben lesen nur. Das Werkzeug kann nichts anlegen, ändern oder löschen.

Das Werkzeug meldet sich mit einem WordPress-Konto an und sieht genau das, was dieses Konto auf der Website sieht. Dazu kommen drei Ausnahmen, in denen es weniger sieht:

* Seiten mit dem Häkchen **Diese Seite von KI-Nutzung ausschließen** fehlen immer.
* Seiten mit Passwort fehlen immer.
* In Handbüchern, die nicht öffentlich sind, stehen statt der Adressen hochgeladener Dateien nur der Linktext oder die Bildbeschreibung.

Die Suche ist dieselbe wie auf der Website: Sie findet Wörter, wie sie geschrieben sind, ohne Synonyme und ohne Tippfehler-Toleranz. Ein gutes Werkzeug sucht deshalb mehrmals mit anderen Wörtern.

</details>

## Voraussetzungen

* WordPress 6.9 oder neuer. Auf älteren Versionen bietet Living Handbook keine Abilities an.
* Das Plugin **MCP Adapter**, installiert und aktiviert unter **Plugins → Neues Plugin hinzufügen**.
* Die Website läuft über HTTPS. Sonst bietet WordPress keine Anwendungspasswörter an.

## Schritte

1. **Kläre im Team, was in Ordnung ist.** Der Inhalt jeder Seite, die das Werkzeug liest, geht an den Anbieter des KI-Werkzeugs. Markiere Seiten mit Personendaten, etwa ein Verzeichnis mit Kontaktdaten, im Editor im Kasten **Handbuch-Wartung** mit **Diese Seite von KI-Nutzung ausschließen**.
2. **Lege ein eigenes Konto für das Werkzeug an,** mit der kleinsten Rolle, die reicht. Zum Lesen genügt **Abonnent**, plus die Freigabe in eingeschränkten Handbüchern, die das Werkzeug lesen soll. Für die Abilities der Redaktion braucht das Konto die Rolle **Redakteur**. Nimm kein Administratorkonto: Es liest jedes Handbuch, und damit auch das Werkzeug.
3. **Erstelle ein Anwendungspasswort.** Öffne unter **Benutzer** das Profil dieses Kontos und scrolle zu **Anwendungspasswörter**. Gib einen Namen ein, etwa „Claude auf meinem Laptop“, und klicke auf **Neues Anwendungspasswort hinzufügen**. Kopiere das Passwort sofort, WordPress zeigt es nur einmal.
4. **Trage die Website im KI-Werkzeug ein.** Die Adresse ist die Adresse deiner Website, gefolgt von `/wp-json/mcp/mcp-adapter-default-server`. In Claude Desktop öffnest du unter **Einstellungen → Entwickler → Konfiguration bearbeiten** eine Datei und ergänzt diesen Eintrag mit deinen Werten:

	```json
	{
	  "mcpServers": {
	    "handbuch": {
	      "command": "npx",
	      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
	      "env": {
	        "WP_API_URL": "https://example.com/wp-json/mcp/mcp-adapter-default-server",
	        "WP_API_USERNAME": "ki-konto",
	        "WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
	      }
	    }
	  }
	}
	```

	Dafür muss Node.js auf deinem Computer installiert sein. Andere Werkzeuge und die direkte Verbindung über HTTP beschreibt die [Anleitung des MCP Adapter](https://github.com/WordPress/mcp-adapter/blob/trunk/docs/getting-started/site-owners.md).
5. **Starte das Werkzeug neu und teste.** Frag zum Beispiel: „Welche Handbücher kannst du auf meiner Website lesen?“ Das Werkzeug sollte die Handbücher nennen, die das Konto sehen darf.

## Ergebnis

Du kannst dein KI-Werkzeug fragen, was im Handbuch steht, und bekommst Antworten mit Link auf die Quellseite und mit ihrem Prüfstatus. Als Redaktion fragst du etwa: „Welche Seiten muss ich diese Woche prüfen?“ oder „Wonach suchen die Leute, ohne etwas zu finden?“

<details>
<summary>Hinweis: Wenn das Werkzeug nichts findet</summary>

* **Die Liste der Abilities ist leer:** Prüfe, dass Living Handbook und MCP Adapter beide aktiv sind und WordPress mindestens 6.9 ist.
* **Fehler 401:** Benutzername oder Anwendungspasswort stimmen nicht, oder der Server leitet den Kopf `Authorization` nicht an WordPress weiter. Frag im zweiten Fall beim Hosting nach.
* **Ein Handbuch fehlt:** Das Konto darf es nicht lesen. Gib es unter **Handbuch → Handbücher** für dieses Konto frei.
* **Eine Seite fehlt:** Sie ist vom KI-Zugriff ausgeschlossen, hat ein Passwort oder ist nicht veröffentlicht.

</details>

<details>
<summary>Hinweis: Suchen ohne Treffer</summary>

Findet eine Suche nichts, zählt Living Handbook den Suchbegriff, damit die Redaktion sieht, was fehlt. Gespeichert werden nur Begriff, Handbuch, Kalenderwoche und ob die Suche von der Website oder vom KI-Werkzeug kam, nichts über die Person. Nach 90 Tagen wird gelöscht. Abschalten lässt sich das in den [Einstellungen](../die-einstellungen.md) unter **Feedback**.

</details>

## Verwandte Seiten

* [Zugriff verstehen](zugriff-verstehen.md)
* [Sichtbarkeit einstellen](sichtbarkeit-einstellen.md)
* [Die Einstellungen](../die-einstellungen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Slug: ki-werkzeug-anbinden
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Zugriff
* Zielgruppe: Alle Mitglieder
* Eltern-Seite: Zugriff
* Reihenfolge: 3
* Textauszug: Wie du ein KI-Werkzeug wie Claude mit deinen Handbüchern verbindest, was es danach sieht und was nicht.
* Letzte Aktualisierung: 2026-11-09
* Letzte Prüfung: 2026-11-09
* Prüfintervall: 180 Tage
