# local_aimcp – AI MCP content tools

Service-neutral replacement for `local_claudemcp` + `local_claudemcpbook`. The plugin
exposes 17 web service functions (`local_aimcp_*`) for building course content. They are
meant to be used through the MCP web service protocol (`webservice_mcp`) from ChatGPT, Claude or
other MCP clients.

Requires Moodle 5.2.

## Functions

Activities: `create_page`, `create_label`, `create_url`, `create_forum`, `create_assign`,
`create_quiz`, `add_gift_questions`, `create_subsection`, `delete_subsection`,
`set_completion`, `update_activity`, `update_section`.

Book: `create_book`, `add_book_chapter`, `update_book_chapter`, `delete_book_chapter`,
`get_book_chapters`.

## Services created on install

| Service | Short name | Contents |
|---|---|---|
| AI-assistenter – interne (MCP) | `aimcp_internal` | All 17 tools plus core read, create and edit functions, deleting categories and courses, and enrolment (47 functions) |
| AI-assistenter – eksterne (MCP) | `aimcp_external` | The 17 tools plus core read, create and edit functions. It cannot delete categories or courses, enrol users or use `core_courseformat_update_course` (38 functions) |

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
