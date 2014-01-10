# Panopto plugin for Moodle 2

## What is it?

This is a block for Moodle 2, that allows courses in Moodle to link directly to Panopto folders, and to display recordings in the sidebar. It also allows for SSO between Moodle and Panopto, and automatically syncs user permissions between both systems.

## Credits

The original Panopto plugin was written by Panopto for Moodle 1.9 and earlier. It has since been rewritten for Moodle 2 by [Spenser Jones](http://spenserjones.com), and subsequently made open-source for collaboration between the open-source community and Panopto.

## How can I help?

Fork the block, fix a bug or add a new feature, and send us a pull-request. Or, if you're not a developer, but you've found a bug, add it to our [issue tracker](https://github.com/Panopto/Moodle-2.0-Plugin-for-Panopto/issues).

## To do:
* Move language-specific strings into lang/en/block_panopto.php
* * lib/panopto_data.php
* * SSO.php
* * views/provisioned_course.html.php

## Copyright

 Copyright Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 
## License

The Panopto plugin for Moodle is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

The Panopto plugin for Moodle is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with the Panopto plugin for Moodle.  If not, see <http://www.gnu.org/licenses/>.

# Kent specific instructions

## New roles setup

This block now requires two new roles to be setup:
	
	-	- shortname: panopto_academic
		- fullname: Panopto academic user
		- archetype: Authenticated user
		- Context types: system
	
	-	- shortname: panopto_non_academic
		- fullname: Panopto non academic user
		- archetype: Authenticated user
		- Context types: system

## Permission change on current roles

All roles that currently have panopto write access also need to be given viewer access as well. This is to facilitate their use of the system until they have agreed to the t&cs.
