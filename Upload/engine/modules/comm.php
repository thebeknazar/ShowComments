<?php
/*
=====================================================
Вывод последних комментариев
-----------------------------------------------------
Автор: Gameer (24.07.2015)
Web-site: http://igameer.ru/port/49-showcomments.html
-----------------------------------------------------
Copyright (c) 2011 - 2015
=====================================================
Файл: comm.php
-----------------------------------------------------
Назначение: Вывод последних комментариев в любом месте любого tpl файла
=====================================================
*/

if (!defined('DATALIFEENGINE')) {
	die("Hacking attempt!");
}
if(!class_exists('Comments')) 
{
	class Comments 
	{
	
		public function __construct() 
		{
			global $db, $config, $member_id, $user_group;
			$this->config = &$config;
			$this->db = $db;
			$this->member = $member_id;
			$this->group = $user_group;
		}

		public function New_Cfg($cfg) // создаем новый конфиг
		{
			$this->comm_cfg = $cfg;
		}
		
		public function Start($CommCfg)
		{
			$this->New_Cfg($CommCfg); // создаем новый конфиг
			$where = array();
			
			// проверка некоторых параметров конфига по версиях
			$allow_alt_url = ($this->config['version_id'] >= '10.2') ? $this->config['allow_alt_url'] == '1' : $this->config['allow_alt_url'] == "yes";
			$allow_cache = ($this->config['version_id'] >= '10.2') ? $this->config['allow_cache'] == '1' : $this->config['allow_cache'] == "yes";
			$allow_multi_category = ($this->config['version_id'] >= '10.2') ? $this->config['allow_multi_category'] == '1' : $this->config['allow_multi_category'] == "yes";
			
			if ($this->config['version_id'] >= '10.5' AND $this->comm_cfg['rating_comm']) // рейтинг комментариев только для DLE 10.5 и выще
				$where[] = "c.rating > {$this->comm_cfg[rating_comm]}";
			
			// работа с категориями
			if( $allow_multi_category )
			{
				if($this->comm_cfg['stop_category'])
					$where[] = "category NOT REGEXP '[[:<:]](" . $this->Explode_Category($this->comm_cfg['stop_category'], "multi") . ")[[:>:]]'";
				if($this->comm_cfg['from_category'])
					$where[] = "category REGEXP '[[:<:]](" .  $this->Explode_Category($this->comm_cfg['from_category'], "multi") . ")[[:>:]]'";
			}
			else
			{
				if($this->comm_cfg['stop_category'])
					$where[] = "category NOT IN ('" . $this->Explode_Category($this->comm_cfg['stop_category']) . "')";
				if($this->comm_cfg['from_category'])
					$where[] = "category IN ('" . $this->Explode_Category($this->comm_cfg['from_category']) . "')";
			}
			
			if($this->comm_cfg['news_xfield']) // работа с доп полями новостей
				$where[] = $this->Explode_xField($this->comm_cfg['news_xfield'], "p.xfields");
			if($this->comm_cfg['user_xfield']) // работа с доп полями пользователей
				$where[] = $this->Explode_xField($this->comm_cfg['user_xfield'], "u.xfields");
			
			// работа с новостями
			if($this->comm_cfg['stop_id'])
				$where[] = $this->Explode_NewsID($this->comm_cfg['stop_id']);
			if($this->comm_cfg['from_id'])
				$where[] = $this->Explode_NewsID($this->comm_cfg['from_id']);
			
			if($this->comm_cfg['ncomm']) // выводим только с комментариями у новостей больше чем
				$where[] = "p.comm_num > {$this->comm_cfg[ncomm]}";
			
			if($this->comm_cfg['fixed']) // выводим только с зафиксированых новостей
				$where[] = "p.fixed = 1";
			
			if($this->comm_cfg['tags']) // выводим только с с тех новостей которые имеют теги
			{ 
				$t = explode(',', $this->comm_cfg['tags']);
				$t = implode('|', $t);
				$where[] = "p.tags regexp '[[:<:]](".$t.")[[:>:]]'";
			}
			
			if($this->comm_cfg['news_read']) // выводим только с комментариями у новостей больше чем
				$where[] = "e.news_read > {$this->comm_cfg[news_read]}";
			
			if($this->comm_cfg['rating_news']) // выводим только с комментариями у новостей больше чем
				$where[] = "e.rating > {$this->comm_cfg[rating_news]}";
			
			if($this->comm_cfg['only_avatar']) // выводим только с аватарами
				$where[] = "u.foto != ''";
			
			if($this->comm_cfg['only_news']) // выводим только с новостями
				$where[] = "u.news_num > 0";
			
			if($this->comm_cfg['only_fav']) // выводим только с закладками
				$where[] = "u.favorites != ''";
			
			if($this->comm_cfg['only_fullname']) // выводим только с полным именем
				$where[] = "u.fullname != ''";
			
			if($this->comm_cfg['only_land']) // выводим только с место жительством
				$where[] = "u.land != ''";
			
			if($this->comm_cfg['news_user']) // выводим только если новостей больше чем
				$where[] = "u.news_num > {$this->comm_cfg[news_user]}";
			
			if($this->comm_cfg['comm']) // выводим только если комментариев больше чем
				$where[] = "u.comm_num > {$this->comm_cfg[comm]}";
			
			// префикс кэша
			$Comm_hash = 	$this->comm_cfg['max_comm'] . 
							$this->comm_cfg['max_text'] . 
							$this->comm_cfg['max_title'] . 
							$this->comm_cfg['check_guest'] . 
							$this->comm_cfg['stop_category'] . 
							$this->comm_cfg['from_category'] . 
							$this->comm_cfg['stop_id'] . 
							$this->comm_cfg['from_id'] . 
							$this->comm_cfg['only_avatar'] . 
							$this->comm_cfg['only_news'] . 
							$this->comm_cfg['news_user'] . 
							$this->comm_cfg['comm'] . 
							$this->comm_cfg['only_fav'] . 
							$this->comm_cfg['only_fullname'] . 
							$this->comm_cfg['only_land'];

			$is_change = false;

			if (!$allow_cache) // если кэш не включен включаем принудительно
			{
				if ($this->config['version_id'] >= '10.2')
					$this->config['allow_cache'] = '1';
				else 
					$this->config['allow_cache'] = "yes";
				$is_change = true;
			}

			$Comm = dle_cache( "Comm_" . $Comm_hash, $this->config['skin'], true); // подгружаем из кэша
			if (!$Comm) // если кэша небыло или другая проблема
			{
				if(count($where) > 0)
					$where = " AND " . implode(" AND ", $where);
				else
					$where = "";
				
				$sql = $this->db->query("SELECT c.id as comid, c.post_id, c.date, c.user_id, c.is_register, c.text, c.autor, c.email, c.approve, p.id, p.date as newsdate, p.title, p.category, p.comm_num, p.alt_name, e.news_id, e.news_read, e.rating, u.foto, u.user_group, u.user_id FROM " . PREFIX . "_comments as c, " . PREFIX . "_post as p, " . PREFIX . "_post_extras as e, " . PREFIX . "_users as u WHERE p.id=c.post_id AND e.news_id=c.post_id AND c.approve = 1 AND c.user_id = u.user_id {$where} ORDER BY c.date DESC LIMIT 0, " . $this->comm_cfg['max_comm']);
				
				$tpl = new dle_template();
				$tpl->dir = TEMPLATE_DIR;
				$tpl->load_template('comm/comm.tpl');
				
				$count_rows = $sql->num_rows;
				if($count_rows > 0) 
				{
					while ($row = $this->db->get_row($sql)) 
					{
						$row['date'] = strtotime($row['date']);
						$row['category'] = intval($row['category']);
						// Обработка ссылки на комментарий
						$on_page = FALSE;
						if ($row['comm_num'] > $this->config['comm_nummers'])
							$on_page = 'page,1,' . ceil($row['comm_num'] / $this->config['comm_nummers']) . ',';
						
						if ($allow_alt_url)
						{
							if ($condition = $this->config['seo_type'] == 1 OR $this->config['seo_type'] == 2)
							{
								if ($row['category'] and $this->config['seo_type'] == 2)
									$full_link = $this->config['http_home_url'] . get_url($row['category']) . "/" . $on_page . $row['id'] . "-" . $row['alt_name'] . ".html";
								else
									$full_link = $this->config['http_home_url'] . $on_page . $row['id'] . "-" . $row['alt_name'] . ".html";
							}
							else
								$full_link = $this->config['http_home_url'] . date('Y/m/d/', $row['date']) . $on_page . $row['alt_name'] . ".html";
						}
						else
							$full_link = $this->config['http_home_url'] . "index.php?newsid=" . $row['id'];

						$full_link = $full_link . '#comment-id-' . $row['comid'];
						
						// Обработка текста комментария
						if (dle_strlen($row['text'], $this->config['charset']) > $this->comm_cfg['max_text'])
							$text = stripslashes(dle_substr($row['text'], 0, $this->comm_cfg['max_text'], $this->config['charset']) . " ...");
						else
							$text = stripslashes($row['text']);
						
						// Обработка заголовка новости (title)
						if (dle_strlen($row['title'], $this->config['charset']) > $this->comm_cfg['max_title'])
							$title = stripslashes(dle_substr($row['title'], 0, $this->comm_cfg['max_title'], $this->config['charset']) . " ...");
						else
							$title = stripslashes($row['title']);
						
						// Обработка ника автора комментария
						if ($row['is_register'] == 1)
						{
							if ($allow_alt_url)
								$go_page = $this->config['http_home_url'] . "user/" . urlencode($row['autor']) . "/";
							else
								$go_page = "$PHP_SELF?subaction=userinfo&amp;user=" . urlencode($row['autor']);
							
							if ($this->config['version_id'] >= '10.2')
								$go_page = "onclick=\"ShowProfile('" . urlencode( $row['autor'] ) . "', '" . htmlspecialchars( $go_page, ENT_QUOTES, $this->config['charset'] ) . "', '" . $this->group[$this->member['user_group']]['admin_editusers'] . "'); return false;\"";
							else 
								$go_page = "onclick=\"ShowProfile('" . urlencode($row['autor']) . "', '" . $go_page . "'); return false;\"";
							
							if ($allow_alt_url)
								$author = "<a {$go_page} href=\"" . $this->config['http_home_url'] . "user/" . urlencode( $row['autor'] ) . "/\">" . $row['autor'] . "</a>";
							else
								$author = "<a {$go_page} href=\"$PHP_SELF?subaction=userinfo&amp;user=" . urlencode( $row['autor'] ) . "\">" . $row['autor'] . "</a>";
						}
						else
							$author = strip_tags($row['autor']);
						
						// Обработка фото автора комментария
						if($row['foto'] AND $row['is_register'] == 1) 
						{
							if ( count(explode("@", $row['foto'])) == 2 )
								$tpl->set( '{foto}', '//www.gravatar.com/avatar/' . md5(trim($row['foto'])) . '?s=' . intval($this->group[$row['user_group']]['max_foto']) );
							else 
							{
								if($this->config['version_id'] >= '10.5') 
								{								
									if (strpos($row['foto'], "//") === 0) $avatar = "http:".$row['foto']; else $avatar = $row['foto'];
									$avatar = @parse_url ( $avatar );
									if( $avatar['host'] )
										$tpl->set( '{foto}', $row['foto'] );
									else
										$tpl->set( '{foto}', $this->config['http_home_url'] . "uploads/fotos/" . $row['foto'] );
								} 
								else
									if( $row['foto'] and (file_exists( ROOT_DIR . "/uploads/fotos/" . $row['foto'] )) ) $tpl->set( '{foto}', $this->config['http_home_url'] . "uploads/fotos/" . $row['foto'] );
							}
						}
						else
							$tpl->set( '{foto}', "{THEME}/dleimages/noavatar.png" );

						// Обработка ссылки автора комментария
						if ($allow_alt_url)
							$user_url = $this->config['http_home_url'] . "user/" . urlencode($row['autor']) . "/";
						else
							$user_url = "$PHP_SELF?subaction=userinfo&amp;user=" . urlencode($row['autor']);
						
						if ($row['is_register'] != 1)
							$user_url = 'mailto:' . $row['email'];
						
						// Обработка даты комментария
						if (date('Ymd', $row['date']) == date('Ymd', $_TIME))
						{
							$tpl->set('{date}', $lang['time_heute'] . langdate(", H:i", $row['date']));
						}
						elseif (date('Ymd', $row['date']) == date('Ymd', ($_TIME - 86400)))
						{
							$tpl->set('{date}', $lang['time_gestern'] . langdate(", H:i", $row['date']));
						}
						else
							$tpl->set('{date}', langdate($this->config['timestamp_active'], $row['date']));
						
						$tpl->copy_template = preg_replace("#\{date=(.+?)\}#ie", "langdate('\\1', '{$row['date']}')", $tpl->copy_template);
						
						$tpl->set('{text}', $text); //текст комментария
						
						$tpl->set('{user_url}', $user_url); // ссылка на автора
						$tpl->set('{user_name}', $row['autor']); // просто ник автора
						$tpl->set('[user_url]', "<a href=\"" . $user_url . "\">"); // оборачиваем в ссылку
						$tpl->set('[/user_url]', "</a>"); // оборачиваем в ссылку
						$tpl->set('{author}', $author); // автор с ссылкой на профиль с модальным окном
						$tpl->set('[color]', $this->group[$row['user_group']]['group_prefix']); // префикс цвета группы
						$tpl->set('[/color]', $this->group[$row['user_group']]['group_suffix']); // суфикс цвета группы
						
						$tpl->set('{title}', $title); // укороченный заголовок
						$tpl->set('{long_title}', stripslashes($row['title'])); // полный заголовок
						
						$tpl->set('{rating}', $row['rating']); // рейтинг новости
						$tpl->set('{views}', $row['news_read']); // просмотров новости
						$tpl->set('{full_link}', $full_link); // линк на комментарий
						$tpl->set('{comm_num}', $row['comm_num']); // кол-во комментариев новости
						
						$tpl->set("{error}", "");
						$tpl->set( '[comm]', "" );
						$tpl->set( '[/comm]', "" );
						$tpl->set_block( "'\\[not-comm\\](.*?)\\[/not-comm\\]'si", "" );
						
						$tpl->compile('comm'); //компиляция шаблона
					}		
					$this->db->free($sql); //очищаем от запросов
				}
				else
				{
					$tpl->set("{error}", "Комментариев нету!");
					$tpl->set_block( "'\\[comm\\](.*?)\\[/comm\\]'si", "" );
					$tpl->set( '[not-comm]', "" );
					$tpl->set( '[/not-comm]', "" );
					$tpl->compile('comm');	
				}

				$tpl->clear(); //очищаем шаблон
				$Comm = $tpl->result['comm'];
				
				if (preg_match_all('/<!--dle_spoiler(.*?)<!--\/dle_spoiler-->/is', $Comm, $spoilers))
				{
					foreach ($spoilers as $spoiler)
					{
						$Comm = str_replace($spoiler, '<div class="quote">Для просмотра содержимого спойлера, перейдите к выбранному комментарию.</div>', $Comm);
					}
				}
				
				create_cache( "Comm_" . $Comm_hash, $Comm, $this->config['skin'], true ); //создаем кэш
				
				if ($this->group[$this->member['user_group']]['allow_hide'])
					$Comm = preg_replace("'\[hide\](.*?)\[/hide\]'si", "\\1", $Comm);
				else
					$Comm = preg_replace("'\[hide\](.*?)\[/hide\]'si", "<div class=\"quote\"> Для вашей группы скрытый текст не виден </div>", $Comm);
				
				if ($is_change)
					$this->config['allow_cache'] = false; //выключаем кэш принудительно (возвращаем назад)
			}
				echo '<div class="iComm" id="iComm"><ul class="lastcomm">' .$Comm. '</ul> <!-- .lastcomm --></div>';
		}
		
		private function Explode_Category($category, $type) //работа с категориями
		{
			$temp_array = array();
			$category = explode (',', $category);
			
			foreach ($category as $value) {
				if( count(explode('-', $value)) == 2 ) $temp_array[] = get_mass_cats($value);
				else $temp_array[] = intval($value);
			}
			if($type == "multi")
				$temp_array = implode("|", $temp_array);
			else
				$temp_array = implode(",", $temp_array);
			
			return $temp_array;
			unset($temp_array);
		}
		
		private function Explode_NewsID($id) //работа с новостями
		{
			$temp_array = array();
			$where_id = array();
			
			$id = explode (',', trim($id));

			foreach ($id as $value) {
				if( count(explode('-', $value)) == 2 ) {
					$value = explode('-', $value);
					$where_id[] = "id >= '" . intval($value[0]) . "' AND id <= '".intval($value[1])."'";
				} else $temp_array[] = intval($value);
			}

			if ( count($temp_array) )
				$where_id[] = "id IN ('" . implode("','", $temp_array) . "')";

			if ( count($where_id) ) { 
				$custom_id = implode(' OR ', $where_id);
				$id_news = $custom_id;
			}
			return $id_news;
			unset($temp_array);
			unset($where_id);
		}
		
		private function Explode_xField($xf, $type) //работа с дополнительными полями
		{
			$array = array();
			$xf_arr = array();
			foreach(explode('^', $string) as $value)
				$array[] = explode('|', $value);
				
			for ($i = 0; $i < count($array); $i++)
				$xf_arr[] = "SUBSTRING_INDEX( SUBSTRING_INDEX( {$type},  '{$array[$i][0]}|', -1 ) ,  '||', 1 ) LIKE '%{$array[$i][1]}%'";

			$xf_arr = $type . '!= "" AND ' . implode(' AND ', $xf_arr);

			return $xf_arr;
			unset($array);
			unset($xf_arr);
		}
	}
}

