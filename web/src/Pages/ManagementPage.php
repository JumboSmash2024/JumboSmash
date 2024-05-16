<?php

namespace JumboSmash\Pages;

use JumboSmash\HTML\{HTMLBuilder, HTMLElement};
use JumboSmash\Services\AuthManager;
use JumboSmash\Services\Database;
use JumboSmash\Services\Logger;
use JumboSmash\Services\Management;
use stdClass;

class ManagementPage extends BasePage {

    private Database $db;
    private Logger $logger;

    public function __construct() {
        parent::__construct( 'Management' );
        $this->addStyleSheet( 'management-styles.css' );
        $this->db = new Database();
        $this->logger = new Logger();
    }

    protected function getBodyElements(): array {
        return [
            HTMLBuilder::element(
                'div',
                [
                    HTMLBuilder::element( 'h1', 'Manage' ),
                    ...$this->getMainDisplay(),
                ]
            ),
        ];
    }

    private function getMainDisplay(): array {
        $authError = $this->getAuthError();
        if ( $authError ) {
            return [ $authError ];
        }
        $userId = AuthManager::getLoggedInUserId();
        $status = $this->db->getAccountStatus( $userId );
        if ( ( $status & Management::FLAG_MANAGER ) !== Management::FLAG_MANAGER ) {
            return [ $this->notManagerError() ];
        }
        $isPost = ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST';
        if ( !$isPost ) {
            return [ $this->getManagerTable() ];
        }
        return $this->trySubmit();
    }

    private function trySubmit(): array {
        $targetUser = $_POST['target-user'] ?? false;
        $messages = [];
        if ( !$targetUser || !is_numeric( $targetUser ) ) {
            $messages[] = HTMLBuilder::element( 'p', 'Missing valid target user' );
        }
        $action = $_POST['action'] ?? false;
        $actionMap = [
            'verify' => 'markVerified',
            'unverify' => 'unmarkVerified',
            'enable' => 'markEnabled',
            'disable' => 'markDisabled',
        ];
        if ( !$action || !array_key_exists( $action, $actionMap ) ) {
            $messages[] = HTMLBuilder::element( 'p', 'Missing valid action' );
        }
        if ( $messages ) {
            return [ ...$messages, $this->getManagerTable() ];
        }

        $userId = (int)$targetUser;
        $currStatus = $this->db->getAccountStatus( $userId );

        $actionHandler = $actionMap[$action];
        $result = $this->$actionHandler( $userId, $currStatus );
        $this->logger->notice(
            'Performing action `{action}` for user #{id}; result={res}',
            [ 'action' => $action, 'id' => $userId, 'res' => $result ]
        );
        return [
            HTMLBuilder::element( 'p', $result ),
            HTMLBuilder::element( 'br' ),
            $this->getManagerTable(),
        ];
    }

    private function markDisabled( int $userId, int $currStatus ): string {
        if ( Management::hasFlag( $currStatus, Management::FLAG_DISABLED ) ) {
            return 'Already disabled';
        }
        $this->db->setAccountStatus( $userId, $currStatus | Management::FLAG_DISABLED );
        return 'Account disabled';
    }

    private function markEnabled( int $userId, int $currStatus ): string {
        if ( !Management::hasFlag( $currStatus, Management::FLAG_DISABLED ) ) {
            return 'Already enabled';
        }
        $this->db->setAccountStatus( $userId, $currStatus & ~Management::FLAG_DISABLED );
        return 'Account enabled';
    }

    private function markVerified( int $userId, int $currStatus ): string {
        if ( Management::hasFlag( $currStatus, Management::FLAG_VERIFIED ) ) {
            return 'Already verified';
        }
        $this->db->setAccountStatus( $userId, $currStatus | Management::FLAG_VERIFIED );
        return 'Account verified';
    }

    private function unmarkVerified( int $userId, int $currStatus ): string {
        if ( !Management::hasFlag( $currStatus, Management::FLAG_VERIFIED ) ) {
            return 'Already unverified';
        }
        $this->db->setAccountStatus( $userId, $currStatus & ~Management::FLAG_VERIFIED );
        return 'Account unverified';
    }

    private function notManagerError(): HTMLElement {
        return HTMLBuilder::element(
            'div',
            'ERROR: Not a manager!',
            [ 'class' => 'js-error' ]
        );
    }

    private function getRowForUser( stdClass $dbRow ): HTMLElement {
        $userId = (int)( $dbRow->user_id );
        $userStatus = (int)( $dbRow->user_status );

        $cells = array_map(
            static fn ( $val ) => HTMLBuilder::element( 'td', (string)$val ),
            array_values( (array)$dbRow )
        );

        $verified = Management::hasFlag( $userStatus, Management::FLAG_VERIFIED );
        $disabled = Management::hasFlag( $userStatus, Management::FLAG_DISABLED );

        $empty = HTMLBuilder::element( 'td', '' );
        if ( $verified ) {
            $cells[] = $empty;
            $cells[] = $this->makeFormCell( $userId, 'unverify', 'Unverify' );
        } else {
            $cells[] = $this->makeFormCell( $userId, 'verify', 'Verify' );
            $cells[] = $empty;
        }

        if ( $disabled ) {
            $cells[] = $this->makeFormCell( $userId, 'enable', 'Enable' );
            $cells[] = $empty;
        } else {
            $cells[] = $empty;
            $cells[] = $this->makeFormCell( $userId, 'disable', 'Disable' );
        }

        return HTMLBuilder::element(
            'tr',
            $cells,
            [ 'class' => 'js-manage-user-row' ]
        );
    }

    private function makeFormCell(
        int $targetUser,
        string $action,
        string $label
    ): HTMLElement {
        $form = HTMLBuilder::element(
			'form',
			[
				HTMLBuilder::hidden( 'target-user', $targetUser ),
				HTMLBuilder::hidden( 'action', $action ),
				HTMLBuilder::element( 'button', $label, [ 'type' => 'submit' ] ),
			],
			[ 'method' => 'POST' ]
		);
        return HTMLBuilder::element( 'td', $form );
    }

    private function getForm(): HTMLElement {
        $fields = $this->getFormFields();
        if ( !$fields ) {
            return HTMLBuilder::element(
                'div',
                'No new Jumbos to respond to :('
            );
        };
        return HTMLBuilder::element(
            'form',
            $fields,
            [
                'id' => 'js-submit-response',
                'action' => './response.php',
                'method' => 'POST',
            ]
        );
    }

    private function getManagerTable(): HTMLElement {
        $db = new Database;
        $allUsers = $db->getUsersForManagement();

        // Will ALWAYS have at least 1 user - the manager
        $tableRows = array_map(
            fn ( $row ) => $this->getRowForUser( $row ),
            $allUsers
        );

        $columns = [
            'ID', 'Name', 'Personal', 'Status',
            'Verify', 'Unverify', 'Enable', 'Disable'
        ];
        $columnHeaders = array_map(
            static fn ( $text ) => HTMLBuilder::element( 'th', $text ),
            $columns
        );

        $thead = HTMLBuilder::element(
            'thead',
            HTMLBuilder::element( 'tr', $columnHeaders )
        );
        $tbody = HTMLBuilder::element( 'tbody', $tableRows );

        $table = HTMLBuilder::element(
            'table',
            [ $thead, $tbody ],
            [ 'id' => 'js-management-table' ]
        );
        return $table;
    }

}