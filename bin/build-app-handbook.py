#!/usr/bin/env python3
"""Build the app handbook files the plugin ships.

The app handbook is a normal Living Handbook bundle, minus the ZIP and minus the
media folder that a real export carries, so AppHandbook can hand it straight to
HandbookImport::import_manifest(). This script is the source of truth for its
content; the JSON files under assets/app-handbook/ are generated and should not
be edited by hand, because writing block markup inside JSON string literals is a
good way to ship broken pages.

Run it from anywhere:

    python3 bin/build-app-handbook.py

It writes assets/app-handbook/app-handbook-en.json and -de.json, and fails
loudly if a page references an image that is not in assets/app-handbook/media/.

Two rules the content has to follow, both because the plugin is translated:

- Vocabulary terms are referenced by token, never by slug. The seeded terms are
  translated when they are created, so their slugs depend on the site language.
  AppHandbook::tokens() maps a token to the term that actually exists. Adding a
  token here means adding it there too.
- Review dates are an age in days, not a date. A fixed date would make every
  page overdue a year after release.

Both language files must describe the same structure: the same keys, the same
parents, the same order, the same terms and the same review ages. Only the prose
and the handbook name differ. A test (tests/Integration/AppHandbookTest.php)
holds that rule down.

How to add a page:

1. Add its text to EN and to DE, under the same key.
2. Add a line to STRUCTURE: key, parent key, order, page type token, audience
   tokens, topic tokens, review age in days, review interval in days. Use None
   for the last two if the page should carry no review data.
3. Run this script, then run the tests.

How to add an image:

1. Put the file in assets/app-handbook/media/, as PNG or JPEG. Not SVG:
   WordPress rejects SVG uploads by default, so it would silently not arrive.
2. Reference it in the page body with img("name.png", "alt text"). The alt text
   is not optional; a screenshot without one is useless to a screen reader.
3. If the picture shows translated interface text, ship two files and use a
   different name per language.

Images are sideloaded into the media library on load and are recognised again by
a content hash, so loading twice does not duplicate them. They do stay in the
media library when the handbook is deleted.
"""

import json
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "assets", "app-handbook")
MEDIA_DIR = os.path.join(OUT, "media")

# Placeholder scheme for a shipped image. The importer replaces it with the URL
# of the sideloaded copy before the content is stored, so it never reaches the
# database.
MEDIA_SCHEME = "lh-app-handbook://"


# --- block helpers ---------------------------------------------------------

def p(text):
    return "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->" % text


def h(level, text):
    return ('<!-- wp:heading {"level":%d} -->\n<h%d class="wp-block-heading">%s</h%d>\n'
            "<!-- /wp:heading -->" % (level, level, text, level))


def ul(items):
    body = "\n".join("<!-- wp:list-item -->\n<li>%s</li>\n<!-- /wp:list-item -->" % i for i in items)
    return "<!-- wp:list -->\n<ul class=\"wp-block-list\">\n%s\n</ul>\n<!-- /wp:list -->" % body


def ol(items):
    body = "\n".join("<!-- wp:list-item -->\n<li>%s</li>\n<!-- /wp:list-item -->" % i for i in items)
    return ('<!-- wp:list {"ordered":true} -->\n<ol class="wp-block-list">\n%s\n</ol>\n'
            "<!-- /wp:list -->" % body)


def quote(text, cite):
    return ('<!-- wp:quote -->\n<blockquote class="wp-block-quote">'
            "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->"
            "<cite>%s</cite></blockquote>\n<!-- /wp:quote -->" % (text, cite))


def block(name, attrs=None):
    if attrs:
        return "<!-- wp:living-handbook/%s %s /-->" % (name, json.dumps(attrs, ensure_ascii=False))
    return "<!-- wp:living-handbook/%s /-->" % name


def img(file_name, alt, caption=None):
    """An image block pointing at a file in assets/app-handbook/media/."""
    src = MEDIA_SCHEME + file_name
    figcaption = ""
    if caption:
        figcaption = '<figcaption class="wp-element-caption">%s</figcaption>' % caption
    return ('<!-- wp:image {"sizeSlug":"large"} -->\n'
            '<figure class="wp-block-image size-large">'
            '<img src="%s" alt="%s"/>%s</figure>\n'
            "<!-- /wp:image -->" % (src, alt, figcaption))


