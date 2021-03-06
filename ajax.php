<?php
// +-----------------------------------------------------------------------+
// | Lexiglot - A PHP based translation tool                               |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2011-2013 Damien Sorel       http://www.strangeplanet.fr |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

/**
 * This page is called by jQuery ro perform some AJAX actions
 * an ephemeral key is mandatory for security
 */

define('LEXIGLOT_PATH', './');
define('IN_AJAX', 1);
include(LEXIGLOT_PATH . 'include/common.inc.php');


if (!isset($_POST['action']))
{
  close_ajax('error', 'Undefined action');
}

if (!verify_ephemeral_key(@$_POST['key']))
{
  close_ajax('error', 'Invalid/expired form key');
}

switch ($_POST['action'])
{
  // SAVE A ROW
  case 'save_row':
  {    
    if (empty($_POST['row_value']))
    {
      close_ajax('error', 'String is empty');
    }
    
    if ( empty($_POST['row_name']) or empty($_POST['project']) or empty($_POST['language']) or empty($_POST['file']) )
    {
      close_ajax('error', 'Bad parameters');
    }
    
    $key = utf8_decode($_POST['row_name']);
    $text = utf8_decode($_POST['row_value']);
    clean_eol($text);
    
    $_LANG = load_language($_POST['project'], $_POST['language'], $_POST['file'], $key);
    $_LANG_default = load_language($_POST['project'], $conf['default_language'], $_POST['file'], $key);
    
    if ( !isset($_LANG[$key]) or $text!=$_LANG[$key]['row_value'] )
    {
      if ($text!=$conf['equal_to_ref'] && !check_sprintf($_LANG_default[$key]['row_value'], $text))
      {
        close_ajax('error', 'Number of "%s" and/or "%d" mismatch');
      }
      
      $query = '
INSERT INTO `'.ROWS_TABLE.'`(
    language,
    project,
    file_name,
    row_name,
    row_value,
    user_id,
    last_edit,
    status
  )
  VALUES(
    "'.mres($_POST['language']).'",
    "'.mres($_POST['project']).'",
    "'.mres($_POST['file']).'",
    "'.mres($key).'",
    "'.mres($text).'",
    "'.$user['id'].'",
    NOW(),
    "'.(isset($_LANG[$key]) ? 'edit' : 'new').'" 
  )
  ON DUPLICATE KEY UPDATE
    last_edit = NOW(),
    row_value = "'.mres($text).'",
    status = IF(status="done","edit",status)
;';
      $db->query($query);      
      close_ajax('success', 'Saved');
    }
    else
    {
      close_ajax('warning', 'Already up-to-date');
    }
  }
  
  
  // HISTORY OF A ROW
  case 'row_log':
  {
    if ( empty($_POST['row_name']) or empty($_POST['project']) or empty($_POST['language']) or empty($_POST['file']) )
    {
      close_ajax('error', 'Bad parameters');
    }
    
    $key = utf8_decode($_POST['row_name']);
    
    $query = '
SELECT
    r.row_value,
    r.user_id,
    u.'.$conf['user_fields']['username'].' AS username,
    r.last_edit
  FROM '.ROWS_TABLE.' AS r
    INNER JOIN '.USERS_TABLE.' AS u
    ON u.id = r.user_id
  WHERE
    r.row_name = "'.mres($key).'"
    AND r.project = "'.mres($_POST['project']).'"
    AND r.language = "'.mres($_POST['language']).'"
    AND r.file_name = "'.mres($_POST['file']).'"
  ORDER BY r.last_edit DESC
;';
    $result = $db->query($query);
    
    $out = array();
    while ($entry = $result->fetch_assoc())
    {
      array_push($out, '<li><pre>'.htmlspecialchars($entry['row_value']).'</pre> <span>by <a href="'.get_url_string(array('user_id'=>$entry['user_id']), true, 'profile').'">'.$entry['username'].'</a> on '.format_date($entry['last_edit']).'</span></li>');
    }
    if (!count($out))
    {
      array_push($out, '<li><i>No data</i></li>');
    }
    
   close_ajax('success', '<h5>Past translations :</h5>
    <ul class="row_log">
      '.implode('', $out).'
    </ul>');
  }
  
  // MAKE STATS
  case 'make_stats':
  {
    if ( !empty($_POST['project']) and !array_key_exists($_POST['project'], $conf['all_projects']) )
    {
      close_ajax('error', 'Bad project id');
    }
    if ( !empty($_POST['language']) and !array_key_exists($_POST['language'], $conf['all_languages']) )
    {
      close_ajax('error', 'Bad language id');
    }
    
    if ( empty($_POST['project']) and empty($_POST['language']) )
    {
      close_ajax('error', 'Bad parameters');
    }
    else if ( !empty($_POST['project']) and !empty($_POST['language']) )
    {
      $stats = make_stats($_POST['project'], $_POST['language']);
    }
    else if ( !empty($_POST['project']) and empty($_POST['language']) )
    {
      $stats = make_project_stats($_POST['project']);
    }
    else if ( empty($_POST['project']) and !empty($_POST['language']) )
    {
      $stats = make_language_stats($_POST['language']);
    }
    
    close_ajax('success', $stats);
  }
  
  default:
    close_ajax('error', 'Bad parameters');
}


function close_ajax($errcode, $data=null)
{
  echo json_encode(array('errcode'=>$errcode, 'data'=>$data));
  exit;
}

?>