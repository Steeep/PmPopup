<?php
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if(!defined("PLUGINLIBRARY"))
{
    define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

$plugins->add_hook("global_start", "pmpopup_start");
$plugins->add_hook("global_end", "pmpopup_end");


global $templatelist;
if(isset($templatelist))
{
	$templatelist .= ',';
}
	$templatelist .= 'pmpopup_link';


function pmpopup_info()
{
	return array (
		"name"          => "Popup of private messages",
		"description"   => "Shows the last X private messages in a popup window.",
		"website"       => "http://www.magiakdabra.com",
		"author"        => "Steeep",
		"authorsite"    => "http://www.magiakdabra.com",
		"version"       => "1.0",
		"compatibility" => "16*",
	);
}

function pmpopup_is_installed()
{
	global $settings;

    if(isset($settings['pmpopup_enable']))
	{
		return true;
	}
}

function pmpopup_install()
{
	global $PL;
	
	pmpopup_uninstall();

	$PL->settings(
		'pmpopup', 
		'PM Popup', 
		'Options on how to configure and personalize PM Popup',
			array(
				'enable' => array(
					'title' 	  => 'Enable popup of private messages',
					'description' => 'Turns on or off PM Popup.',
					'optionscode' => 'onoff',
					'value' 	  => 1,
				),
				'gidsignore' => array(
					'title' 	  => 'Usergroups to ignore',
					'description' => 'Usergroups, separated by a comma, to ignore. Use the usergroup id, <strong>not the name</strong>.',
					'optionscode' => 'text',
					'value' 	  => '1,5,7',
				),
				'limitmps' => array(
					'title' 	  => 'Limit private messages',
					'description' => 'Set limit the number of private messages you want to appear in the popup.',
					'optionscode' => 'text',
					'value' 	  => '5',
				),
				'enable_ajax' => array(
					'title' 	  => 'Enable use of AJAX',
					'description' => 'Enable or disable the use of AJAX in PM Popup',
					'optionscode' => 'onoff',
					'value' 	  => 1,
				),
				'refresh_interval' => array(
					'title' 	  => 'Refresh interval',
					'description' => 'Select the quantity of seconds for check for new private messages (uses AJAX).',
					'optionscode' => 'text',
					'value' 	  => '30',
				)
			)
	);
	
	$PL->templates(
		'pmpopup',
		'PM Popup',
			array(
				'' => 'This is the PM Popup template.',
				'link' => '<a id="pmpopupl" href="{$mybb->settings[\'bburl\']}/private.php">{$ppmp[\'unread\']}</a>',)
	);
	
	$PL->stylesheet('pmpopup', 'body { border: solid red 8px; }');
}

function pmpopup_uninstall()
{
	global $PL;
	pmpopup_depend();

	$PL->settings_delete('pmpopup');
	$PL->templates_delete('pmpopup', true);
	$PL->stylesheet_delete('pmpopup', true);
}

function pmpopup_activate()
{
	pmpopup_deactivate();
	require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
	
	//find_replace_templatesets('headerinclude', '#'.preg_quote('<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/popup_menu.js?ver=1600"></script>').'#', '<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/popup_menu.js?ver=1600"></script>' . "\n" . '<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/pmpopup.js?ver=1600"></script>');
	find_replace_templatesets('header_welcomeblock_member', '#'.preg_quote('<a href="{$mybb->settings[\'bburl\']}/private.php">{$lang->welcome_pms}</a> {$lang->welcome_pms_usage}').'#', '<!--PMPOPUP-->');
}

function pmpopup_deactivate()
{
	require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
	//find_replace_templatesets('headerinclude', '#'.preg_quote("\n" . '<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/pmpopup.js?ver=1600"></script>').'#', "", 0);
	find_replace_templatesets('header_welcomeblock_member', '#'.preg_quote('<!--PMPOPUP-->').'#', '<a href="{$mybb->settings[\'bburl\']}/private.php">{$lang->welcome_pms}</a> {$lang->welcome_pms_usage}');
}

function pmpopup_depend()
{
    global $PL;

    if(!file_exists(PLUGINLIBRARY))
    {
        flash_message('The PM Popup plugin depends on <a href="http://mods.mybb.com/view/pluginlibrary">PluginLibrary</a>, which is missing. Please install it.', 'error');
        admin_redirect('index.php?module=config-plugins');
    }

    $PL or require_once PLUGINLIBRARY;

    if($PL->version < 3)
    {
        flash_message('The PM Popup plugin depends on <a href="http://mods.mybb.com/view/pluginlibrary">PluginLibrary</a>, which is too old. Please update it.', 'error');
        admin_redirect("index.php?module=config-plugins");
    }
}

function pmpopup_start()
{
	global $PL, $mybb, $db, $parser, $lang, $header, $templates;
	pmpopup_depend();

	if($mybb->settings['pmpopup_enable'] == 1)
	{
		if($mybb->user['pmnotice'] == 2 && $mybb->user['pms_unread'] > 0 && $mybb->settings['enablepms'] != 0 && $mybb->usergroup['canusepms'] != 0 && $mybb->usergroup['canview'] != 0 && ($current_page != "private.php" || $mybb->input['action'] != "read"))
		{
			if(!$PL->is_member($mybb->settings['pmpopup_gidsignore']))
			{
				if(!$parser)
				{
					require_once MYBB_ROOT.'inc/class_parser.php';
					$parser = new postParser;
				}

				if($mybb->user['pms_unread'] == 1)
				{
					$ppmp['unread'] = "Tienes <strong>1</strong> mensaje privado sin leer.";
				}
				else
				{
					$ppmp['unread'] = "Tienes <strong>{$mybb->user['pms_unread']}</strong> mensajes privados sin leer.";
				}
					eval('$linkpmp = "'.$templates->get('pmpopup_link').'";');
					$header = str_replace('<!--PMPOPUP-->', $linkpmp, $header);
					
					//eval("\$linkpmp = \"".$templates->get("pmpopup_link")."\";");
				
				$query = $db->query("
					SELECT pm.subject, pm.pmid, pm.message, fu.username AS fromusername, fu.uid AS fromuid
					FROM ".TABLE_PREFIX."privatemessages pm
					LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
					WHERE pm.folder='1' AND pm.uid='{$mybb->user['uid']}' AND pm.status='0'
					ORDER BY pm.dateline DESC
					LIMIT {$mybb->settings['pmpopup_limitmps']}
				");

				while($ppm = $db->fetch_array($query))
				{
					$ppm['subject'] = $parser->parse_badwords($ppm['subject']);
					if($ppm['fromuid'] == 0)
					{
						$ppm['fromusername'] = $lang->mybb_engine;
						$userppm = $ppm['fromusername'];
					}
					else
					{
						$get_userppm = get_user($ppm['fromuid']);
						$userppm_format = format_name($get_userppm['username'], $get_userppm['usergroup'], $get_userppm['displaygroup']);
						$userppm = build_profile_link($userppm_format, $ppm['fromuid']);
					}
				}
			}
		}
	}
}

function pmpopup_end()
{
	global $templates, $header;

	eval('$linkpmp = "'.$templates->get('pmpopup_link').'";');
	$header = str_replace('<!--PMPOPUP-->', $linkpmp, $header);
}

?>