<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_panopto;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/panopto/lib/lti/panoptoblock_lti_utility.php');

/**
 * Unit tests for playback-launch custom parameter filtering.
 *
 * @package    block_panopto
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class panoptoblock_lti_utility_test extends \advanced_testcase {
    /**
     * Editor launches keep the session id and the viewer flag, and nothing else.
     *
     * @return void
     * @covers \panoptoblock_lti_utility::filter_launch_custom_parameters
     */
    public function test_filter_keeps_editor_content_item_parameters(): void {
        $sessionid = '6f1e4c2a-9b8d-4e7f-a1c2-0123456789ab';
        $raw = json_encode([
            'context_delivery' => $sessionid,
            'use_panopto_interactive_view' => true,
            'panopto_student_submission_tool' => 'true',
            'grading_not_supported' => false,
            'nested' => ['role' => 'Instructor'],
        ]);

        $filtered = \panoptoblock_lti_utility::filter_launch_custom_parameters($raw, 'tiny_panoptoltibutton');

        $this->assertEquals((object) [
            'context_delivery' => $sessionid,
            'use_panopto_interactive_view' => 'true',
        ], $filtered);
    }

    /**
     * A playlist embed keeps its playlist id.
     *
     * @return void
     * @covers \panoptoblock_lti_utility::filter_launch_custom_parameters
     */
    public function test_filter_keeps_playlist_id(): void {
        $playlistid = '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';
        $raw = json_encode([
            'context_playlist' => $playlistid,
            'use_panopto_interactive_view' => 'false',
        ]);

        $filtered = \panoptoblock_lti_utility::filter_launch_custom_parameters($raw, 'atto_panoptoltibutton');

        $this->assertEquals((object) [
            'context_playlist' => $playlistid,
            'use_panopto_interactive_view' => 'false',
        ], $filtered);

        $badguid = json_encode(['context_playlist' => 'not-a-playlist']);
        $this->assertEquals(
            new \stdClass(),
            \panoptoblock_lti_utility::filter_launch_custom_parameters($badguid, 'mod_panoptocourseembed')
        );
    }

    /**
     * A value that is not a session id or a boolean flag is dropped, including non-objects.
     *
     * @return void
     * @covers \panoptoblock_lti_utility::filter_launch_custom_parameters
     */
    public function test_filter_drops_malformed_payloads(): void {
        $badguid = json_encode(['context_delivery' => 'not-a-session']);
        $this->assertEquals(
            new \stdClass(),
            \panoptoblock_lti_utility::filter_launch_custom_parameters($badguid, 'tiny_panoptoltibutton')
        );

        $this->assertEquals(
            new \stdClass(),
            \panoptoblock_lti_utility::filter_launch_custom_parameters('["x"]', 'tiny_panoptoltibutton')
        );
        $this->assertEquals(
            new \stdClass(),
            \panoptoblock_lti_utility::filter_launch_custom_parameters('{', 'atto_panoptoltibutton')
        );
    }

    /**
     * Course embed and student submission do not echo the interactive viewer flag.
     *
     * @return void
     * @covers \panoptoblock_lti_utility::filter_launch_custom_parameters
     */
    public function test_filter_submission_omits_interactive_view(): void {
        $sessionid = '6f1e4c2a-9b8d-4e7f-a1c2-0123456789ab';
        $raw = json_encode([
            'context_delivery' => $sessionid,
            'use_panopto_interactive_view' => 'true',
            'use_panopto_sandbox' => 'true',
        ]);

        $filtered = \panoptoblock_lti_utility::filter_launch_custom_parameters($raw, 'mod_panoptosubmission');

        $this->assertEquals((object) ['context_delivery' => $sessionid], $filtered);
    }

    /**
     * A comma in the content URL or the custom JSON survives the hint round trip.
     *
     * @return void
     * @covers \panoptoblock_lti_utility::build_playback_launch_hint
     * @covers \panoptoblock_lti_utility::parse_playback_launch_hint
     */
    public function test_hint_round_trip_keeps_commas(): void {
        $sessionid = '6f1e4c2a-9b8d-4e7f-a1c2-0123456789ab';
        $custom = \panoptoblock_lti_utility::filter_launch_custom_parameters(json_encode([
            'context_delivery' => $sessionid,
            'use_panopto_interactive_view' => 'TRUE',
            'folio_embed' => 'true',
        ]), 'tiny_panoptoltibutton');
        $contenturl = 'https://example.panopto.com/Panopto/LTI/LTI.aspx?a=1,2';

        $hint = \panoptoblock_lti_utility::build_playback_launch_hint(
            'tiny_panoptoltibutton',
            '',
            12,
            'abc123',
            $contenturl,
            $custom
        );

        $this->assertStringStartsWith('tiny_panoptoltibutton,{', $hint);

        [$pluginname, $callback, $toolid, $resourcelinkid, $parsedurl, $parsedcustom] =
            \panoptoblock_lti_utility::parse_playback_launch_hint($hint);

        $this->assertSame('tiny_panoptoltibutton', $pluginname);
        $this->assertSame('', $callback);
        $this->assertSame('12', (string) $toolid);
        $this->assertSame('abc123', $resourcelinkid);
        $this->assertSame($contenturl, $parsedurl);
        $this->assertEquals((object) [
            'context_delivery' => $sessionid,
            'use_panopto_interactive_view' => 'true',
        ], $parsedcustom);
    }

    /**
     * A legacy hint whose JSON contains commas is rejoined, then filtered.
     *
     * @return void
     * @covers \panoptoblock_lti_utility::parse_playback_launch_hint
     */
    public function test_legacy_hint_rejoins_custom_json(): void {
        $sessionid = '6f1e4c2a-9b8d-4e7f-a1c2-0123456789ab';
        $custom = json_encode([
            'context_delivery' => $sessionid,
            'panopto_course_embed_tool' => 'true',
        ]);
        $hint = "mod_panoptocourseembed,'',9,linkid,https://example.panopto.com/Panopto/LTI/LTI.aspx,{$custom}";

        [$pluginname, $callback, $toolid, $resourcelinkid, $contenturl, $parsedcustom] =
            \panoptoblock_lti_utility::parse_playback_launch_hint($hint);

        $this->assertSame('mod_panoptocourseembed', $pluginname);
        $this->assertSame("''", $callback);
        $this->assertSame('9', $toolid);
        $this->assertSame('linkid', $resourcelinkid);
        $this->assertSame('https://example.panopto.com/Panopto/LTI/LTI.aspx', $contenturl);
        $this->assertEquals((object) ['context_delivery' => $sessionid], $parsedcustom);
    }

    /**
     * A content-item hint is only a plugin name and a callback. Parsing it must not invent a tool id.
     *
     * @return void
     * @covers \panoptoblock_lti_utility::parse_playback_launch_hint
     */
    public function test_content_item_hint_stays_a_selection_request(): void {
        [$pluginname, $callback, $toolid] = \panoptoblock_lti_utility::parse_playback_launch_hint(
            'tiny_panoptoltibutton, handleContent'
        );

        $this->assertSame('tiny_panoptoltibutton', $pluginname);
        $this->assertSame('handleContent', $callback);
        $this->assertSame('', $toolid);
    }
}