$CommCfg = array(
	'max_comm' => !empty($max_comm) ? $max_comm : 10, // максимальное кол-во выводимых комментариев
	'max_text' => !empty($max_text) ? $max_text : 500, // максимальное кол-во символов при выводе комментария
	'max_title' => !empty($max_title) ? $max_title : 25, // максимальное кол-во символов при выводе заголовка новости
	'stop_category' => !empty($stop_category) ? $stop_category : false, // из каких категорий не выводить
	'from_category' => !empty($from_category) ? $from_category : false, // из каких категорий выводить
	'stop_id' => !empty($stop_id) ? $stop_id : false, // исключаем комментарии по id новостей
	'from_id' => !empty($from_id) ? $from_id : false, // выводить комментарии только из этих новостей
	'only_avatar' => !empty($avatar) ? $avatar : false, // выводить только комментарии авторов которые имеют загруженный аватар
	'only_news' => !empty($news) ? $news : false, // выводить только комментарии авторов которые имеют новости
	'news_user' => !empty($news_user) ? $news_user : false, // выводить комментарии авторов которые имеют кол-во новостей больше чем
	'comm' => !empty($comm) ? $comm : false, // выводить комментарии авторов которые имеют кол-во комментариев больше чем
	'ncomm' => !empty($ncomm) ? $ncomm : false, // выводить комментарии только из тех новостей которые имеют кол-во комментариев больше чем
	'fixed' => !empty($fixed) ? $fixed : false, // выводить только комментарии из тех новостей которые зафиксированы
	'tags' => !empty($tags) ? $tags : false, // выводить только комментарии из тех новостей которые имеют теги
	'news_read' => !empty($read) ? $read : false, // выводить только комментарии из тех новостей которые имеют просмотров больше чем
	'rating_news' => !empty($nrating) ? $nrating : false, // выводить только комментарии из тех новостей которые имеют рейтинг больше чем
	'only_fav' => !empty($fav) ? $fav : false, // выводить только комментарии авторов которые имеют закладки
	'only_fullname' => !empty($fullname) ? $fullname : false, // выводить только комментарии авторов которые заполнили полное имя
	'only_land' => !empty($land) ? $land : false, // выводить только комментарии авторов которые заполнили место жительства
	'rating_comm' => !empty($rating) ? $rating : false, // выводить только комментарии у которых рейтинг больше чем
	'news_xfield' => !empty($nxf) ? $nxf : false, // взаемодействие с дополнительными полями новостей
	'user_xfield' => !empty($uxf) ? $uxf : false, // взаемодействие с дополнительными полями пользователей
);

$ShowComments = new Comments;
$ShowComments->Start($CommCfg);
?>