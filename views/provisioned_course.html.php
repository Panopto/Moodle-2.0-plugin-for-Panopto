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

/**
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
?>

<div class='block_panopto'>
    <div class='courseProvisionResult'>
        <div class='attribute'>Course Name</div>
        <div class='value'><?php echo $provisioningdata->ShortName . ": " . $provisioningdata->LongName ?></div>

        <div class='attribute'>Publishers</div>
        <div class='value'>
            <?php
                if (!empty($provisioningdata->Publishers)) {
                $publishers = $provisioningdata->Publishers;

                // Single-element return set comes back as scalar, not array (?).
                if (!is_array($publishers)) {
                    $publishers = array($publishers);
                }
                $publisherinfo = array();
                foreach ($publishers as $publisher) {
                    array_push($publisherinfo, "$publisher->UserKey ($publisher->FirstName $publisher->LastName &lt;$publisher->Email&gt;)");
                }

                echo join("<br />", $publisherinfo);
                } else {
                ?><div class='errorMessage'>No publishers.</div><?php
                }
                ?>
        </div>

        <div class='attribute'>Creators</div>
        <div class='value'>
            <?php
            if (!empty($provisioningdata->Instructors)) {
                $instructors = $provisioningdata->Instructors;

                // Single-element return set comes back as scalar, not array (?).
                if (!is_array($instructors)) {
                    $instructors = array($instructors);
                }
                $instructorinfo = array();
                foreach ($instructors as $instructor) {
                    array_push($instructorinfo, "$instructor->UserKey ($instructor->FirstName $instructor->LastName &lt;$instructor->Email&gt;)");
                }

                echo join("<br />", $instructorinfo);
            } else {
                ?><div class='errorMessage'>No creators.</div><?php
            }
            ?>
        </div>
        <div class='attribute'>Students</div>
        <div class='value'>
            <?php
            if (!empty($provisioningdata->Students)) {
                $students = $provisioningdata->Students;

                // Single-element return set comes back as scalar, not array (?).
                if (!is_array($students)) {
                    $students = array($students);
                }
                $studentinfo = array();
                foreach ($students as $student) {
                    array_push($studentinfo, $student->UserKey);
                }

                echo join(", ", $studentinfo);
            } else {
                ?><div class='errorMessage'>No students.</div><?php
            }
            ?>
        </div>
        <div class='attribute'>Result</div>
        <div class='value'>
            <?php
            if (!empty($provisioneddata)) {
                ?>
                <div class='successMessage'>Successfully provisioned course {<?php echo $provisioneddata->PublicID ?>}</div>
                <?php
            } else {
                ?>
                <div class='errorMessage'>Error provisioning course.</div>
                <?php
            }
            ?>
        </div>
    </div>
</div>