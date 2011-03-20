<?php
/**
 * Evil quick reply
 *
 * @package	phpBB3
 * @version 1.0.1
 * @copyright (c) 2007 eviL3
 * @license	http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
 * This function will load everything needed for the evil quick reply
 *
 * @param int $topic_id
 * @param int $forum_id
 * @param array $topic_data
 */
function quick_reply($topic_id, $forum_id, &$topic_data)
{
	global $template, $user, $auth, $db;
	global $phpbb_root_path, $phpEx, $config;

	// Some little config for the quick reply, allows the admin to change these default values through the database.
	$qr_config = array(
		'enabled'			=> true, 	// Disable it easily
		'display_subject'	=> false,	// Do you want the subject line to be displayed
		'hide_box'			=> true,	// Shall the box be hidden on pageload?
		'resize'			=> true,	// Display the buttons to resize the textarea?
	);

	// do evil_qr_ prefixed of the config values exist in $config
	// /me slaps highway of life
	foreach (array_keys($qr_config) as $key)
	{
		if (isset($config['evil_qr_' . $key]))
		{
			$qr_config[$key] = $config['evil_qr_' . $key];
		}
	}

	// Check if user has reply permissions for this forum or the topic is locked (thanks damnian)
	if (!$auth->acl_get('f_reply', $forum_id) || ($topic_data['topic_status'] == ITEM_LOCKED && !$auth->acl_get('m_lock', $forum_id)) || !$qr_config['enabled'])
	{
		return;
	}

	// Hidden fields
	$s_hidden_fields = array(
		't'			=> $topic_id,
		'f'			=> $forum_id,
		'mode'		=> 'reply',
		'lastclick'	=> time(),
		'icon'		=> 0,
	);

	// Set preferences such as allow smilies, bbcode, attachsig
	$reply_prefs = array(
		'disable_bbcode'	=> ($config['allow_bbcode'] && $user->optionget('bbcode')) ? false : true,
		'disable_smilies'	=> ($config['allow_smilies'] && $user->optionget('smilies')) ? false : true,
		'disable_magic_url'	=> false,
		'attach_sig'		=> ($config['allow_sig'] && $user->optionget('attachsig')) ? true: false,
		'notify'			=> ($config['allow_topic_notify'] && ($user->data['user_notify'] || isset($topic_data['notify_status']))) ? true : false,
		'lock_topic'		=> ($topic_data['topic_status'] == ITEM_LOCKED && $auth->acl_get('m_lock', $forum_id)) ? true : false,
	);

	foreach ($reply_prefs as $name => $value)
	{
		if ($value)
		{
			$s_hidden_fields[$name] = 1;
		}
	}

	$subject = ((strpos($topic_data['topic_title'], 'Re: ') !== 0) ? 'Re: ' : '') . censor_text($topic_data['topic_title']);

	if (!$qr_config['display_subject'])
	{
		// /me is a show-off
		list($s_hidden_fields['subject'], $subject) = array($subject, '');
	}

	// Confirmation code handling (stolen from posting.php)
	if ($config['enable_post_confirm'] && !$user->data['is_registered'])
	{
		// Show confirm image
		$sql = 'DELETE FROM ' . CONFIRM_TABLE . "
			WHERE session_id = '" . $db->sql_escape($user->session_id) . "'
				AND confirm_type = " . CONFIRM_POST;
		$db->sql_query($sql);

		// Generate code
		$code = gen_rand_string(mt_rand(5, 8));
		$confirm_id = md5(unique_id($user->ip));
		$seed = hexdec(substr(unique_id(), 4, 10));

		// compute $seed % 0x7fffffff
		$seed -= 0x7fffffff * floor($seed / 0x7fffffff);

		$sql = 'INSERT INTO ' . CONFIRM_TABLE . ' ' . $db->sql_build_array('INSERT', array(
			'confirm_id'	=> (string) $confirm_id,
			'session_id'	=> (string) $user->session_id,
			'confirm_type'	=> (int) CONFIRM_POST,
			'code'			=> (string) $code,
			'seed'			=> (int) $seed,
		));

		$db->sql_query($sql);

		$template->assign_vars(array(
			'S_CONFIRM_CODE'	=> true,
			'CONFIRM_ID'		=> $confirm_id,
			'CONFIRM_IMAGE'		=> '<img src="' . append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=confirm&amp;id=' . $confirm_id . '&amp;type=' . CONFIRM_POST) . '" alt="" title="" />',
		));
	}

	// new RC6/RC7 stuff
	add_form_key('posting');

	// Page title & action URL, include session_id for security purpose
	$s_action = append_sid("{$phpbb_root_path}posting.$phpEx", false, true, $user->session_id);

	// Assign template variables
	$template->assign_vars(array(
		'QR_SUBJECT'			=> $subject,

		'S_QR_HIDDEN_FIELDS'	=> build_hidden_fields($s_hidden_fields),
		'S_QR_POST_ACTION'		=> $s_action,

		'S_QR_ENABLED'			=> $qr_config['enabled'], // this is true anyway :P
		'S_QR_SUBJECT'			=> $qr_config['display_subject'],
		'S_QR_HIDE_BOX'			=> $qr_config['hide_box'],
		'S_QR_RESIZE'			=> $qr_config['resize'],
	));
}

?>