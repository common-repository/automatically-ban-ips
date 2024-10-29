<?php
/*
Plugin Name: Automatically Ban IPs
Plugin URI: https://qdb.wp.kukmara-rayon.ru/automatically-ban-ips/
Description: If somebody open site too frequently ban their IPs with .htaccess.
Version: 0.2
Author: Dinar Qurbanov
Author URI: http://qdb.wp.kukmara-rayon.ru/
Author E-Mail: qdinar@gmail.com qdb@ya.ru d-84@bk.ru
Network: true

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


// $ban_ip_db_version = "1.0";

//http://codex.wordpress.org/Function_Reference/wp_schedule_event
register_activation_hook( __FILE__, 'automatically_ban_ips_install' );

function automatically_ban_ips_install() {
	//http://codex.wordpress.org/Creating_Tables_with_Plugins
	global $wpdb;
	// global $ban_ip_db_version;
	$table_name = $wpdb->prefix . 'check_ip';
	$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` bigint(11) NOT NULL,
  `cnt` int(11) NOT NULL DEFAULT '0',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`) USING BTREE,
  KEY `ip` (`ip`) USING BTREE
) ENGINE=MEMORY  DEFAULT CHARSET=utf8;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	// add_option( "wp_ban_ip_db_version", $ban_ip_db_version );
	$options=[];
	$options['count'] = 60;
	$options['time'] = 1;
	automatically_ban_ips_option($options);
}



add_action( 'init', 'automatically_ban_ips_run' );

function automatically_ban_ips_run(){
	// echo 'OK123'; exit;
	// $logh = fopen(plugin_dir_path( __FILE__ ).'log.txt', 'a');
	global $user_ID;
	if (isset($user_ID) && intval($user_ID) > 0 ) {
		return true;
	}
	// code from http://www.php-example.ru/ogranichenie-kolichestva-zaprosov-s-odnogo-ip/
	// https://web.archive.org/web/20150223083050/http://www.php-example.ru/ogranichenie-kolichestva-zaprosov-s-odnogo-ip/
	// is used in this function.
	// i have found that author of this site is Andrey Lucenko https://vk.com/id41902245 , and he has allowed me to use this code here.
	// $dbuser='dbuser';  //Пользователь базы данных
	// $dbpass='dbpass';  //Пароль пользователя базы данных
	// $db='db'; //Имя базы данных
	global $wpdb;
	$table_name = $wpdb->prefix . 'check_ip';
	// $try_count=7; //Лимиты попыток
	// $try_time=10; //Период лимита попыток в секундах
	// include(plugin_dir_path( __FILE__ ).'wp-ban-ip-config.php');
	$options = automatically_ban_ips_option();
	$try_count=$options['count'];
	$try_time=$options['time'];
	try {
		//Подключение к БД
		// $mysqli = new mysqli("localhost", $dbuser, $dbpass, $db);
		// if ($mysqli->connect_errno) {
			// throw new Exception('Не удалось подключиться к БД');
		// }
		//Определяем IP адрес
		$ip=$_SERVER['REMOTE_ADDR'];
		// fwrite($logh, "\n".$ip);
		// echo 'OK'; exit;
		$long = ip2long($ip);
		if ($long == -1 || $long === FALSE) {
			throw new Exception('Ошибка определения IP');
		}
		//Подготавливаем данные для запросов
		$long = sprintf('%u', $long);
		$old_time=date('Y-m-d H:i:s',time()-$try_time);
		//Ищем запись о попытках подключения 
		//$res = $mysqli->query("SELECT `id`,`cnt` FROM `check_ip` where `ip`='$long' and `date`>='$time'");
		$sql="SELECT `id`,`cnt` FROM `$table_name` where `ip`='$long' and `date`>='$old_time'";
		//$res = $wpdb->get_results($sql,ARRAY_N);
		$row = $wpdb->get_row($sql,ARRAY_N);
		//if ($res->num_rows>0){
		//if (count($res)>0){
		if ($row){
			//$row = $res->fetch_assoc();
			//$row = $res[0];
			//if ($row['cnt']>=$try_count){
			if ($row[1]>=$try_count){
				//throw new Exception('Превышено количество попыток, повторите запрос через 1-2 минуты');
				$htaccessh = fopen(ABSPATH.'.htaccess', 'a');
				fwrite($htaccessh, "\ndeny from $ip");
				fclose($htaccessh);
			}else{
				//Увеличиваем счетчик попыток
				//$mysqli->query("update `check_ip` set `cnt`=`cnt`+1 where `id`='{$row['id']}'");
				$wpdb->update($table_name,array('cnt'=>$row[1]+1),array('id'=>$row[0]));
			}
		}else{
			//Вставляем запись о попытке запроса
			$time=date('Y-m-d H:i:s',time());
			//$mysqli->query("INSERT INTO `check_ip` ( `ip`, `cnt`, `date`) VALUES ('$long', 1, '$time')");
			$wpdb->insert($table_name,array('ip'=>$long,'cnt'=>1,'date'=>$time));
			/*
			$sql="SELECT `id` FROM `$table_name` where `ip`='$long'";
			$row = $wpdb->get_row($sql,ARRAY_N);
			if($row){
				$wpdb->update($wpdb->prefix.'check_ip',array('cnt'=>1,'date'=>$time),array('id'=>$row[0]));
			}else{
				$wpdb->insert($table_name,array('ip'=>$long,'cnt'=>1,'date'=>$time));
			}
			*/
		}
		// Удаляем записи о старых запросах
		//$sql="DELETE FROM `$table_name` where `ip`='$long' and `date`<'$old_time'";
		$sql="DELETE FROM `$table_name` where `date`<'$old_time'";
		$wpdb->query($sql);
	} catch ( Exception $e ) {
		//Обработка ошибочных ситуаций
		//echo $e->getMessage();
		// fwrite($logh, "\n".$e->getMessage());
	}
	// fclose($logh);
}



