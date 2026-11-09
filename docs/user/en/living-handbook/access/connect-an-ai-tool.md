# Connect an AI tool

With Living Handbook, an AI tool such as Claude, Cursor or VS Code can read your handbooks: it searches, reads pages and names the source and the review status as it goes. This guide shows how to connect such a tool to your site and what it sees afterwards.

<details>
<summary>Concept: What the tool can do and what it cannot</summary>

Living Handbook offers seven capabilities, called "abilities" in WordPress. An AI tool uses them through the **MCP Adapter** plugin, which makes the connection.

* **For everyone:** list the handbooks, read the structure of a handbook, search, and read a page.
* **For editors** (whoever may edit other people's posts): pages whose review is due or missing, pages with many "not helpful" votes, and the most frequent searches without a result.

All seven only read. The tool cannot create, change or delete anything.

The tool signs in with a WordPress account and sees exactly what that account sees on the site. There are three exceptions where it sees less:

* Pages with **Exclude this page from AI use** ticked are always left out.
* Pages with a password are always left out.
* In handbooks that are not public, the addresses of uploaded files are replaced by the link text or the image description.

The search is the same as on the site: it finds words as they are written, without synonyms and without tolerance for typos. A good tool therefore searches several times with other words.

</details>

## Before you start

* WordPress 6.9 or newer. On older versions Living Handbook offers no abilities.
* The **MCP Adapter** plugin, installed and active under **Plugins → Add New Plugin**.
* The site runs on HTTPS. Otherwise WordPress offers no application passwords.

## Steps

1. **Agree in the team what is acceptable.** The content of every page the tool reads goes to the provider of the AI tool. Mark pages with personal data, such as a directory with contact details, in the editor's **Handbook maintenance** box with **Exclude this page from AI use**.
2. **Create an account of its own for the tool,** with the smallest role that will do. For reading, **Subscriber** is enough, plus access to the restricted handbooks the tool should read. For the editors' abilities the account needs the **Editor** role. Do not use an administrator account: it reads every handbook, and so would the tool.
3. **Create an application password.** Under **Users**, open that account's profile and scroll to **Application Passwords**. Enter a name such as "Claude on my laptop" and click **Add New Application Password**. Copy the password at once; WordPress shows it only once.
4. **Add the site to the AI tool.** The address is your site's address followed by `/wp-json/mcp/mcp-adapter-default-server`. In Claude Desktop, open the file under **Settings → Developer → Edit Config** and add this entry with your own values:

	```json
	{
	  "mcpServers": {
	    "handbook": {
	      "command": "npx",
	      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
	      "env": {
	        "WP_API_URL": "https://example.com/wp-json/mcp/mcp-adapter-default-server",
	        "WP_API_USERNAME": "ai-account",
	        "WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
	      }
	    }
	  }
	}
	```

	This needs Node.js on your computer. Other tools and the direct connection over HTTP are described in the [MCP Adapter's guide](https://github.com/WordPress/mcp-adapter/blob/trunk/docs/getting-started/site-owners.md).
5. **Restart the tool and test.** Ask, for example: "Which handbooks can you read on my site?" The tool should name the handbooks the account may see.

## Result

You can ask your AI tool what the handbook says and get answers with a link to the source page and its review status. As an editor you ask, for instance: "Which pages do I need to review this week?" or "What do people search for without finding it?"

<details>
<summary>Note: When the tool finds nothing</summary>

* **The list of abilities is empty:** check that Living Handbook and MCP Adapter are both active and WordPress is at least 6.9.
* **Error 401:** the username or the application password is wrong, or the server does not pass the `Authorization` header on to WordPress. In the second case, ask your host.
* **A handbook is missing:** the account may not read it. Grant it to this account under **Handbook → Handbooks**.
* **A page is missing:** it is excluded from AI use, has a password or is not published.

</details>

<details>
<summary>Note: Searches without a result</summary>

When a search finds nothing, Living Handbook counts the search term, so the editors see what is missing. Only the term, the handbook, the calendar week and whether the search came from the site or from the AI tool are stored, nothing about the person. The counts are deleted after 90 days. You can switch this off in [the settings](../the-settings.md) under **Feedback**.

</details>

## Related pages

* [Understanding access](understanding-access.md)
* [Set visibility](set-visibility.md)
* [The settings](../the-settings.md)

## Transport-Metadaten
* Seitentyp: Guide
* Slug: connect-an-ai-tool
* Verantwortliche Rolle: Handbook editors
* Thema: Access
* Zielgruppe: All members
* Eltern-Seite: Access
* Reihenfolge: 3
* Textauszug: How to connect an AI tool such as Claude to your handbooks, and what it sees afterwards and what it does not.
* Letzte Aktualisierung: 2026-11-09
* Letzte Prüfung: 2026-11-09
* Prüfintervall: 180 Tage
