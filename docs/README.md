# Documentation

Four handbooks live here, and all four ship inside the plugin. **Handbook → Import → App handbook** loads any of them into a WordPress site, each into a handbook you pick, so the version a site reads always matches the version it runs.

| Folder | What it is | For whom |
| --- | --- | --- |
| [`user/de/`](user/de/living-handbook/README.md) | User handbook, German | The people who write and read a handbook |
| [`user/en/`](user/en/living-handbook/README.md) | User handbook, English | The same, in English |
| [`technical/de/`](technical/de/living-handbook-technical/README.md) | Technical documentation, German | Whoever installs, styles or extends the plugin |
| [`technical/en/`](technical/en/living-handbook-technical/README.md) | Technical documentation, English | The same, in English |

Start with [`technical/en/`](technical/en/living-handbook-technical/README.md) if you are here to work on the code: it has a reading order, from a plain-language code overview to the release process.

## The shape, and why every folder has it

All four have the same form: `docs/<kind>/<language>/<handbook>/`. The last level matters, it is not decoration. The import reads the language folder, and what lies **directly** in it becomes a page of its own at the top level, while a subfolder with a `README.md` becomes **one** page with everything else below it. The last level is therefore the handbook's own start page: `living-handbook` for the user handbook, `living-handbook-technical` for the technical documentation, in both languages.

Until 0.70.0 only the user handbook had that level, so the technical documentation imported as a dozen pages side by side with no entry page, and its `README.md` landed among them instead of above them.

**This is also why the four language folders have no `README.md` of their own.** One there would be read by the import as a page, and a page called "Readme" at the top of a handbook is not what anyone wants. The index of each handbook is one level down, inside the handbook folder. Nothing imports `docs/`, `docs/user/` or `docs/technical/`, so those three do have one.

## Two rules for editing

- **This is the source.** Nothing here is generated, and no copy of it is kept anywhere else. A change belongs in a commit.
- **The German and the English version stay in step.** They are separate texts, not a translation pipeline, so a change to one is a change to both. A German page that says something the English one does not is how the two start drifting apart, and that has cost this project a day before.
