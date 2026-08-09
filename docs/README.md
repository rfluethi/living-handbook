# Documentation

Four handbooks live here, and all four ship inside the plugin. **Handbook → Import → App handbook** loads any of them into a WordPress site, each into a handbook you pick, so the version a site reads always matches the version it runs.

| Folder | What it is | For whom |
| --- | --- | --- |
| [`user/de/`](user/de/living-handbook/README.md) | User handbook, German | The people who write and read a handbook |
| [`user/en/`](user/en/living-handbook/README.md) | User handbook, English | The same, in English |
| [`technical/de/`](technical/de/README.md) | Technical documentation, German | Whoever installs, styles or extends the plugin |
| [`technical/en/`](technical/en/README.md) | Technical documentation, English | The same, in English |

Start with [`technical/en/README.md`](technical/en/README.md) if you are here to work on the code: it has a reading order, from a plain-language code overview to the release process.

## Why they sit together

They did not, until 0.69.0: the user handbook lived under `docs/user/` and the technical documentation under `docs/` and `docs/technical/de/`. That was two roots for the same kind of thing, a German folder name in an English repository, and the language once in the path and once in the folder name. It had grown rather than been designed, and once all four shipped and were offered in one dropdown, the reason for two roots was gone.

## Two rules for editing

- **This is the source.** Nothing here is generated, and no copy of it is kept anywhere else. A change belongs in a commit.
- **The German and the English version stay in step.** They are separate texts, not a translation pipeline, so a change to one is a change to both. A German page that says something the English one does not is how the two start drifting apart, and that has cost this project a day before.
