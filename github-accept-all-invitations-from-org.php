<?php

require_once 'vendor/autoload.php';

$github_token_keyfile      = '.gh-token';
$github_token_keyfile_path = __DIR__ . DIRECTORY_SEPARATOR . $github_token_keyfile;

$last_repo_file      = '.last-repo';
$last_repo_file_path = __DIR__ . DIRECTORY_SEPARATOR . $last_repo_file;

/**
 * Prompt for token if file doesn't exist.
 */
$github_token = '';
if ( true === file_exists( $github_token_keyfile_path ) ) {
	$github_token = trim( file_get_contents( $github_token_keyfile_path ) );
}
if ( true === empty( $github_token ) ) {
	$github_token = readline( 'Paste your github token (should have all privileges assigned):' );
	file_put_contents( $github_token_keyfile_path, $github_token );
}

/**
 * Prompt for org name and use last used as default.
 */
$last_org_name = '';
if ( true === file_exists( $last_repo_file_path ) ) {
	$last_org_name = trim( file_get_contents( $last_repo_file_path ) );
}

$org_name = readline( 'Org name to accept invitations from [' . $last_org_name . ']:' );
if ( true === empty( $org_name ) ) {
	$org_name = $last_org_name;
}
file_put_contents( $last_repo_file_path, $org_name );

/**
 * Is token or org_name empty?
 * That is not allowed at this moment.
 */
if ( true === empty( $github_token ) ) {
	echo 'Token not defined' . PHP_EOL;
	exit( 1 );
}

if ( true === empty( $org_name ) ) {
	echo 'With great power comes great responsibility. Only one organization invitations can be accepted.' . PHP_EOL;
	exit( 1 );
}

// prepare headers.
$headers = array(
	'Authorization' => 'token ' . $github_token,
);

// prepare guzzle client for https://api.github.com.
$client = new GuzzleHttp\Client(
	array(
		'base_uri' => 'https://api.github.com/',
	)
);

/**
 * Get invitations from user/repository_invitations
 */
$result = $client->request(
	'GET',
	'user/repository_invitations?per_page=100',
	array(
		'headers' => $headers,
	)
);

$http_status_code = intval( $result->getStatusCode() );

if ( 200 === $http_status_code ) {
	echo "Reading invitations:" . PHP_EOL;
} else {
	echo 'Unexpected HTTP results when getting data from user/repository_invitations: ' . $result->getStatusCode() . PHP_EOL;
	if ( 400 <= $http_status_code && 500 > $http_status_code ) {
		// most of 400 errors would mean that token wasn't correct - so it can be removed.
		unlink( $github_token );
	}

	exit( 1 );
}

$result_counter = array(
	'success' => 0,
	'failed'  => 0,
	'skipped' => 0,
);

$invitations = json_decode( $result->getBody() );

echo 'Processing ' . count( $invitations ) . ' invitations' . PHP_EOL;
foreach ( $invitations as $invitation ) {
	$repo_owner      = $invitation->repository->owner->login;
	$repo_name       = $invitation->repository->full_name;
	$invitation_id   = $invitation->id;
	$invitation_node = $invitation->node_id;

	if ( $repo_owner === $org_name ) {
		$accept_headers = array_merge(
			$headers,
			array(
				'Accept' => 'application/vnd.github.v3+json',
			)
		);

		$accept_result = $client->request(
			'PATCH',
			'user/repository_invitations/' . $invitation_id,
			array(
				'headers' => $accept_headers,
			)
		);

		$accept_http_status_code = intval( $accept_result->getStatusCode() );
		switch ( $accept_http_status_code ) {
			case 204:
				echo '[SUCCESS] Invitation accepted for repository: ' . $repo_name . PHP_EOL;
				$result_counter['success'] ++;
				break;
			case 304:
				echo 'Invitation was already accepted for repository: ' . $repo_name . PHP_EOL;
				$result_counter['skipped'] ++;
				break;
			case 404:
				echo '[FAILED] Invitation has expired or removed: ' . $repo_name . PHP_EOL;
				$result_counter['failed'] ++;
				break;
			default:
				echo '[FAILED] Invitation for ' . $repo_name . ' can\'t be processed at this time.' . PHP_EOL;
				$result_counter['failed'] ++;
		}
	} else {
		$result_counter['skipped'] ++;
	}
}
echo '=============================================================' . PHP_EOL;
echo 'Summary:' . PHP_EOL;
echo 'Accepted invitations: ' . $result_counter['success'] . PHP_EOL;
echo 'Skipped invitations: ' . $result_counter['skipped'] . PHP_EOL;
echo 'Failed invitations: ' . $result_counter['failed'] . PHP_EOL;
echo '=============================================================' . PHP_EOL;