def join(*parts):
    return "\n\n".join(parts)


# --- the strings, per language --------------------------------------------

EN = {
    "handbook": {"name": "App handbook", "slug": "app-handbook"},
    "pages": {
        "getting-started": {
            "title": "Getting started",
            "body": join(
                p("Welcome. This is the handbook of the plugin itself: it explains how a "
                  "Living Handbook is put together, and it is at the same time an example of "
                  "one. Everything here is ordinary content: read it, change it, or delete "
                  "the whole handbook when you no longer need it."),
                p("A handbook is made of <strong>areas</strong>, and each area holds "
                  "<strong>pages</strong>. This page is an area overview: it says what the "
                  "area is for, and then lists what is inside it."),
                block("entry", {"display": "cards"}),
            ),
        },
        "getting-started/how-this-handbook-is-organised": {
            "title": "How this handbook is organised",
            "body": join(
                block("toc", {"variant": "desktop"}),
                p("Three things give a handbook its shape: the hierarchy, the page type, "
                  "and the vocabularies. They do different jobs, and it helps to keep them "
                  "apart."),
                h(2, "Hierarchy"),
                p("Pages sit inside areas, and areas sit inside a handbook. That is the "
                  "path a reader walks. Keep it shallow: two levels are enough for almost "
                  "every team, and a third level usually means an area is trying to be two "
                  "areas."),
                h(2, "Page type"),
                p("The page type says what kind of text this is: a guide, a process "
                  "description, a tool overview, a role description, background, or an FAQ. "
                  "A reader who knows the type before reading knows what to expect from it. "
                  "One page, one type. If a page needs two, it is two pages."),
                h(2, "Vocabularies"),
                p("Topic, role and audience cut across the hierarchy. A page about "
                  "onboarding lives in one area, but it may matter to several roles. That is "
                  "what the vocabularies are for, and it is why the filters on the overview "
                  "can find pages the menu would not lead you to."),
                h(2, "Who looks after a page"),
                p("Every page can name a reviewer and a review interval. That is the whole "
                  "mechanism behind the freshness badges, and it is the reason a handbook "
                  "does not quietly rot."),
                block("pagemeta"),
            ),
        },
        "getting-started/writing-a-good-page": {
            "title": "Writing a good page",
            "body": join(
                block("toc", {"variant": "desktop"}),
                p("A handbook page is not an essay. Someone opens it in the middle of doing "
                  "something else, reads as little as possible, and leaves. Write for that "
                  "person."),
                h(2, "Start with the answer"),
                p("Put the conclusion in the first sentence and the reasoning after it. "
                  "Readers who already trust the answer stop there; readers who need to "
                  "understand it keep going. The reverse order makes everyone read "
                  "everything."),
                h(2, "One page, one job"),
                p("If you cannot say what a page is for in a single sentence, it is doing "
                  "more than one job and should be split."),
                h(2, "Name the owner, not just the content"),
                p("A page nobody owns is a page nobody corrects. The reviewer field is not "
                  "bureaucracy: it is the name of the person who gets asked when the page "
                  "turns out to be wrong."),
                h(2, "A short checklist"),
                ul([
                    "The title says what the reader gets, not what the topic is.",
                    "The first paragraph answers the question on its own.",
                    "Headings are scannable: someone reading only them still gets the shape.",
                    "There is a page type, a reviewer, and a review interval.",
                    "Links point at pages, not at people's memories.",
                ]),
                quote("If a page has been right for two years without anyone checking, "
                      "you were lucky, not organised.", "The point of the review interval"),
                p("Below is the feedback block. Readers use it to say whether a page helped, "
                  "and the handbook overview can sort by the difference between yes and no, "
                  "which is a fast way to find the pages that need work."),
                block("feedback"),
            ),
        },
        "keeping-content-current": {
            "title": "Keeping content alive",
            "body": join(
                p("A handbook is easy to start and hard to keep true. This area is about the "
                  "second part: where content comes from, and how it stays current."),
                block("entry", {"display": "cards"}),
            ),
        },
        "keeping-content-current/getting-content-in": {
            "title": "Getting content in",
            "body": join(
                p("Content reaches a handbook in four ways. They differ in one thing that "
                  "matters more than convenience: where the content is maintained afterwards."),
                h(2, "Paste text"),
                p("A Markdown draft pasted into the import screen becomes an editable page. "
                  "From then on it lives in WordPress and is edited there. Good for a single "
                  "document you have lying around."),
                h(2, "ZIP file"),
                p("A ZIP of Markdown files becomes one page per file. If the ZIP carries a "
                  "<code>mkdocs.yml</code>, its navigation decides titles, order and nesting, "
                  "so a documentation site keeps its shape. Also maintained in WordPress "
                  "afterwards."),
                h(2, "GitHub"),
                p("A page can be tied to a Markdown file in a repository. This is the one "
                  "case where the content is <em>not</em> maintained here: the repository is "
                  "the original, the page is a copy that is refreshed, and the editor is "
                  "locked so nobody edits a copy by mistake. Use it when a text already has a "
                  "home in a repository and should keep it."),
                h(2, "Bundle"),
                p("A whole handbook, or one area of it, exported from another site running "
                  "the plugin: pages, hierarchy, vocabularies, review data and media in one "
                  "file. This is how a handbook moves between sites. On import you decide "
                  "what happens to pages that already exist, and nothing is ever deleted."),
                h(2, "Choosing"),
                p("Ask where the text should be corrected in a year. If the answer is "
                  "\"in the repository\", use GitHub. In every other case the content belongs "
                  "in WordPress, and the other three paths only differ in how much of it "
                  "arrives at once."),
                block("pagemeta"),
            ),
        },
        "keeping-content-current/the-review-cycle": {
            "title": "The review cycle",
            "body": join(
                p("Every page carries a review interval. When it runs out, the page shows a "
                  "badge and appears in the dashboard widget. Nothing is deleted and nothing "
                  "is hidden: the handbook simply stops pretending the page is current."),
                block("mermaid", {
                    "code": "graph TD;\n"
                            "  A[Page reviewed] --> B[Interval running];\n"
                            "  B --> C{Interval over?};\n"
                            "  C -->|No| B;\n"
                            "  C -->|Yes| D[Badge: review due];\n"
                            "  D --> E{Twice the interval?};\n"
                            "  E -->|Yes| F[Badge: review overdue];\n"
                            "  D --> G[Reviewer checks the page];\n"
                            "  F --> G;\n"
                            "  G --> A;",
                    "title": "From a review to the next one",
                    "description": "A reviewed page runs through its interval. When the "
                                   "interval is over the page is marked as due, and after "
                                   "twice the interval as overdue. A review sets the date "
                                   "again and the cycle starts over.",
                }),
                h(2, "Choosing an interval"),
                p("Short intervals on pages that never change produce noise, and noise "
                  "teaches people to ignore badges. Twelve months is a sensible default. Use "
                  "three months only where being out of date does real damage, such as "
                  "access rules or anything with a legal edge."),
                h(2, "What a review actually is"),
                p("Reading the page and asking one question: would I write this the same way "
                  "today? If yes, confirm the date and stop. A review that always turns into "
                  "a rewrite is a sign the interval is too long, not that the reviewer is "
                  "thorough."),
                block("badges"),
            ),
        },
        "reference": {
            "title": "Reference",
            "body": join(
                p("Things you look up rather than read: answers to recurring questions, and "
                  "the tools the team uses."),
                block("entry", {"display": "list"}),
            ),
        },
        "reference/frequently-asked-questions": {
            "title": "Frequently asked questions",
            "body": join(
                h(2, "Can I delete this handbook?"),
                p("Yes. Delete its pages and then the handbook itself under Handbooks. "
                  "Nothing else in the plugin depends on it."),
                h(2, "Who can see a handbook?"),
                p("Each handbook has its own visibility: public, members, or restricted to "
                  "chosen roles and people. A handbook created by an import always starts at "
                  "members, even if the bundle said public, so nothing is published by "
                  "accident."),
                h(2, "What is the difference between a page and an area?"),
                p("Technically nothing: an area is a page without a parent. Practically an "
                  "area should be an overview that helps a reader choose, not a place to put "
                  "content that did not fit anywhere else."),
                h(2, "Can content come from GitHub?"),
                p("Yes. A page can be tied to a Markdown file in a repository and is then "
                  "kept in sync from there. Such a page is maintained in the repository, not "
                  "in WordPress, and is marked accordingly."),
                h(2, "What happens when I import something that already exists?"),
                p("You choose: skip, update, or always create new. A page can also be marked "
                  "as protected, in which case an import never touches it. Imports never "
                  "delete anything."),
            ),
        },
        "reference/the-blocks": {
            "title": "The blocks you can use",
            "body": join(
                p("The plugin adds its own blocks to the editor. Most of them build "
                  "themselves from the page they sit on, so they need no configuration and "
                  "stay correct when the handbook changes around them."),
                h(2, "On an area page"),
                ul([
                    "<strong>Area entries</strong> lists the pages inside the area, as cards "
                    "or as a list. Two of the area pages in this handbook use it.",
                    "<strong>Handbook overview</strong> lists all handbooks. It sits on the "
                    "page the plugin created for you on activation.",
                ]),
                h(2, "On a content page"),
                ul([
                    "<strong>On this page</strong> builds a table of contents from the "
                    "headings, to a depth you choose.",
                    "<strong>Freshness badges</strong> shows the review state.",
                    "<strong>Page metadata</strong> shows the page type, the reviewer and the "
                    "review date.",
                    "<strong>Was this helpful?</strong> collects yes and no, which the page "
                    "list can sort by.",
                    "<strong>Mermaid</strong> renders a diagram from text, with a written "
                    "description underneath so it is not lost on a screen reader.",
                ]),
                h(2, "Navigation and search"),
                ul([
                    "<strong>Handbook navigation</strong> is the page tree of one handbook.",
                    "<strong>Handbook menu</strong> and <strong>Search</strong> put the same "
                    "into a theme's own layout.",
                ]),
                p("This page deliberately has no reviewer and no review interval, so you can "
                  "see how such a page is shown in the handbook overview."),
            ),
        },
    },
}

