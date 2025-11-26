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
], ($, ajax, str) =>
{
    var init = (params) =>
    {
        // Find the div containing the Panopto block's content.
        var mynode = $('#' + params.id);
        if (mynode.length)
        {
            // Execute on DOM ready.
            $(document).ready(function()
            {
                var request = {
                    methodname: 'block_panopto_get_content',
                    args: {
                        courseid: params.courseid
                    }
                };

                ajax.call([request])[0].done(function(response)
                {
                    mynode.find('#loading_text').remove();
                    mynode.html(response);
                })
                .fail(function(error)
                {
                    mynode.find('#loading_text').remove();
                    mynode.html(error);
                });
            });
        }
        else
        {
            console.error("Couldn't find element with id: " + params.id);
        }
    };
    return {
        initblock: init,
    };
});