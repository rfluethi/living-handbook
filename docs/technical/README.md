# Technical documentation

For whoever installs, styles or extends the plugin: [German](de/living-handbook-technical/README.md), [English](en/living-handbook-technical/README.md).

The English folder is the one to start from. Each folder holds its own copy of the images in `_attachments/`, because the import only reads files inside the folder it loads, so a German page pointing into the English folder would import without its pictures. Both ship inside the plugin and can be loaded into a WordPress site through **Handbook → Import → App handbook**, so a site can read them without leaving the admin. Like the user handbook, each sits one level down in a folder of its own, `living-handbook-technical/`, which is what gives it a start page on import rather than a dozen loose pages; see [`../README.md`](../README.md).

The two languages are separate texts rather than a translation pipeline, which means a change to one is a change to both. Neither is the source of the other: both are.