DE = {
    "handbook": {"name": "App-Handbuch", "slug": "app-handbuch"},
    "pages": {
        "getting-started": {
            "title": "Erste Schritte",
            "body": join(
                p("Willkommen. Das ist das Handbuch des Plugins selbst: es erklärt, wie ein "
                  "Living Handbook aufgebaut ist, und ist zugleich ein Beispiel für eines. "
                  "Alles hier ist gewöhnlicher Inhalt: lies ihn, ändere ihn, oder lösche das "
                  "ganze Handbuch, wenn du es nicht mehr brauchst."),
                p("Ein Handbuch besteht aus <strong>Bereichen</strong>, und jeder Bereich "
                  "enthält <strong>Seiten</strong>. Diese Seite ist eine Bereichs-Übersicht: "
                  "sie sagt, wofür der Bereich da ist, und listet dann auf, was darin liegt."),
                block("entry", {"display": "cards"}),
            ),
        },
        "getting-started/how-this-handbook-is-organised": {
            "title": "Wie dieses Handbuch aufgebaut ist",
            "body": join(
                block("toc", {"variant": "desktop"}),
                p("Drei Dinge geben einem Handbuch seine Form: die Hierarchie, der Seitentyp "
                  "und die Vokabulare. Sie haben verschiedene Aufgaben, und es hilft, sie "
                  "auseinanderzuhalten."),
                h(2, "Hierarchie"),
                p("Seiten liegen in Bereichen, Bereiche in einem Handbuch. Das ist der Weg, "
                  "den eine lesende Person geht. Halte ihn flach: zwei Ebenen genügen fast "
                  "jedem Team, und eine dritte Ebene heisst meistens, dass ein Bereich "
                  "eigentlich zwei Bereiche sind."),
                h(2, "Seitentyp"),
                p("Der Seitentyp sagt, um welche Art Text es sich handelt: eine Anleitung, "
                  "eine Prozessbeschreibung, eine Tool-Übersicht, eine Rollenbeschreibung, "
                  "Hintergrund oder eine FAQ. Wer den Typ vor dem Lesen kennt, weiss, was "
                  "ihn erwartet. Eine Seite, ein Typ. Braucht eine Seite zwei, sind es zwei "
                  "Seiten."),
                h(2, "Vokabulare"),
                p("Thema, Rolle und Zielgruppe laufen quer zur Hierarchie. Eine Seite über "
                  "Onboarding liegt in einem Bereich, kann aber für mehrere Rollen wichtig "
                  "sein. Dafür sind die Vokabulare da, und darum finden die Filter in der "
                  "Übersicht Seiten, zu denen dich das Menü nicht führen würde."),
                h(2, "Wer eine Seite betreut"),
                p("Jede Seite kann eine prüfende Person und ein Prüfintervall benennen. Das "
                  "ist der ganze Mechanismus hinter den Aktualitäts-Abzeichen, und der "
                  "Grund, warum ein Handbuch nicht still vergammelt."),
                block("pagemeta"),
            ),
        },
        "getting-started/writing-a-good-page": {
            "title": "Eine gute Seite schreiben",
            "body": join(
                block("toc", {"variant": "desktop"}),
                p("Eine Handbuchseite ist kein Aufsatz. Jemand öffnet sie mitten in einer "
                  "anderen Tätigkeit, liest so wenig wie möglich und geht wieder. Schreib "
                  "für diese Person."),
                h(2, "Beginne mit der Antwort"),
                p("Die Schlussfolgerung gehört in den ersten Satz, die Begründung danach. "
                  "Wer der Antwort ohnehin traut, hört dort auf; wer sie verstehen muss, "
                  "liest weiter. Die umgekehrte Reihenfolge zwingt alle, alles zu lesen."),
                h(2, "Eine Seite, eine Aufgabe"),
                p("Wenn du nicht in einem Satz sagen kannst, wofür eine Seite da ist, macht "
                  "sie mehr als eine Sache und sollte geteilt werden."),
                h(2, "Nenne die zuständige Person, nicht nur den Inhalt"),
                p("Eine Seite ohne Zuständigkeit ist eine Seite, die niemand korrigiert. Das "
                  "Feld für die prüfende Person ist keine Bürokratie: es ist der Name der "
                  "Person, die gefragt wird, wenn sich die Seite als falsch herausstellt."),
                h(2, "Eine kurze Checkliste"),
                ul([
                    "Der Titel sagt, was die lesende Person bekommt, nicht wie das Thema heisst.",
                    "Der erste Absatz beantwortet die Frage für sich allein.",
                    "Die Überschriften sind überfliegbar: wer nur sie liest, erkennt die Form.",
                    "Es gibt einen Seitentyp, eine prüfende Person und ein Prüfintervall.",
                    "Links zeigen auf Seiten, nicht auf das Gedächtnis von Leuten.",
                ]),
                quote("Wenn eine Seite zwei Jahre lang richtig war, ohne dass jemand "
                      "nachgesehen hat, hattest du Glück, nicht Ordnung.",
                      "Wozu das Prüfintervall da ist"),
                p("Unten steht der Feedback-Block. Lesende sagen damit, ob eine Seite "
                  "geholfen hat, und die Handbuch-Übersicht kann nach der Differenz zwischen "
                  "Ja und Nein sortieren. Das ist ein schneller Weg zu den Seiten, die "
                  "Arbeit brauchen."),
                block("feedback"),
            ),
        },
        "keeping-content-current": {
            "title": "Inhalte am Leben halten",
            "body": join(
                p("Ein Handbuch ist leicht begonnen und schwer wahr gehalten. In diesem "
                  "Bereich geht es um den zweiten Teil: woher Inhalt kommt, und wie er "
                  "aktuell bleibt."),
                block("entry", {"display": "cards"}),
            ),
        },
        "keeping-content-current/getting-content-in": {
            "title": "Inhalte hereinholen",
            "body": join(
                p("Inhalt kommt auf vier Wegen ins Handbuch. Sie unterscheiden sich in einem "
                  "Punkt, der wichtiger ist als die Bequemlichkeit: wo der Inhalt danach "
                  "gepflegt wird."),
                h(2, "Text einfügen"),
                p("Ein Markdown-Entwurf, den du in die Import-Seite einfügst, wird zu einer "
                  "bearbeitbaren Seite. Ab dann lebt er in WordPress und wird dort "
                  "bearbeitet. Gut für ein einzelnes Dokument, das du herumliegen hast."),
                h(2, "ZIP-Datei"),
                p("Ein ZIP mit Markdown-Dateien wird zu einer Seite pro Datei. Enthält das "
                  "ZIP eine <code>mkdocs.yml</code>, bestimmt deren Navigation Titel, "
                  "Reihenfolge und Verschachtelung, eine Doku-Site behält also ihre Form. "
                  "Wird danach ebenfalls in WordPress gepflegt."),
                h(2, "GitHub"),
                p("Eine Seite kann an eine Markdown-Datei in einem Repository gebunden sein. "
                  "Das ist der einzige Fall, in dem der Inhalt <em>nicht</em> hier gepflegt "
                  "wird: das Repository ist das Original, die Seite eine Kopie, die "
                  "abgeglichen wird, und der Editor ist gesperrt, damit niemand aus Versehen "
                  "eine Kopie bearbeitet. Nimm das, wenn ein Text schon ein Zuhause in einem "
                  "Repository hat und es behalten soll."),
                h(2, "Bündel"),
                p("Ein ganzes Handbuch, oder ein Bereich davon, exportiert von einer anderen "
                  "Seite mit diesem Plugin: Seiten, Hierarchie, Vokabulare, Prüfdaten und "
                  "Medien in einer Datei. So wandert ein Handbuch zwischen Seiten. Beim "
                  "Import entscheidest du, was mit bereits vorhandenen Seiten geschieht, und "
                  "gelöscht wird grundsätzlich nichts."),
                h(2, "Die Wahl"),
                p("Frag dich, wo der Text in einem Jahr korrigiert werden soll. Lautet die "
                  "Antwort \"im Repository\", nimm GitHub. In jedem anderen Fall gehört der "
                  "Inhalt nach WordPress, und die anderen drei Wege unterscheiden sich nur "
                  "darin, wie viel davon auf einmal ankommt."),
                block("pagemeta"),
            ),
        },
        "keeping-content-current/the-review-cycle": {
            "title": "Der Prüfzyklus",
            "body": join(
                p("Jede Seite trägt ein Prüfintervall. Läuft es ab, zeigt die Seite ein "
                  "Abzeichen und erscheint im Dashboard-Widget. Nichts wird gelöscht und "
                  "nichts versteckt: das Handbuch hört bloss auf, so zu tun, als sei die "
                  "Seite aktuell."),
                block("mermaid", {
                    "code": "graph TD;\n"
                            "  A[Seite geprüft] --> B[Intervall läuft];\n"
                            "  B --> C{Intervall vorbei?};\n"
                            "  C -->|Nein| B;\n"
                            "  C -->|Ja| D[Abzeichen: Prüfung fällig];\n"
                            "  D --> E{Doppeltes Intervall?};\n"
                            "  E -->|Ja| F[Abzeichen: Prüfung überfällig];\n"
                            "  D --> G[Prüfende Person sieht nach];\n"
                            "  F --> G;\n"
                            "  G --> A;",
                    "title": "Von einer Prüfung zur nächsten",
                    "description": "Eine geprüfte Seite durchläuft ihr Intervall. Ist das "
                                   "Intervall vorbei, gilt die Seite als fällig, nach dem "
                                   "doppelten Intervall als überfällig. Eine Prüfung setzt "
                                   "das Datum neu, und der Zyklus beginnt von vorn.",
                }),
                h(2, "Ein Intervall wählen"),
                p("Kurze Intervalle auf Seiten, die sich nie ändern, erzeugen Rauschen, und "
                  "Rauschen bringt Leuten bei, Abzeichen zu übersehen. Zwölf Monate sind ein "
                  "vernünftiger Normalfall. Drei Monate nur dort, wo Veralten wirklich "
                  "Schaden anrichtet, etwa bei Zugriffsregeln oder allem mit rechtlicher "
                  "Kante."),
                h(2, "Was eine Prüfung wirklich ist"),
                p("Die Seite lesen und eine Frage stellen: würde ich das heute noch genauso "
                  "schreiben? Wenn ja, Datum bestätigen und fertig. Eine Prüfung, die immer "
                  "zur Überarbeitung wird, ist ein Zeichen für ein zu langes Intervall, "
                  "nicht für Gründlichkeit."),
                block("badges"),
            ),
        },
        "reference": {
            "title": "Nachschlagen",
            "body": join(
                p("Dinge, die man nachschlägt statt liest: Antworten auf wiederkehrende "
                  "Fragen, und die Werkzeuge des Teams."),
                block("entry", {"display": "list"}),
            ),
        },
        "reference/frequently-asked-questions": {
            "title": "Häufige Fragen",
            "body": join(
                h(2, "Kann ich dieses Handbuch löschen?"),
                p("Ja. Lösche seine Seiten und danach unter Handbücher das Handbuch selbst. "
                  "Nichts anderes im Plugin hängt daran."),
                h(2, "Wer sieht ein Handbuch?"),
                p("Jedes Handbuch hat seine eigene Sichtbarkeit: öffentlich, Mitglieder, "
                  "oder beschränkt auf gewählte Rollen und Personen. Ein Handbuch, das durch "
                  "einen Import entsteht, startet immer bei Mitglieder, auch wenn das Bündel "
                  "öffentlich sagte. So wird nichts versehentlich veröffentlicht."),
                h(2, "Was ist der Unterschied zwischen einer Seite und einem Bereich?"),
                p("Technisch keiner: ein Bereich ist eine Seite ohne Elternseite. Praktisch "
                  "sollte ein Bereich eine Übersicht sein, die beim Wählen hilft, und nicht "
                  "ein Ort für Inhalt, der sonst nirgends passte."),
                h(2, "Kann Inhalt von GitHub kommen?"),
                p("Ja. Eine Seite kann an eine Markdown-Datei in einem Repository gebunden "
                  "sein und wird von dort abgeglichen. Eine solche Seite wird im Repository "
                  "gepflegt, nicht in WordPress, und ist entsprechend gekennzeichnet."),
                h(2, "Was passiert, wenn ich etwas importiere, das es schon gibt?"),
                p("Du wählst: überspringen, aktualisieren, oder immer neu anlegen. Eine "
                  "Seite lässt sich zusätzlich als geschützt markieren, dann fasst ein "
                  "Import sie nie an. Ein Import löscht grundsätzlich nichts."),
            ),
        },
        "reference/the-blocks": {
            "title": "Die Blöcke, die du nutzen kannst",
            "body": join(
                p("Das Plugin bringt eigene Blöcke in den Editor mit. Die meisten bauen sich "
                  "aus der Seite auf, auf der sie stehen, brauchen also keine Einstellungen "
                  "und bleiben richtig, wenn sich das Handbuch um sie herum ändert."),
                h(2, "Auf einer Bereichsseite"),
                ul([
                    "<strong>Bereichs-Einträge</strong> listet die Seiten des Bereichs, als "
                    "Karten oder als Liste. Zwei Bereichsseiten in diesem Handbuch nutzen ihn.",
                    "<strong>Handbuch-Übersicht</strong> listet alle Handbücher. Er steht auf "
                    "der Seite, die das Plugin bei der Aktivierung für dich angelegt hat.",
                ]),
                h(2, "Auf einer Inhaltsseite"),
                ul([
                    "<strong>Auf dieser Seite</strong> baut ein Inhaltsverzeichnis aus den "
                    "Überschriften, bis zu einer Tiefe, die du wählst.",
                    "<strong>Aktualitäts-Abzeichen</strong> zeigt den Prüfzustand.",
                    "<strong>Seiten-Metadaten</strong> zeigt Seitentyp, prüfende Person und "
                    "Prüfdatum.",
                    "<strong>War das hilfreich?</strong> sammelt Ja und Nein, wonach die "
                    "Seitenliste sortieren kann.",
                    "<strong>Mermaid</strong> rendert ein Diagramm aus Text, mit einer "
                    "geschriebenen Beschreibung darunter, damit es am Screenreader nicht "
                    "verloren geht.",
                ]),
                h(2, "Navigation und Suche"),
                ul([
                    "<strong>Handbuch-Navigation</strong> ist der Seitenbaum eines Handbuchs.",
                    "<strong>Handbuch-Menü</strong> und <strong>Suche</strong> bringen "
                    "dasselbe in das Layout eines Themes.",
                ]),
                p("Diese Seite hat bewusst keine prüfende Person und kein Prüfintervall, "
                  "damit du siehst, wie eine solche Seite in der Handbuch-Übersicht "
                  "dargestellt wird."),
            ),
        },
    },
}


