<?php
/**
 * MoveRevisions MediaWiki extension.
 *
 * This extension allows users to move revisions that were deleted in a particular log event.
 *
 * Written by Leucosticte
 * http://www.mediawiki.org/wiki/User:Leucosticte
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Extensions
 */

# Alert the user that this is not a valid entry point to MediaWiki if the user tries to access the
# extension file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
   die( 'This file is a MediaWiki extension. It is not a valid entry point' );
}

$wgExtensionCredits['specialpage'][] = array(
   'path' => __FILE__,
   'name' => 'MoveRevisions',
   'author' => '[https://www.mediawiki.org/wiki/User:Leucosticte Leucosticte]',
   'url' => 'https://www.mediawiki.org/wiki/Extension:MoveRevisions',
   'descriptionmsg' => 'moverevisions-desc',
   'version' => '1.0.1',
);

$wgAutoloadClasses['SpecialMoveRevisions'] = __DIR__ . '/SpecialMoveRevisions.php';
$wgAutoloadClasses['MoveRevisions'] = __DIR__ . '/MoveRevisions.classes.php';
$wgMessagesDirs['MoveRevisions'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['MoveRevisions'] = __DIR__ . '/MoveRevisions.i18n.php';
$wgExtensionMessagesFiles['MoveRevisions'] = __DIR__ . '/MoveRevisions.alias.php';
$wgSpecialPages['MoveRevisions'] = 'SpecialMoveRevisions';
$wgSpecialPageGroups['MoveRevisions'] = 'other';
$wgHooks['GetActionLinks'][] = 'MoveRevisions::onGetActionLinks';
$wgGroupPermissions['sysop']['moverevision'] = true;
$messagesDirs['MoveRevisions'] = __DIR__ . '/i18n';