# Writing content

This handbook comes from Markdown files on GitHub. On import, Markdown becomes HTML and is then cleaned. That decides what survives.

## What works

- Headings, paragraphs, lists, tables, quotes, bold and italic.
- Links, including relative `.md` links to other pages of the handbook. They are pointed at the right page on import.
- Images. They are sideloaded into the media library.
- Mermaid diagrams in a code block with the language `mermaid`. There is an example on [The review cycle](../upkeep/the-review-cycle.md).

## What does not work

The plugin's own blocks (the area list, feedback, badges, page metadata) cannot be expressed in Markdown. They are technically HTML comments, and the cleaning removes comments. If you write such a block into a Markdown file, it vanishes on import.

You do not need to place two of them yourself: the navigation tree on the left is built from the folder structure, and an area page without its own file gets its card list automatically.