# --- structure shared by both languages ------------------------------------

STRUCTURE = [
    # key, parent, order, type token, audience tokens, topic tokens, review days ago, interval
    ("getting-started", "", 10, "area-overview", ["all-members"], [], None, None),
    ("getting-started/how-this-handbook-is-organised", "getting-started", 10,
     "background-concept", ["all-members"], ["documentation"], 10, 180),
    ("getting-started/writing-a-good-page", "getting-started", 20,
     "guide", ["content-creators"], ["documentation", "quality"], 200, 180),
    ("keeping-content-current", "", 20, "area-overview", ["all-members"], [], None, None),
    ("keeping-content-current/getting-content-in", "keeping-content-current", 10,
     "process-description", ["coordination"], ["import"], 400, 180),
    ("keeping-content-current/the-review-cycle", "keeping-content-current", 20,
     "process-description", ["content-creators"], ["quality"], 30, 90),
    ("reference", "", 30, "area-overview", ["all-members"], [], None, None),
    ("reference/frequently-asked-questions", "reference", 10,
     "faq", ["all-members"], [], 45, 365),
    ("reference/the-blocks", "reference", 20,
     "tool-overview", ["all-members"], [], None, None),
]

ROLES = {
    "getting-started/writing-a-good-page": ["handbook-editors"],
    "keeping-content-current/the-review-cycle": ["handbook-editors"],
}


