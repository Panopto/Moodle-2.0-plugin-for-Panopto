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

namespace block_panopto\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/panopto/lib/block_panopto_lib.php');

/**
 * Tests for the block_panopto_get_content web service and the template that renders its data.
 *
 * @package    block_panopto
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_panopto\external\get_content
 */
final class get_content_test extends \advanced_testcase {
    /**
     * Call the web service and clean its result the way the external API does for an ajax call.
     *
     * @param int $courseid the course to load the block for
     * @return array the cleaned result
     */
    private function call_get_content(int $courseid): array {
        $result = get_content::execute($courseid);

        return \core_external\external_api::clean_returnvalue(get_content::execute_returns(), $result);
    }

    /**
     * A course with no Panopto folder offers the provision link to a user who can provision it.
     *
     * @return void
     */
    public function test_unprovisioned_course_offers_provisioning_to_teachers(): void {
        $this->resetAfterTest();
        set_config('auto_provision_new_courses', 'off', 'block_panopto');
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        $data = $this->call_get_content($course->id);

        $this->assertTrue($data['unprovisioned']);
        $this->assertFalse($data['showcontent']);
        $this->assertSame([], $data['errors']);
        $this->assertSame(
            (new \moodle_url('/blocks/panopto/provision_course_internal.php', ['id' => $course->id]))->out(false),
            $data['provisionurl']
        );
    }

    /**
     * A student sees that the course is not provisioned, without the link to provision it.
     *
     * @return void
     */
    public function test_unprovisioned_course_has_no_provision_link_for_students(): void {
        $this->resetAfterTest();
        set_config('auto_provision_new_courses', 'off', 'block_panopto');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $data = $this->call_get_content($course->id);

        $this->assertTrue($data['unprovisioned']);
        $this->assertSame('', $data['provisionurl']);
    }

    /**
     * Data shaped like a provisioned course passes the external API's return value check unchanged.
     *
     * Return values are checked strictly, so one value that fails its type would fail the whole call.
     * The URLs follow the formats Panopto builds them in, so this also shows they keep their links.
     *
     * @return void
     */
    public function test_provisioned_course_data_passes_return_value_check(): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $PAGE->set_context(\context_system::instance());

        $server = 'https://demo.panopto.com/Panopto';
        $guid = '6c0313fd-177a-40dd-9e8a-b4f300d923e6';
        $viewerurl = panopto_clean_link_url("{$server}/Pages/Viewer.aspx?id={$guid}&instance=moodle");
        $notesurl = panopto_clean_link_url("{$server}/Pages/Viewer.aspx?id={$guid}&notes=true");
        $itunes = ['http', 'https', 'itpc'];

        $data = [
            // A site can customise this string, with HTML too.
            'errors' => [['message' => 'Could not load the folder. <a href="https://example.com/help">Help</a>']],
            'unprovisioned' => false,
            'provisionurl' => '',
            'showcontent' => true,
            'instance' => 'moodle',
            'livesessions' => [[
                'name' => 'Lecture 3: <div> vs <span>',
                'notesurl' => $notesurl,
                'viewerurl' => $viewerurl,
                'alt' => false,
            ]],
            'recordings' => [
                ['name' => '<span lang="en" class="multilang">Week 1</span>', 'viewerurl' => $viewerurl, 'alt' => false],
                ['name' => 'Q&A "live"', 'viewerurl' => '', 'alt' => true],
            ],
            'morerecordings' => [],
            'hasmorerecordings' => false,
            'audiopodcasturl' => panopto_clean_link_url(
                "itpc://demo.panopto.com/Panopto/Podcast/Podcast.ashx?courseid={$guid}&type=mp3",
                $itunes
            ),
            'audiorssurl' => panopto_clean_link_url("{$server}/Podcast/Podcast.ashx?courseid={$guid}&type=mp3"),
            'videopodcasturl' => '',
            'videorssurl' => '',
            'feediconurl' => (new \moodle_url('/blocks/panopto/images/feed_icon.gif'))->out(false),
            'settingsurl' => panopto_clean_link_url(
                "{$server}/Pages/Sessions/List.aspx?modalPage=FolderSettings&folderID={$guid}"
            ),
            'showrecorderlinks' => true,
            'windowsrecorderurl' => panopto_clean_link_url(
                "{$server}/Cache/16.0.0.00001/Software/PanoptoRecorder.exe?arch=Amd64&useCustomBinary=False"
            ),
            'macrecorderurl' => panopto_clean_link_url(
                "{$server}/Cache/16.0.0.00001/Software/PanoptoRecorder.pkg?arch=X64&useCustomBinary=False"
            ),
        ];

        // Every Panopto URL above is kept as a link.
        foreach (['audiopodcasturl', 'audiorssurl', 'settingsurl', 'windowsrecorderurl', 'macrecorderurl'] as $key) {
            $this->assertNotSame('', $data[$key], $key);
        }
        $this->assertNotSame('', $viewerurl);
        $this->assertNotSame('', $notesurl);

