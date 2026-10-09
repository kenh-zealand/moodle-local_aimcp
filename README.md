# local_aimcp – AI MCP content tools

Service-neutral replacement for `local_claudemcp` + `local_claudemcpbook`. The plugin
exposes 22 web service functions (`local_aimcp_*`) for building course content. They are
meant to be used through the MCP web service protocol (`webservice_mcp`) from ChatGPT, Claude or
other MCP clients.

Requires Moodle 5.2.

## Functions

Activities: `create_page`, `create_label`, `create_url`, `create_forum`, `create_assign`,
`create_quiz`, `add_gift_questions`, `create_subsection`, `delete_subsection`,
`set_completion`, `update_activity`, `update_section`.

Book: `create_book`, `add_book_chapter`, `update_book_chapter`, `delete_book_chapter`,
`get_book_chapters`.

Courses: `create_course`, `get_default_category`.

Grid tile images: `set_section_image`, `delete_section_image`, `get_section_images`.

## Grid tile images

These functions work on courses in the Grid format (`format_grid`). They store the image exactly as
the section settings form does: the original in `format_grid/sectionimage`, a row in
`format_grid_image` and a generated tile image. The alt text is the section setting
`sectionimagealttext`.

`set_section_image` takes one of three sources:

- `svg`: SVG markup. This is the practical choice for an AI assistant, since it can write SVG
  directly. The server renders it to a PNG (1140 px wide by default) with Imagick, so Imagick
  with SVG support is required. Use `viewBox="0 0 1140 600"` for a 1.9:1 tile. The SVG must be
  self-contained: scripts, event handlers, `<image>`, `<foreignObject>`, DOCTYPE and references
  outside the document are rejected.
- `imagedata`: base64 PNG, JPEG, GIF or WebP (max 5 MB).
- `imageurl`: a public http(s) URL. Moodle's curl security settings apply.

Send `alttext` alone to change only the alt text. The file name gets a short content hash, so
browsers show a replaced image at once. Both services include the three functions. The user needs
`moodle/course:update` in the course.

## Services created on install

| Service | Short name | Contents |
|---|---|---|
| AI-assistenter – interne (MCP) | `aimcp_internal` | All 22 tools plus core read, create and edit functions, deleting categories and courses, and enrolment (49 functions) |
| AI-assistenter – eksterne (MCP) | `aimcp_external` | The 22 tools plus core read, create and edit functions. It cannot delete categories or courses, enrol users or use `core_courseformat_update_course` (40 functions) |

Both services are limited to authorised users. Service names are unique. If you already made a
service by hand with either name, delete it before installing.

Because these services are defined by the plugin, you change their function lists in
`db/services.php` and then bump the version.

## Setup checklist

1. Web services and the MCP protocol are enabled.
2. Give each MCP user a system role that has `webservice/mcp:use`.
3. Add the user as an authorised user on the right service.
4. Create one token per AI service, e.g. "ChatGPT – Kenneth" and "Claude – Kenneth".
5. Endpoint: `https://<site>/webservice/mcp/server.php?wstoken=<token>`.