def build(lang, strings):
    pages = []
    for key, parent, order, ptype, audience, topics, days, interval in STRUCTURE:
        text = strings["pages"][key]
        slug = key.split("/")[-1]
        page = {
            "key": key,
            "origin_id": "app-handbook:" + key,
            "parent_key": parent,
            "order": order,
            "title": text["title"],
            "slug": slug,
            "status": "publish",
            "source": "wordpress",
            "content": text["body"],
            "terms": {
                "handbook_type": [ptype],
                "handbook_audience": audience,
                "handbook_topic": topics,
                "handbook_role": ROLES.get(key, []),
            },
            "meta": {},
        }
        if days is not None:
            page["meta"]["review_days_ago"] = days
            page["meta"]["review_interval"] = interval
        pages.append(page)

    return {
        "format": "living-handbook-bundle",
        "version": 1,
        "app_handbook": True,
        "language": lang,
        "scope": "handbook",
        "handbook": {
            "slug": strings["handbook"]["slug"],
            "name": strings["handbook"]["name"],
            "visibility": "members",
            "roles": [],
        },
        "pages": pages,
        "media": media_manifest(pages),
    }


def media_manifest(pages):
    """The media list for one language, read back out of that language's pages.

    Collecting it from the finished content rather than from a registry keeps the
    two languages independent: a screenshot that exists only in the German pages
    is not shipped with the English ones.
    """
    used = set()
    for page in pages:
        for match in re.findall(re.escape(MEDIA_SCHEME) + r'([A-Za-z0-9._-]+)', page["content"]):
            used.add(match)

    entries = []
    for file_name in sorted(used):
        path = os.path.join(MEDIA_DIR, file_name)
        if not os.path.isfile(path):
            raise SystemExit(
                "Missing image: %s\nPut it in %s or remove the img() call." % (file_name, MEDIA_DIR)
            )
        entries.append({
            "file": os.path.join("media", file_name),
            "original_url": MEDIA_SCHEME + file_name,
        })
    return entries


os.makedirs(OUT, exist_ok=True)
for lang, strings in (("en", EN), ("de", DE)):
    path = os.path.join(OUT, "app-handbook-%s.json" % lang)
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(build(lang, strings), fh, ensure_ascii=False, indent=1)
        fh.write("\n")
    print("wrote", path, os.path.getsize(path), "bytes")