        $cleaned = \core_external\external_api::clean_returnvalue(get_content::execute_returns(), $data);

        $this->assertEquals($data, $cleaned);

        $html = $OUTPUT->render_from_template('block_panopto/block_content', $cleaned);

        $this->assertStringContainsString('Lecture 3: &lt;div&gt; vs &lt;span&gt;', $html);
        $this->assertStringContainsString('&lt;a href=&quot;https://example.com/help&quot;&gt;Help&lt;/a&gt;', $html);
        $this->assertStringContainsString('Q&amp;A &quot;live&quot;', $html);
        $this->assertStringNotContainsString('<div> vs', $html);
    }

    /**
     * The template renders names as text and leaves no inline script in the block.
     *
     * The name below is deliberately not cleaned first, to show that the template escapes it on its own.
     *
     * @return void
     */
    public function test_template_escapes_values_and_has_no_inline_script(): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $PAGE->set_context(\context_system::instance());

        $viewerurl = 'https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1&instance=moodle';
        $recording = ['name' => '<script>alert(1)</script>', 'viewerurl' => $viewerurl, 'alt' => false];

        $html = $OUTPUT->render_from_template('block_panopto/block_content', [
            'errors' => [],
            'unprovisioned' => false,
            'showcontent' => true,
            'instance' => 'moodle"><script>alert(2)</script>',
            'livesessions' => [],
            'recordings' => [$recording, $recording, $recording],
            'morerecordings' => [$recording],
            'hasmorerecordings' => true,
            'settingsurl' => 'https://demo.panopto.com/Panopto/Pages/Sessions/List.aspx',
        ]);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('data-action="panopto-sso"', $html);
        $this->assertStringContainsString('data-action="panopto-toggle-recordings"', $html);
        $this->assertStringContainsString('href="' . s($viewerurl) . '"', $html);
    }

    /**
     * The context the template needs, with every branch switched off.
     *
     * @return array the default template context
     */
    private static function base_context(): array {
        return [
            'errors' => [],
            'unprovisioned' => false,
            'provisionurl' => '',
            'showcontent' => false,
            'instance' => '',
            'livesessions' => [],
            'recordings' => [],
            'morerecordings' => [],
            'hasmorerecordings' => false,
            'audiopodcasturl' => '',
            'audiorssurl' => '',
            'videopodcasturl' => '',
            'videorssurl' => '',
            'feediconurl' => 'https://example.com/moodle/blocks/panopto/images/feed_icon.gif',
            'settingsurl' => '',
            'showrecorderlinks' => false,
            'windowsrecorderurl' => '',
            'macrecorderurl' => '',
        ];
    }

    /**
     * One template context per branch the block can render.
     *
     * @return array[] the contexts, keyed by what the block is showing
     */
    public static function template_branch_provider(): array {
        $provisionurl = 'https://example.com/moodle/blocks/panopto/provision_course_internal.php?id=1';
        $viewerurl = 'https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1&instance=moodle';

        return [
            'unprovisioned, user can provision' => [
                ['unprovisioned' => true, 'provisionurl' => $provisionurl] + self::base_context(),
            ],
            'unprovisioned, user cannot provision' => [
                ['unprovisioned' => true] + self::base_context(),
            ],
            'unprovisioned and an error' => [
                [
                    'errors' => [['message' => 'Something went wrong']],
                    'unprovisioned' => true,
                    'provisionurl' => $provisionurl,
                ] + self::base_context(),
            ],
            'an error on its own' => [
                ['errors' => [['message' => 'Something went wrong']]] + self::base_context(),
            ],
            'provisioned, folder loaded' => [
                [
                    'showcontent' => true,
                    'instance' => 'moodle',
                    'recordings' => [['name' => 'Week 1', 'viewerurl' => $viewerurl, 'alt' => false]],
                ] + self::base_context(),
            ],
            'provisioned, folder empty' => [
                ['showcontent' => true, 'instance' => 'moodle'] + self::base_context(),
            ],
        ];
    }

    /**
     * Every branch of the template renders markup that opens with an element.
     *
     * block_panopto/asyncload passes the rendered template to core/templates replaceNodeContents(),
     * which hands the string straight to jQuery. jQuery only treats a string as HTML when its first
     * non-space character is '<'; anything else it parses as a CSS selector, which throws and leaves
     * the block showing load_content_error instead of its content. A branch that opens with a
     * language string rather than a tag therefore breaks the block.
     *
     * @dataProvider template_branch_provider
     * @param array $data the template context for one branch
     * @return void
     */
    public function test_template_output_opens_with_an_element(array $data): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $PAGE->set_context(\context_system::instance());

        $html = $OUTPUT->render_from_template('block_panopto/block_content', $data);

        $this->assertNotSame('', trim($html));
        $this->assertStringStartsWith('<', ltrim($html));
    }
}
