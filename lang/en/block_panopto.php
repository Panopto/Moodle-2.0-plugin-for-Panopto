<?php
/* Copyright Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * 
 * This file is part of the Panopto plugin for Moodle.
 * 
 * The Panopto plugin for Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The Panopto plugin for Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with the Panopto plugin for Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

$string['pluginname'] = 'Panopto Focus';
$string['panopto:addinstance'] = 'Add a new Panopto block';
$string['panopto:myaddinstance'] = 'Add a new Panopto block to my page';
$string['panopto:provision_course'] = 'Provision a course';
$string['panopto:provision_multiple'] = 'Provision multiple courses at once';
$string['provision_courses'] = 'Provision Courses';
$string['provisioncourseselect'] = 'Select Courses to Provision.';
$string['provisioncourseselect_help'] = 'Multiple selections are possible by Ctrl-clicking (Windows) or Cmd-clicking (Mac).';
$string['unconfigured'] = 'Global configuration incomplete. Please contact your system administrator.';
$string['block_edit_error'] = 'Cannot configure block instance: ' . $string['unconfigured'];
$string['block_edit_header'] = 'Select the Panopto Focus course to display in this block.';
$string['add_to_panopto'] = 'Add this course to Panopto (re-add to sync user lists)';
$string['or'] = 'OR';
$string['existing_course'] = 'Select an existing course:';
$string['block_global_instance_name'] = 'Moodle Instance Name';
$string['block_global_instance_description'] = 'This value is prefixed before usernames and course-names in Panopto.';
$string['block_global_hostname'] = 'Panopto Server Hostname';
$string['block_global_application_key'] = 'Application Key';
$string['block_global_add_courses'] = 'Add Moodle courses to Panopto Focus';
$string['course'] = 'Course';
$string['no_course_selected'] = 'No Panopto Focus course selected';
$string['error_retrieving'] = 'Error retrieving Panopto Focus course.';
$string['live_sessions'] = 'Live Sessions';
$string['no_live_sessions'] = 'No Live Sessions';
$string['take_notes'] = 'Take Notes';
$string['watch_live'] = 'Watch Live';
$string['completed_recordings'] = 'Completed Recordings';
$string['no_completed_recordings'] = 'No Completed Recordings';
$string['show_all'] = 'Show All';
$string['podcast_feeds'] = 'Podcast Feeds';
$string['podcast_audio'] = 'Audio Podcast';
$string['podcast_video'] = 'Video Podcast';
$string['links'] = 'Links';
$string['course_settings'] = 'Course Settings';
$string['download_recorder'] = 'Download Recorder';
$string['show_all'] = 'Show All';
$string['show_less'] = 'Show Less';

/* Kent Changes */
$string['panopto:panoptocreator'] = 'Role is a creator in panopto';
$string['panopto:panoptoviewer'] = 'Role is a viewer in panopto';
$string['reprovision'] = 'Reprovison course';
$string['help_student'] = 'Lecture recording help';
$string['help_student_help'] = 'For help viewing lecture recordings please view our <a href="http://www.kent.ac.uk/elearning/lecture_recording/LR_Student.pdf" target="_blank">help documentation</a>';
$string['help_staff'] = 'Lecture recording help';
$string['help_staff_help'] = 'For help using lecture recordings please view our <a href="http://www.kent.ac.uk/elearning/documentationStaff.html#lr" target="_blank">help documentation</a>';
$string['terms_link_title'] = 'Terms of Use';
$string['help_terms'] = 'Acceptable Use Policy';
$string['help_terms_help'] = 'You can only use recordings for your own study as a supplement to live lectures. You must not share these recordings, even with friends studying at other universities. <br>You must not upload copies to any public website (such as YouTube) and to do so will be treated as a serious breach of academic integrity and IT Regulations <a target="_blank" href="http://www.kent.ac.uk/regulations/general.html">http://www.kent.ac.uk/regulations/general.html</a>';
$string['block_panopto_admin_email'] = 'Panopto admin email';
$string['block_panopto_admin_email_toggle'] = 'Admin email toggle';
$string['cachedef_panopto'] = 'Panopto';
$string['ajax_json_error'] = 'Unable to obtain panopto data (Err Id: 1)';
$string['ajax_data_error'] = 'Unable to obtain panopto data (Err Id: 2)';
$string['ajax_failure'] = 'Unable to obtain panopto data (Err Id: 3)';
$string['ajax_busy'] = 'Panopto seems to be a bit busy right now! Try again later.';
$string['block_panopto_status_message'] = 'Panopto status message.';

