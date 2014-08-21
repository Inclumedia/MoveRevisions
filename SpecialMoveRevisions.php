<?php
/**
 * Implements Special:MoveRevisions
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
 * @ingroup SpecialPage
 */

/**
 * Special page to allow moving revisions
 *
 * @ingroup SpecialPage
 */
class SpecialMoveRevisions extends SpecialPage {

	protected $mLogId;
	protected $mLogNamespace;
	protected $mLogTitle;
	protected $mTargetPageTitle;

	public function __construct() {
		parent::__construct( 'MoveRevisions' );
	}

	public function isRestricted() {
		return true;
	}

	public function userCanExecute( User $user ) {
		return $user->isAllowed( 'moverevision' );
	}

	/**
	 * Manage forms to be shown according to posted data.
	 * Depending on the submit button used, call a form or a save function.
	 *
	 * @param string|null $par String if any subpage provided, else null
	 * @throws UserBlockedError|PermissionsError
	 */
	public function execute( $par ) {

		$user = $this->getUser();

		if ( !$user->isAllowed( 'moverevision' ) ) {
			throw new PermissionsError();
		}

		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		$request = $this->getRequest();

		if ( $request->getCheck( 'success' ) ) {
			$this->setHeaders();
			$out = $this->getOutput();
			$out->setPagetitle( wfMsg( 'actioncomplete' ) );
			$out->addWikiMsg( 'moverevisions-done', $request->getVal( 'numbermoved' ),
				$request->getVal( 'targettitle' ) );
			$out->returnToMain();
			return;
		}

		if ( $par !== null ) {
			$this->mLogId = $par;
		} else {
			$this->mLogId = $request->getVal( 'logid' );
		}

		if ( $this->mLogId === null ) {
			/*
			 * If the user specified no target, and they can only
			 * edit their own groups, automatically set them as the
			 * target.
			 */
			throw new NoLogIdError();
		}

		$this->mLogId = intval( $this->mLogId );

		if ( !$this->mLogId ) {
			throw new InvalidLogIdError();
		}

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow ( 'logging',
			array( 'log_action', 'log_namespace', 'log_title' ),
			array( 'log_id' => $this->mLogId
		) );
		$this->mLogNamespace = $res->log_namespace;
		$this->mLogTitle = $res->log_title;

		if ( $res->log_action != 'delete' ) {
			throw new InvalidLogIdError();
		}

		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		$out->addModuleStyles( 'mediawiki.special' );
		if (
			$request->wasPosted() &&
			$request->getCheck( 'namespace' ) &&
			$request->getCheck( 'pagetitle' ) &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ), $this->mLogId )
		) {
			$affectedRows = $this->moveRevisions(
				$request->getInt( 'namespace' ),
				$request->getVal( 'pagetitle' ),
				$request->getVal( 'reason' )
			);

			$out->redirect( $this->getSuccessURL( $affectedRows ) );

			return;
		}

		// show some more forms
		if ( $this->mLogId !== null ) {
			$this->MoveRevisionsForm( $this->mLogId );
		}
	}

	function moveRevisions ( $namespace, $titleString, $reason ) {
		$title = Title::makeTitleSafe ( $namespace, $titleString );
		$page = WikiPage::factory( $title );
		$dbw = wfGetDB( DB_MASTER );
		if ( $title->exists() ) {
			$pageId = $title->getArticleID();
		} else {
			$pageId = $page->insertOn( $dbw );
			if ( !$pageId ) {
				die ( 'Could not insert page entry' );
			}
		}
		$this->mTargetPageTitle = $title->getFullText();
		$row = array(
			'rev_comment' => 'ar_comment',
			'rev_user' => 'ar_user',
			'rev_user_text' => 'ar_user_text',
			'rev_timestamp' => 'ar_timestamp',
			'rev_minor_edit' => 'ar_minor_edit',
			'rev_id' => 'ar_rev_id',
			'rev_parent_id' => 'ar_parent_id',
			'rev_text_id' => 'ar_text_id',
			'rev_len' => 'ar_len',
			'rev_page' => $pageId,
			'rev_deleted' => 'ar_deleted',
			'rev_sha1' => 'ar_sha1',
		);
		global $wgContentHandlerUseDB;
		if ( $wgContentHandlerUseDB ) {
			$row['rev_content_model'] = 'ar_content_model';
			$row['rev_content_format'] = 'ar_content_format';
		}
		$logParams = $dbw->selectField( 'logging', 'log_params',
			array( 'log_id' => $this->mLogId ) );
		$revisions = unserialize( $logParams );
		$insertSelectConds = '';
		$deleteConds = '';
		$firstRev = true;
		foreach ( $revisions as $revision ) {
			if ( !$firstRev ) {
				$insertSelectConds .= ' OR ';
				$deleteConds .= ' OR ';
			}
			$insertSelectConds .= "rev_id=$revision";
			$deleteConds .= "ar_rev_id=$revision";
			$firstRev = false;
		}
		$dbw->insertSelect( 'revision', array( 'archive' ),
			$row,
			$deleteConds,
			__METHOD__
		);
		$dbw->delete( 'archive', $deleteConds );
		$affectedRows = $dbw->affectedRows();
		$sourcePageIds = array( $pageId );
		$sourcePageRes = $dbw->select(
			'revision',
			'rev_page',
			array( $insertSelectConds ),
			__METHOD__,
			array( 'DISTINCT' )
		);
		$emptyPages = array();
		foreach ( $sourcePageRes as $sourcePageRow ) {
			$sourcePageIds[] = intval( $sourcePageRow->rev_page );
		}
		$dbw->update(
			'revision',
			array( 'rev_page' => $pageId ),
			array( $insertSelectConds )
		);
		$affectedRows += $dbw->affectedRows();
		// Clean up page_latest
		foreach ( $sourcePageIds as $sourcePageId ) {
			$latestRevisionRow = $dbw->selectRow(
				'revision',
				array( 'rev_id', 'rev_timestamp' ),
				array( 'rev_page' => $sourcePageId ),
				__METHOD__,
				array( 'ORDER BY' => 'rev_timestamp DESC' )
			);
			// Delete empty pages from page table
			if ( !$latestRevisionRow ) {
				$dbw->delete( 'page', array( 'page_id' => $sourcePageId ) );
			} else {
				$dbw->update(
					'page',
					array( 'page_latest' => $latestRevisionRow->rev_id ),
					array( 'page_id' => $sourcePageId )
				);
			}
		}
		$this->addLogEntry( $title, $reason );
		return $affectedRows;
	}

	function getSuccessURL( $affectedRows ) {
		return $this->getPageTitle( $this->mLogId )->getFullURL(
			array( 'success' => 1, 'numbermoved' => $affectedRows,
				'targettitle' => $this->mTargetPageTitle ) );
	}

	/**
	 * Add a log entry for an action.
	 * @param Title $title
	 * @param string $reason
	 */
	function addLogEntry( $title, $reason ) {
		$logEntry = new ManualLogEntry( 'move', 'revision' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $title );
		$logEntry->setComment( $reason );
		$logEntry->setParameters( 'logid', $this->mLogId );
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
	}

	/**
	 * Show the form to edit group memberships.
	 *
	 * @param User|UserRightsProxy $user User or UserRightsProxy you're editing
	 * @param array $groups Array of groups the user is in
	 */
	protected function moveRevisionsForm() {
		$namespaces = $this->getContext()->getLanguage()->getNamespaces();
		$this->getOutput()->addWikiMsg( 'moverevisions-intro');
		$this->getOutput()->addHTML(
			Xml::openElement(
				'form',
				array(
					'method' => 'post',
					'action' => $this->getPageTitle()->getLocalURL(),
					'name' => 'editGroup',
					'id' => 'mw-userrights-form2'
				)
			) .
			Xml::label( $this->msg( 'moverevisions-namespace' )->text(), 'namespace' ) .
			Html::namespaceSelector(
				array( 'selected' => NS_MAIN ),
				array( 'name' => 'namespace', 'id' => 'namespace' )
			) .
			Html::hidden( 'wpEditToken', $this->getUser()->getEditToken( $this->mLogId ) ) .
			Html::hidden( 'logid', $this->mLogId ) ./*
			Html::hidden( 'namespace' ),
			Html::hidden( 'title' ),
			Html::hidden( 'moverevisions-reason' ),*/
			Xml::inputLabel( $this->msg( 'moverevisions-pagetitle' )->text(), 'pagetitle', 'username2', 30, str_replace( '_', ' ', '' ), array( 'autofocus' => true ) ) . '<br/>' .
			Xml::inputLabel( $this->msg( 'moverevisions-reason' )->text(), 'reason', 'username', 60, str_replace( '_', ' ', '' ), array() ) . ' ' .
			Xml::submitButton( $this->msg( 'htmlform-submit' )->text() ) .
			Xml::closeElement( 'table' ) . "\n" .
			Xml::closeElement( 'fieldset' ) .
			Xml::closeElement( 'form' ) . "\n"
		);
	}
}

class NoLogIdError extends ErrorPageError {
	public function __construct() {
		parent::__construct( 'error', array( 'nologiderror' ) );
	}
}

class InvalidLogIdError extends ErrorPageError {
	public function __construct() {
		parent::__construct( 'error', array( 'invalidlogiderror' ) );
	}
}