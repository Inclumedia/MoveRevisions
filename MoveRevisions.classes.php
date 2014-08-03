<?php
class MoveRevisions {
    public static function onGetActionLinks( $subtype, $target, $timestamp, $user,
	&$retMessage ) {
        if ( $subtype != 'delete' ) {
	    return true;
	}
	if ( $user->isAllowed( 'undelete' ) ) {
	    $message = 'undeletelink';
	} else {
	    $message = 'undeleteviewlink';
	}
	$revert = Linker::linkKnown(
	    SpecialPage::getTitleFor( 'Undelete' ),
	    wfMessage( $message )->escaped(),
	    array(),
	    array( 'target' => $target->getPrefixedDBkey() )
	);

	$retMessage = wfMessage( 'parentheses' )->rawParams( $revert )->escaped();
	if ( $user->isAllowed( 'moverevision' ) ) {
	    $dbr = wfGetDB( DB_SLAVE );
	    $id = $dbr->selectField( 'logging', 'log_id', array(
		'log_namespace' => $target->getNamespace(),
		'log_title' => $target->getDBKey(),
		'log_timestamp' => $timestamp
		)
	    );
	    $moverevisions = Linker::linkKnown(
		SpecialPage::getTitleFor( 'MoveRevisions' ),
		wfMessage( 'moverevisionslink' )->escaped(),
		array(),
		array( 'logid' => $id )
	    );
	    $retMessage .= ' ' . wfMessage( 'parentheses' )->rawParams(
		$moverevisions )->escaped();
	}
	return false;
    }
}