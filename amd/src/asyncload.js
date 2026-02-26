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
 * @module block_panopto/asyncload
 * @copyright  Panopto 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from "jquery";
import {call as fetchMany} from "core/ajax";

export const initblock = (params) => {
    // Find the div containing the Panopto block's content.
    const mynode = $('#' + params.id);
    if (mynode.length) {
        // Execute on DOM ready.
        $(document).ready(() => {
            const request = {
                methodname: 'block_panopto_get_content',
                args: {
                    courseid: params.courseid
                }
            };

            fetchMany([request])[0].then((response) => {
                mynode.html(response);
                return response;
            }).catch((error) => {
                mynode.html(error);
            });
        });
    }
};
