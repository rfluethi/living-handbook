# App handbook images

Images referenced by the app handbook. Put files here and reference them from
`bin/build-app-handbook.py` with `img("name.png", "alt text")`; the generator
fails if a referenced file is missing.

- **PNG or JPEG, not SVG.** WordPress rejects SVG uploads by default, so a
  shipped SVG would silently never arrive in the media library.
- **Crop tightly.** One panel, one meta box, one screen region. A full browser
  window is heavy, hard to read on a phone, and goes stale faster because more
  of the interface is in the picture.
- **One file per language when interface text is visible**, because the admin
  interface is translated. Name them accordingly, for example
  `page-list-de.png` and `page-list-en.png`.
- **Every image needs alt text.** It is an argument of `img()`, not an option.

Files here ship inside the plugin zip, so keep them small. On load they are
sideloaded into the media library and recognised again by a content hash, so
loading the handbook twice does not create duplicates. They do stay in the media
library when the handbook itself is deleted.
