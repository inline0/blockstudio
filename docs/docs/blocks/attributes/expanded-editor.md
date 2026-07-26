---
title: Expanded Editor
description: Edit Blockstudio fields in a wider drawer when the inspector sidebar is too narrow.
path: "blocks/attributes/expanded-editor"
order: 22
section: "Blocks"
subsection: "Field Editing"
meta_title: "Expanded Editor"
meta_description: "Edit Blockstudio fields in a wider drawer when the inspector sidebar is too narrow."
---

# Expanded Editor

Blockstudio fields are edited in the block inspector by default. For blocks with
repeaters, code fields, rich text, nested groups, or many extension fields, the
sidebar can become too narrow for comfortable editing.

The Expanded Editor opens the same field controls in a wider drawer from the
right side of the block editor.

## Opening the editor

Select a Blockstudio block that has fields and open the block inspector. The
block header shows an edit button with the label `Open Expanded Editor`.

The drawer can be closed with:

- the `Done` button
- the backdrop outside the drawer
- the `Esc` key

Closing the drawer keeps the selected block active.

## Field behavior

Expanded Editor uses the same `Fields` renderer as the normal inspector. Field
values, conditional logic, extension field groups, code fields, repeaters, and
persisted block attributes all follow the same path as sidebar edits.

Changes made in the drawer update the selected block immediately:

- the block preview updates while editing
- the inspector shows the same values after closing the drawer
- saved values persist after reloading the editor

No `block.json` setting is required. The action is shown automatically when the
selected Blockstudio block, or one of its registered extensions, has fields to
edit.
