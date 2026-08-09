# User handbook

The documentation of the app, written as a Living Handbook, in [German](de/living-handbook/README.md) and [English](en/living-handbook/README.md). It is for the people who write and read a handbook: creating one, writing pages, setting who may read them, keeping them reviewed.

Both folders ship inside the plugin and are imported by **Handbook → Import → App handbook**. That is also why neither has a `README.md` of its own directly under `de/` or `en/`: the import reads such a file as a page, and an extra page called "Readme" at the top of the handbook is not what anyone wants. The index of each handbook is one level down, in `living-handbook/README.md`.

The pages carry a transport block at the foot with their page type, topic and review data. It is what the import reads; leave it in place when editing.
