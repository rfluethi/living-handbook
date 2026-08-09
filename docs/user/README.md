# User handbook

The documentation of the app, written as a Living Handbook, in [German](de/living-handbook/README.md) and [English](en/living-handbook/README.md). It is for the people who write and read a handbook: creating one, writing pages, setting who may read them, keeping them reviewed.

Both ship inside the plugin and are imported by **Handbook → Import → App handbook**. The handbook itself is one level down, in `living-handbook/`, which is what makes it import as a single start page with everything below it; the shape and the reason are in [`../README.md`](../README.md).

The pages carry a transport block at the foot with their page type, topic and review data. It is what the import reads; leave it in place when editing.
