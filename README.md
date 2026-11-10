# Panopto plugin for Moodle

A Moodle block that links each course to a Panopto folder, shows recordings in the course sidebar, signs users in to Panopto with single sign-on, and keeps enrolment and roles in sync.

## Who it is for

- **Administrators** configure the Panopto server and provision courses.
- **Teachers** use the block on the course page to open the course folder, launch the recorder, and see recent sessions.
- **Students** watch recordings they have permission to see, without a separate Panopto login in the usual setup.

This block is the foundation for the other Panopto Moodle plugins (course embed, student submission, and the editor buttons). Install and configure it first.

## Requirements

- A supported **Moodle** version. Each [GitHub release](https://github.com/Panopto/Moodle-2.0-Plugin-for-Panopto/releases) lists the Moodle versions that zip supports — check those notes and pick the release that matches your site.
- A Panopto site and a Moodle identity provider (instance name and application key) from Panopto
- PHP SOAP support on the Moodle server (used to talk to the Panopto API)

## Installation

1. Download a release from [GitHub](https://github.com/Panopto/Moodle-2.0-Plugin-for-Panopto/releases).
2. In Moodle, go to **Site administration → Plugins → Install plugins**.
3. Drop the zip onto the install page and complete the upgrade.
4. Confirm the plugin appears under **Site administration → Plugins → Blocks → Panopto**.

You can also copy the plugin into `blocks/panopto` on the server and visit **Site administration → Notifications**.

## First-time setup

1. Open **Site administration → Plugins → Blocks → Panopto**.
2. Add your Panopto server hostname (for example `demo.hosted.panopto.com`).
3. Enter the **instance name** and **application key** from Panopto’s identity providers page.
4. Save changes.
5. On each course that should use Panopto, turn editing on, add the **Panopto** block, and provision (or select) a Panopto folder.

Until a course is provisioned, the other Panopto plugins will not have a folder to launch into.

## Related plugins

These optional plugins all depend on this block:

| Plugin | What it adds |
| --- | --- |
| [Course embed](https://github.com/Panopto/moodle-mod_panoptocourseembed) | A course activity that embeds a Panopto session or folder |
| [Student submission](https://github.com/Panopto/Moodle-Panopto-Student-Submission) | An assignment-style activity for graded Panopto videos |
| [TinyMCE button](https://github.com/Panopto/moodle-tiny_panoptoltibutton) | Insert Panopto videos in the TinyMCE editor |
| [Atto button](https://github.com/Panopto/Panopto-LTI-Button-for-Atto) | The same for the Atto editor (Moodle 4.5 and earlier; use TinyMCE from Moodle 5.0) |

## Support

- Product documentation: [Panopto Support](https://support.panopto.com)
- Bugs and feature requests: [GitHub issues](https://github.com/Panopto/Moodle-2.0-Plugin-for-Panopto/issues)

Pull requests are welcome. If you are not a developer, please open an issue with what you expected and what happened.

## Credits

The original plugin was written by Panopto for Moodle 1.9. It was rewritten for Moodle 2 by [Spenser Jones](http://spenserjones.com) and is maintained as open source with the Panopto community.

## Copyright and licence

Copyright Panopto 2009–2026, with contributions from Spenser Jones, Hittesh Ahuja, and Tim Lock.

This plugin is free software under the [GNU GPL v3](http://www.gnu.org/licenses/) or later.