//Block permissions status and link to tcs
$string['access_status_creator'] = 'Access: Creator';
$string['access_status_viewer'] = 'Access: Viewer';
$string['access_status_none'] = 'Access: None';
$string['access_status_tcs'] = 'For editing access please see terms and conditions.';
$string['access_status_tcs_btn'] = 'Terms &amp; Conditions';

//Choose your role page
$string['role_choice_head'] = 'Please pick your role';
$string['role_choice_ac_btn'] = 'Academic Staff';
$string['role_choice_nac_btn'] = 'Non Academic Staff';
$string['role_choice_cancel'] = 'Cancel';

//Terms page
$string['terms_head'] = 'Terms and Conditions';
$string['terms_back_btn'] = 'Back';
$string['terms_agree_btn'] = 'Agree';
$string['terms_decline_btn'] = 'Decline';

//Ajax completion messages
$string['success_roleassign'] = 'Your submission has been submitted successfully ';
$string['success_sync_succ'] = 'and you have been synced with this panopto module.';
$string['success_sync_fail'] = 'but we were unable to sync you with this panopto module. Try reprovisioning later.';
$string['success_extras'] = ' A copy of the terms and conditions will be emailed to you shortly. ';
$string['error'] = "There was a problem sending your request, please refresh this page and try again. If this problem persists then please contact <a href='mailto:elearning@kent.ac.uk'>elearning@kent.ac.uk</a>.";

//Email stuff
$string['email_subject'] = "Panopto terms and conditions copy";
$string['admin_email_subject'] = "User agreement to panopto terms and conditions";

// Actual terms and conditions
$string['accademic_terms'] = "
<h2>Lecture Recording Consent </h2>
<p><em>Each&nbsp;person who has agreed to&nbsp;be recorded and filmed as the  principal party to, or as part of, a teaching event carried out within the  University of Kent must agree to these terms.&nbsp;The purpose of this agreement is to seek consent for the  films and/or recordings to be taken&nbsp;and subsequently to be used in a  number of media, including the intranet/web by the University of Kent.&nbsp;The  University of Kent in turn offers a commitment to only allow said recordings to  be used appropriately and sensitively for the purposes of teaching and learning.</em></p>
<h2>Lecturer</h2>
<p>I agree to my lecture being recorded in audio/video  format by the University of Kent. Where  a recording is being made, I will notify everyone present of this fact.</p>
<p>  I confirm  that where material is included in the recording which is the intellectual  property, including copyright, of another party, I have permission to include  the materials in my lecture for educational purposes, including permission to  record such material <a href='#ref1'>[1]</a>. I understand that any copyright or other intellectual  property <a href='#ref2'>[2]</a> which arises in the recording belongs to the University of Kent  and that the recording may be used by the University of Kent as per the  University’s Policy Statement on Intellectual Property <a href='#ref3'>[3]</a>. This includes  conversion to digital format and storing and publication on the University’s  virtual learning environment. <br />
  I agree to license  all performance rights <a href='#ref4'>[4]</a> in the film and/or recordings of lectures to the  University of Kent for teaching and learning purposes on a worldwide basis for  the duration of my employment, or until the end of the academic year following  the recording, whichever is later (see <a href='#note1'>guidance note 1</a>). </p>
