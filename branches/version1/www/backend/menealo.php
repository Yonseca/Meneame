<?
// The source code packaged with this file is Free Software, Copyright (C) 2005 by
// Ricardo Galli <gallir at uib dot es>.
// It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
// You can get copies of the licenses here:
// 		http://www.affero.org/oagpl.html
// AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".

include('../config.php');
include(mnminclude.'link.php');

header('Content-Type: text/plain; charset=UTF-8');

if(!($id=check_integer('id'))) {
	error(_('Falta el ID del artículo'));
}

if(empty($_REQUEST['user']) && $_REQUEST['user'] !== '0' ) {
	error(_('Falta el código de usuario'));
}

if (empty($_REQUEST['md5'])) {
	error(_('Falta la clave de control'));
}

$link = new Link;
$link->id=$id;
if(!$link->read()) {
	error(_('Artículo inexistente'). $current_user->user_id . '-'. $_REQUEST['user']);
}

if(!$link->is_votable()) {
	error(_('¡tranquilo cowboy!'));
}

// Only if the link has been not published, let them play
if ($current_user->user_id == 0 && $link->status != 'published') {
	if (! $anonnymous_vote) {
		error(_('Los votos anónimos están temporalmente deshabilitados'));
	} else {
		// Check that there are not too much annonymous votes in 1 hour
		$anon_votes = $db->get_var("select count(*) from votes where vote_type = 'links' and vote_link_id = $id and vote_user_id = 0 and vote_date > date_sub(now(), interval 30 minute) and vote_value > 0");
		if ($anon_votes > $anon_to_user_votes) {
			$user_votes = $anon_to_user_votes * $db->get_var("select count(*) from votes where vote_type = 'links' and vote_link_id = $id and vote_user_id > 0 and vote_date > date_sub(now(), interval  30 minute) and vote_value > 0");
			if ($anon_votes >  $user_votes) {
				// start anti spam measure: assing 1 to previous anonnymous votes
				$db->query("update votes set vote_value = 1 where vote_type = 'links' and vote_link_id = $id and vote_user_id = 0 and vote_date > date_sub(now(), interval  30 minute) and vote_value > 0");
				error(_('Demasiados votos anónimos para esta noticia, inténtelo más tarde'));
			}
		}
	}
}

if($current_user->user_id != $_REQUEST['user']) {
	error(_('Usuario incorrecto'). $current_user->user_id . '-'. htmlspecialchars($_REQUEST['user']));
}

$md5=md5($current_user->user_id.$id.$link->randkey.$globals['user_ip']);
if($md5 !== $_REQUEST['md5']){
	error(_('clave de control incorrecta'));
}

if ($current_user->user_id == 0) $ip_check = 'and vote_ip_int = '.$globals['user_ip_int'];
else $ip_check = '';
$votes_freq = $db->get_var("select count(*) from votes where vote_type='links' and vote_user_id=$current_user->user_id and vote_date > subtime(now(), '0:0:30') $ip_check");

if ($current_user->user_id > 0) $freq = 3;
else $freq = 2;

if ($link->status == 'published')  $freq *= 2; // Allow to play a little more if published

if ($votes_freq > $freq) {
	if ($current_user->user_id > 0 && $current_user->user_karma > 4 && $link->status != 'published') {
    	// Crazy votes attack, decrease karma
		// she does not deserve it :-)
    	require_once(mnminclude.'user.php');
    	$user = new User;
    	$user->id = $current_user->user_id;
    	$user->read();
    	$user->karma = $user->karma - 0.2;
		$user->store();
		warn(_('¡tranquilo cowboy!, tu karma ha bajado: ') . $user->karma);
	} else  {
		warn(_('¡tranquilo cowboy!'));
	}
}

if($current_user->user_id>0) {
	$value = $current_user->user_karma;
} else {
	$value=$anon_karma;
}

if (!$link->insert_vote($current_user->user_id, $value)) {
	//error(_('error insertando voto'));
	error(_('ya ha votado antes'));
}


if ($link->status == 'discard' && $current_user->user_id>0 && $link->karma > $link->votes * 2  && $link->votes > $link->negatives) {
	$link->read();
	$link->status = 'queued';
	$link->store_basic();
}
	
echo "$link->id~$link->votes~$link->negatives~".intval($link->karma)."~".intval($value);

function error($mess) {
	echo "ERROR: $mess";
	die;
}

function warn($mess) {
	echo "WARN: $mess";
	die;
}
?>