function automatically_ban_ips_uninstall() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'check_ip';
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);
	delete_site_option('automaticallybanips');
}    
register_deactivation_hook( __FILE__, 'automatically_ban_ips_uninstall' );



// some code below is copied from Question Antispam for Comment and Signup
// where it was copied from Hashcash plugin


function automatically_ban_ips_option($save = false){
	if($save) {
		update_site_option('automaticallybanips', $save);

		return $save;
	} else {
		$options = get_site_option('automaticallybanips');

		if(!is_array($options))
			$options = array('count'=>60, 'time'=>1);

		return $options;
	}
}

add_action('admin_menu', 'automatically_ban_ips_add_options_to_admin');

function automatically_ban_ips_add_options_to_admin() {
	if(current_user_can('update_core')){
		add_options_page(
			_x('Automatically Ban IPs','page title'),
			_x('Automatically Ban IPs','menu title'),
			'manage_options',
			'automatically_ban_ips_config',
			'automatically_ban_ips_admin_options'
		);
	}
}

function automatically_ban_ips_admin_options() {
	
	$options = automatically_ban_ips_option();

	// POST HANDLER
	if(isset($_POST['automaticallybanips-submit'])){
		check_admin_referer( 'automaticallybanips-options' );
		if ( function_exists('current_user_can') && !current_user_can('manage_options') )
			die(__('Current user is not authorized to manage options'));

		// $try_count=7; //Лимиты попыток
		// $try_time=10; //Период лимита попыток в секундах
		// $options['count'] = strip_tags(stripslashes($_POST['automaticallybanips-count']));
		// $options['time'] = strip_tags(stripslashes($_POST['automaticallybanips-time']));
		$options['count'] = absint($_POST['automaticallybanips-count']);
		$options['time'] = absint($_POST['automaticallybanips-time']);
		automatically_ban_ips_option($options);
	}
	
	// MAIN FORM
	echo '<style type="text/css">
		.wrap h3 { color: black; background-color: #e5f3ff; padding: 4px 8px; }

		.sidebar {
			border-right: 2px solid #e5f3ff;
			width: 200px;
			float: left;
			padding: 0px 20px 0px 10px;
			margin: 0px 20px 0px 0px;
		}

		.sidebar input {
			background-color: #FFF;
			border: none;
		}

		.main {
			float: left;
			width: 600px;
		}

		.clear { clear: both; }

		.input {width:100%;}
	</style>';

	echo '<div class="wrap">';

	echo '<div class="sidebar">';
	echo '<h3>'._x('About','automatically ban ips').'</h3>';
	echo '<ul>
	<li><a href="http://qdb.wp.kukmara-rayon.ru/">'._x('Plugin\'s Homepage','automatically ban ips').'</a></li>';
	echo '</ul>';		
	echo '</div>';

	echo '<div class="main">';
	echo '<h2>'.__('Settings').'</h2>';

	//echo '<h3>Standard Options</h3>';
	// echo '<form method="POST" action="' . esc_url( '?page=' . $_GET[ 'page' ] . '&updated=true' ) . '">';
	echo '<form method="POST" action="?page=' . esc_attr( $_GET['page'] ) . '&updated=true">';
	wp_nonce_field('automaticallybanips-options');
	// if( function_exists( 'is_site_admin' ) ) { // MU only
		// echo "<p>'Here was MU only block'</p>";
	// }
	// count
	echo '<p><label for="automaticallybanips-count">' . _x('Count', 'automatically ban ips') . '</label> ';
	echo '<input id="automaticallybanips-count" name="automaticallybanips-count" value="'.$options['count'].'" class="input" />';
	echo '</p>';
	// time
	echo '<p><label for="automaticallybanips-time">' . _x('Time:', 'automatically ban ips') . '</label>';
	echo '<input id="automaticallybanips-time" name="automaticallybanips-time" value="'.$options['time'].'" class="input" />';
	echo '<input type="hidden" id="automaticallybanips-submit" name="automaticallybanips-submit" value="1" />';
	echo '<input type="submit" id="automaticallybanips-submit-override" name="automaticallybanips-submit-override" value="'.__('Save Automatically Ban IPs Settings').'"/>';
	echo '</form>';
	echo '</div>';

	echo '<div class="clear">';
	echo '<p style="text-align: center; font-size: .85em;">'.__('Author: Dinar Qurbanov, using free plugins\' codes').'</p>';
	echo '</div>';

	echo '</div>';
}



