<p>  I agree to  waive all moral rights in my performance in the film and/or recordings of  lectures to the University of Kent (see <a href='#note2'>guidance note 2</a>). </p>
<p>  I agree  that, where students or other third parties actively participate in recorded  events, I will obtain a signed consent form for the third party prior to the  recording being made available for viewing. Active participation includes  delivery of a presentation or performance of a student work and is not intended  to encompass ad hoc questions or commentary.</p>
<h2>General</h2>
<p>The  University will continue to comply with the data Protection Act 1998.  I understand  that my image and/or recordings will be used for teaching and learning purposes  only and that copyright in the recordings will be retained by The University of  Kent.  </p>
<h2>Guidance notes</h2>
<ol>
  <li><a name='note1' id='note1'></a>It is necessary to license your performance  rights to the University in order for the recorded lecture content to be made  legally available for teaching and learning purposes. Continued use outside  this duration by either party can be negotiated separately but must be  confirmed in writing, signed and retained by both parties</li>
  <li><a name='note2' id='note2'></a>Your moral right is your right to be named as  the author of a work. This waiver simply allows the University to publish the  recording without being legally obliged to add your name to it. As you have  full control over the details of the recording, you may do this yourself if  required, and the University will not actively remove your name where it  appears within a recording.</li>
</ol>
<h2> References</h2>
<ol>
  <li><a name='ref1' id='ref1'></a>Further  guidance on notifying contributors and  managing use of third party intellectual property can be found in the  <a href='http://www.kent.ac.uk/elearning/lecture_recording/LR_Copyright.pdf' target='_blank'>accompanying guide</a>.</li>
  <li><a name='ref2' id='ref2'></a>As per the <a href='http://www.legislation.gov.uk/ukpga/1988/48/contents' target='_blank'>Copyright, Designs and Patents Act 1988 (CDPA)</a></li>
  <li><a name='ref3' id='ref3'></a>This document can be found on the University’s <a href='https://www.kent.ac.uk/enterprise/university-staff/policy-and-procedure.html' target='_blank'>Innovation &amp; Enterprise  website</a>  (login required)</li>
  <li><a name='ref4' id='ref4'></a>See <a href='http://www.legislation.gov.uk/ukpga/1988/48/section/182' target='_blank'>s.182  CDPA</a></li>
</ol>";

$string['accademic_terms_plain_txt'] = "
Lecture Recording Consent
Each person who has agreed to be recorded and filmed as the  principal party to, or as part of, a teaching event carried out within the  University of Kent must agree to these terms. The purpose of this agreement is to seek consent for the  films and/or recordings to be taken and subsequently to be used in a  number of media, including the intranet/web by the University of Kent. The University of Kent in turn offers a commitment to only allow said recordings to  be used appropriately and sensitively for the purposes of teaching and learning.
Lecturer
I agree to my lecture being recorded in audio/video  format by the University of Kent. Where  a recording is being made, I will notify everyone present of this fact.
I confirm  that where material is included in the recording which is the intellectual  property, including copyright, of another party, I have permission to include  the materials in my lecture for educational purposes, including permission to  record such material [1]. I understand that any copyright or other intellectual  property [2] which arises in the recording belongs to the University of Kent  and that the recording may be used by the University of Kent as per the  University’s Policy Statement on Intellectual Property [3]. This includes  conversion to digital format and storing and publication on the University’s  virtual learning environment. 
I agree to license  all performance rights [4] in the film and/or recordings of lectures to the  University of Kent for teaching and learning purposes on a worldwide basis for  the duration of my employment, or until the end of the academic year following  the recording, whichever is later (see guidance note 1). 
I agree to  waive all moral rights in my performance in the film and/or recordings of  lectures to the University of Kent (see guidance note 2). 
I agree  that, where students or other third parties actively participate in recorded  events, I will obtain a signed consent form for the third party prior to the  recording being made available for viewing. Active participation includes  delivery of a presentation or performance of a student work and is not intended  to encompass ad hoc questions or commentary.

