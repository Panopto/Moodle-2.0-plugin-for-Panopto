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

/**
 * AMD module for displaying Panopto content asynchronously.
 *
 * @package block_panopto
 * @copyright  Panopto 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
        "jquery",
        "core/ajax",
        "core/str",
        "core/log",
        "core/templates",
], ($, ajax, str, log, templates) =>
{
    /**
     * Post the block's SSO form to a Panopto page, so the user arrives there signed in.
     *
     * @param {jQuery} node The block's content container.
     * @param {string} url The Panopto page to open.
     * @param {string} target The name of the window or tab to open it in.
     */
    var submitSso = (node, url, target) =>
    {
        var form = node.find('form[data-region="panopto-sso-form"]')[0];
        if (!form)
        {
            return;
        }

        form.action = url;
        form.target = target;
        form.submit();
    };

    /**
     * Handle the links in the block's content. The handlers are delegated from the container,
     * which stays in place when the content is rendered into it.
     *
     * @param {jQuery} node The block's content container.
     */
    var registerEventListeners = (node) =>
    {
        node.on('click', '[data-action="panopto-sso"]', function(e)
        {
            e.preventDefault();
            submitSso(node, this.href, '_blank');
        });

        node.on('click', '[data-action="panopto-notes"]', function(e)
        {
            e.preventDefault();

            // Open an empty notes window first, then post the SSO form into it.
            var notesWindow = window.open('', 'PanoptoNotes',
                'width=500,height=800,resizable=1,scrollbars=0,status=0,location=0');
            submitSso(node, this.getAttribute('data-notes-url'), 'PanoptoNotes');

            // Bring the notes window to the front. It is null when a popup blocker stopped it.
            if (notesWindow)
            {
                notesWindow.focus();
            }
        });

        node.on('click', '[data-action="panopto-toggle-recordings"]', function(e)
        {
            e.preventDefault();

            var toggle = $(this);
            var expand = toggle.attr('aria-expanded') !== 'true';

            node.find('[data-region="panopto-more-recordings"]').css('display', expand ? 'block' : 'none');
            toggle.attr('aria-expanded', expand ? 'true' : 'false');
            toggle.find('[data-region="panopto-show-all"]').prop('hidden', expand);
            toggle.find('[data-region="panopto-show-less"]').prop('hidden', !expand);
        });
    };

    /**
     * Replace the block's content with a fixed message after a failure.
     *
     * @param {jQuery} node The block's content container.
     * @param {Object} error The failure, an exception object rather than markup.
     */
    var showLoadError = (node, error) =>
    {
        node.find('#loading_text').remove();

        // Show a fixed message as text and leave the details to the developer log.
        log.error(error);
        str.get_string('load_content_error', 'block_panopto')
            .then(function(message)
            {
                node.text(message);
                return message;
            })
            .catch(log.error);
    };

    /**
     * Load the Panopto block's content for a course into the block's container.
     *
     * @param {Object} params
     * @param {string} params.id The id of the element that holds the block's content.
     * @param {number} params.courseid The course whose Panopto content is loaded.
     */
    var init = (params) =>
    {
        // Find the div containing the Panopto block's content.
        var mynode = $('#' + params.id);
        if (mynode.length)
        {
            registerEventListeners(mynode);

            // Execute on DOM ready.
            $(document).ready(function()
            {
                var request = {
                    methodname: 'block_panopto_get_content',
                    args: {
                        courseid: params.courseid
                    }
                };

                // The web service returns data, not markup; the template escapes every value.
                ajax.call([request])[0].done(function(response)
                {
                    templates.renderForPromise('block_panopto/block_content', response)
                        .then(function(rendered)
                        {
                            templates.replaceNodeContents(mynode, rendered.html, rendered.js);
                            return rendered;
                        })
                        .catch(function(error)
                        {
                            showLoadError(mynode, error);
                        });
                })
                .fail(function(error)
                {
                    showLoadError(mynode, error);
                });
            });
        }
        else
        {
            log.debug("Couldn't find element with id: " + params.id);
        }
    };
    return {
        initblock: init,
    };
});