General
The  University will continue to comply with the data Protection Act 1998.  I understand  that my image and/or recordings will be used for teaching and learning purposes  only and that copyright in the recordings will be retained by The University of  Kent.  
Guidance notes

- It is necessary to license your performance  rights to the University in order for the recorded lecture content to be made  legally available for teaching and learning purposes. Continued use outside  this duration by either party can be negotiated separately but must be  confirmed in writing, signed and retained by both parties
- Your moral right is your right to be named as  the author of a work. This waiver simply allows the University to publish the  recording without being legally obliged to add your name to it. As you have  full control over the details of the recording, you may do this yourself if  required, and the University will not actively remove your name where it  appears within a recording.

References

- Further  guidance on notifying contributors and  managing use of third party intellectual property can be found in the  accompanying guide.
- As per the Copyright, Designs and Patents Act 1988 (CDPA)
- This document can be found on the University’s Innovation & Enterprise  website  (login required)
- See s.182  CDPA";

$string['non_accademic_terms'] = "
<h2>Presentation Recording Consent </h2>
<p><em>Each&nbsp;person who has agreed to&nbsp;be recorded and filmed as the  principal party to, or as part of, a teaching event carried out within the  University of Kent must agree to these terms.&nbsp;The purpose of this agreement</em><em> is to seek consent for the films and/or  recordings to be taken&nbsp;and subsequently to be used in a number of media,  including the intranet/web by the University of Kent.&nbsp; The University of Kent  in turn offers a commitment to only allow said recordings to be used  appropriately and sensitively.</em></p>
<h2>Non-Academic Staff</h2>
<p>I agree to my events being recorded in audio/video  format by the University of Kent. Where  a recording is being made, I will notify everyone present of this fact.</p>
<p>  I confirm  that where material is included in the recording which is the intellectual  property, including copyright, of another party, I have permission to include  the materials in my lecture for educational purposes, including permission to  record such material <a href='#ref1'>[1]</a>. I understand that any copyright or other intellectual  property <a href='#ref2'>[2]</a> which arises in the recording belongs to the University of Kent  and that the recording may be used by the University of Kent as per the  University’s Policy Statement on Intellectual Property <a href='#ref3'>[3]</a>. This includes  conversion to digital format and storing and publication on the University’s  virtual learning environment and/or web site. </p>
<p>I grant to The  University of Kent a perpetual, worldwide licence to record/film  materials created by me that are included within my presentation. I agree to license all  performance rights <a href='#ref4'>[4]</a> in the film and/or recordings to the University of Kent on a perpetual,  worldwide basis (see <a href='#note1'>guidance note 1</a>).</p>
<p>  I agree to  waive all moral rights in my performance in the film and/or recordings and in any  works created by me that are included as part of any recording at the University of Kent (see <a href='#note2'>guidance note 2</a>). </p>
<p>  I agree  that, where students or other third parties actively participate in recorded  events, I will obtain a signed consent form for the third party prior to the  recording being made available for viewing. Active participation includes  delivery of a presentation or performance of a student work and is not intended  to encompass ad hoc questions or commentary.</p>
<h2>General</h2>
<p>The  University will continue to comply with the data Protection Act 1998.  I understand  that my image and/or recordings will be used for teaching and learning purposes  only and that copyright in the recordings will be retained by The University of  Kent.  </p>
<h2>Guidance notes</h2>
<ol>
  <li><a name='note1' id='note1'></a>It is necessary to license your performance  rights to the University in order for the recorded lecture content to be made  legally available for teaching and learning purposes. Continued use outside  this duration by either party can be negotiated separately but must be  confirmed in writing, signed and retained by both parties</li>
  <li><a name='note2' id='note2'></a>Your moral right is your right to be named as  the author of a work. This waiver simply allows the University to publish the  recording without being legally obliged to add your name to it. As you have  full control over the details of the recording, you may do this yourself if  required, and the University will not actively remove your name where it  appears within a recording.</li>
</ol>
<h2> References</h2>
<ol>
  <li><a name='ref1' id='ref1'></a>Further  guidance on notifying contributors and  managing use of third party intellectual property can be found in the  <a href='http://www.kent.ac.uk/elearning/lecture_recording/LR_Copyright.pdf' target='_blank'>accompanying guide</a>.</li>
  <li><a name='ref2' id='ref2'></a>As per the <a href='http://www.legislation.gov.uk/ukpga/1988/48/contents' target='_blank'>Copyright, Designs and Patents Act 1988 (CDPA)</a></li>
  <li><a name='ref3' id='ref3'></a>This document can be found on the University’s <a href='https://www.kent.ac.uk/enterprise/university-staff/policy-and-procedure.html' target='_blank'>Innovation &amp; Enterprise  website</a>  (login required)</li>
  <li><a name='ref4' id='ref4'></a>See <a href='http://www.legislation.gov.uk/ukpga/1988/48/section/182' target='_blank'>s.182  CDPA</a></li>
</ol>";

$string['non_accademic_terms_plain_txt'] = "
Presentation Recording Consent

Each person who has agreed to be recorded and filmed as the  principal party to, or as part of, a teaching event carried out within the  University of Kent must agree to these terms. The purpose of this agreement is to seek consent for the films and/or  recordings to be taken and subsequently to be used in a number of media,  including the intranet/web by the University of Kent. The University of Kent  in turn offers a commitment to only allow said recordings to be used  appropriately and sensitively.
Non-Academic Staff
I agree to my events being recorded in audio/video  format by the University of Kent. Where  a recording is being made, I will notify everyone present of this fact.
I confirm  that where material is included in the recording which is the intellectual  property, including copyright, of another party, I have permission to include  the materials in my lecture for educational purposes, including permission to  record such material [1]. I understand that any copyright or other intellectual  property [2] which arises in the recording belongs to the University of Kent  and that the recording may be used by the University of Kent as per the  University’s Policy Statement on Intellectual Property [3]. This includes  conversion to digital format and storing and publication on the University’s  virtual learning environment and/or web site. 
I grant to The  University of Kent a perpetual, worldwide licence to record/film  materials created by me that are included within my presentation. I agree to license all  performance rights [4] in the film and/or recordings to the University of Kent on a perpetual,  worldwide basis (see guidance note 1).
I agree to  waive all moral rights in my performance in the film and/or recordings and in any  works created by me that are included as part of any recording at the University of Kent (see guidance note 2). 
I agree  that, where students or other third parties actively participate in recorded  events, I will obtain a signed consent form for the third party prior to the  recording being made available for viewing. Active participation includes  delivery of a presentation or performance of a student work and is not intended  to encompass ad hoc questions or commentary.

General
The  University will continue to comply with the data Protection Act 1998.  I understand  that my image and/or recordings will be used for teaching and learning purposes  only and that copyright in the recordings will be retained by The University of  Kent.  
Guidance notes

- It is necessary to license your performance  rights to the University in order for the recorded lecture content to be made  legally available for teaching and learning purposes. Continued use outside  this duration by either party can be negotiated separately but must be  confirmed in writing, signed and retained by both parties
- Your moral right is your right to be named as  the author of a work. This waiver simply allows the University to publish the  recording without being legally obliged to add your name to it. As you have  full control over the details of the recording, you may do this yourself if  required, and the University will not actively remove your name where it  appears within a recording.

References

- Further  guidance on notifying contributors and  managing use of third party intellectual property can be found in the  accompanying guide.
- As per the Copyright, Designs and Patents Act 1988 (CDPA)
- This document can be found on the University’s Innovation & Enterprise  website (login required)
- See s.182  CDPA";

/* End of file block_panopto.php */