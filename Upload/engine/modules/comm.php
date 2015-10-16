<?php
/*
=============================================================================
ShowComments - Модуль вывода последних комментариев
=============================================================================
Автор модуля: Gameer
-----------------------------------------------------
URL: http://igameer.ru/
-----------------------------------------------------
email: gameer@mail.ua
-----------------------------------------------------
skype: gameerblog
=============================================================================
Файл:  comm.php
=============================================================================
Версия модуля : 2.0 Stable Release
=============================================================================
/*
 * Что может:
 * - Выводить последние комментарии в любом месте любого tpl файла
 *
 * Установка:
 * - Читать в файле install.html
 *
 * Официальная страница модуля : http://igameer.ru/port/49-showcomments.html
*/


if( !defined( 'DATALIFEENGINE' ) ) die( "You are a fucking faggot!" );

if(!class_exists('Comments')) 
{
	class Comments 
	{
		private $config; 	// Конфиг DLE
		private $comm_cfg;	// Конфиг модуля
		private $db; 		// База данных
		private $member;	// Инфа о юзере
		private $group;		// Инфа о группах
		private $temp;		// Шаблон
		private $thisdate;	// Время
		
		public function __construct()
		{
			global $db, $config, $member_id, $user_group, $tpl, $_TIME; // Глобальные перенные
			$this->config = &$config;
			$this->db = $db;
			$this->member = $member_id;
			$this->group = $user_group;
			$this->temp = $tpl;
			$this->thisdate = date( "Y-m-d H:i:s", $_TIME );
		}
		
		public function Start($CommCfg)
		{
			$this->comm_cfg = $CommCfg;
			
			$where = array();
			
			// проверка некоторых параметров конфига по версиях
			$allow_alt_url = ($this->config['version_id'] >= '10.2') ? $this->config['allow_alt_url'] == '1' : $this->config['allow_alt_url'] == "yes";
			$allow_cache = ($this->config['version_id'] >= '10.2') ? $this->config['allow_cache'] == '1' : $this->config['allow_cache'] == "yes";
			$allow_multi_category = ($this->config['version_id'] >= '10.2') ? $this->config['allow_multi_category'] == '1' : $this->config['allow_multi_category'] == "yes";
			
			if ($this->config['version_id'] >= '10.4' AND $this->comm_cfg['rating_comm']) // рейтинг комментариев только для DLE 10.4 и выще
				$where[] = "c.rating > {$this->comm_cfg[rating_comm]}";
			
			if( $allow_multi_category ) // работа с категориями
			{
				if($this->comm_cfg['stop_cat'])
					$where[] = "category NOT REGEXP '[[:<:]](" . $this->Explode_Category($this->comm_cfg['stop_cat'], true) . ")[[:>:]]'";
				if($this->comm_cfg['from_cat'])
					$where[] = "category REGEXP '[[:<:]](" .  $this->Explode_Category($this->comm_cfg['from_cat'], true) . ")[[:>:]]'";
			}
			else
			{
				if($this->comm_cfg['stop_cat'])
					$where[] = "category NOT IN ('" . $this->Explode_Category($this->comm_cfg['stop_cat'], false) . "')";
				if($this->comm_cfg['from_cat'])
					$where[] = "category IN ('" . $this->Explode_Category($this->comm_cfg['from_cat'], false) . "')";
			}
			
			if($this->comm_cfg['date_news']) // работа с комментариями по дате новостей
			{
				if(count(explode('/', $this->comm_cfg['date_news'])) == 2)
				{
					$date_bet = explode('/', $this->comm_cfg['date_news']);
					$date_first = date('Y-m-d', $date_bet[0]); $date_second = date('Y-m-d', $date_bet[1]);
					$where[] = "p.date BETWEEN '$date_first%' AND '$date_second%'";
				}
			}
			
			if($this->comm_cfg['date_comm']) // работа с комментариями по дате комментариев
			{
				if(count(explode('/', $this->comm_cfg['date_comm'])) == 2)
				{
					$date_bet = explode('/', $this->comm_cfg['date_comm']);
					$date_first = date('Y-m-d', $date_bet[0]); $date_second = date('Y-m-d', $date_bet[1]);
					$where[] = "c.date BETWEEN '$date_first%' AND '$date_second%'";
				}
			}
			
			if($this->comm_cfg['day_news']) // работа с комментариями по днях новостей
				$where[] = "p.date >= '{$this->thisdate}' - INTERVAL {$this->comm_cfg[day_news]} DAY AND p.date < '{$this->thisdate}'";
			
			if($this->comm_cfg['day_comm']) // работа с комментариями по днях комментариев
				$where[] = "c.date >= '{$this->thisdate}' - INTERVAL {$this->comm_cfg[day_comm]} DAY AND c.date < '{$this->thisdate}'";

			if($this->comm_cfg['news_xfield']) // работа с доп полями новостей
				$where[] = $this->Explode_xField($this->comm_cfg['news_xfield'], "p.xfields");
				
			if($this->comm_cfg['user_xfield']) // работа с доп полями пользователей
				$where[] = $this->Explode_xField($this->comm_cfg['user_xfield'], "u.xfields");
			
			if($this->comm_cfg['user']) // работа с пользователями
				$where[] = $this->Explode_User($this->comm_cfg['user'], true);
			if($this->comm_cfg['not_user'])
				$where[] = $this->Explode_User($this->comm_cfg['not_user'], false);
			
			// работа с новостями
			if($this->comm_cfg['stop_id'])
				$where[] = $this->Explode_NewsID($this->comm_cfg['stop_id'], false);
			if($this->comm_cfg['from_id'])
				$where[] = $this->Explode_NewsID($this->comm_cfg['from_id'], true);
			
			if($this->comm_cfg['ncomm']) // выводим только с комментариями у новостей больше чем
				$where[] = "p.comm_num > {$this->comm_cfg[ncomm]}";
			
			if($this->comm_cfg['fixed']) // выводим только с зафиксированых новостей
				$where[] = "p.fixed = 1";
			
			if($this->comm_cfg['tags']) // выводим только с с тех новостей которые имеют теги
			{ 
				$t = str_ireplace(',', '|', $this->comm_cfg['tags']);
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
			
			$Comm_hash = md5(implode(',',$where)); // префикс кэша

			$is_change = false;

			if (!$allow_cache) // если кэш не включен включаем принудительно
			{
				if ($this->config['version_id'] >= '10.2')
					$this->config['allow_cache'] = '1';
				else 
					$this->config['allow_cache'] = "yes";
				$is_change = true;
			}

			$Comm = dle_cache( "news_Comm_", $this->config['skin'] . $Comm_hash); // подгружаем из кэша
			if (!$Comm) // если кэша небыло или другая проблема
			{
				if(count($where) > 0)
					$where = " AND " . implode(" AND ", $where);
				else
					$where = "";
				
				$sql = $this->db->query("SELECT c.id as comid, c.post_id, c.date as commdate, c.user_id, c.is_register, c.text, c.autor, c.email, c.approve, p.id, p.date as newsdate, p.xfields as news_xf, p.title, p.category, p.comm_num, p.alt_name, e.news_id, e.news_read, e.rating, e.allow_rate, e.vote_num, u.foto, u.user_group, u.user_id, u.xfields as user_xf FROM " . PREFIX . "_comments as c, " . PREFIX . "_post as p, " . PREFIX . "_post_extras as e, " . PREFIX . "_users as u WHERE p.id=c.post_id AND e.news_id=c.post_id AND c.approve = 1 AND c.user_id = u.user_id {$where} ORDER BY c.date DESC LIMIT 0, " . $this->comm_cfg['max_comm']);
				
				$this->temp->dir = TEMPLATE_DIR;
				if($this->comm_cfg['temp'])
					$this->temp->load_template('comm/'.$this->comm_cfg['temp'].'.tpl');
				else
					$this->temp->load_template('comm/comm.tpl');
				
				$count_rows = $sql->num_rows;
				if($count_rows > 0) 
				{
					while ($row = $this->db->get_row($sql)) 
					{
						$row['newsdate'] = strtotime($row['newsdate']);
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
								$full_link = $this->config['http_home_url'] . date('Y/m/d/', $row['newsdate']) . $on_page . $row['alt_name'] . ".html";
						}
						else
							$full_link = $this->config['http_home_url'] . "index.php?newsid=" . $row['id'];

						$full_link = $full_link . '#comment-id-' . $row['comid'];

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
						
						IF( strpos( $this->temp->copy_template, "[xfvalue_" ) !== false OR strpos( $this->temp->copy_template, "[xfgiven_" ) !== false )
						{
							$xf_news = $this->Field_Exp($row['news_xf']);	
							FOREACH($xf_news AS $name => $val)
							{
								IF(empty( $xf_news[$name] ))
								{
									$this->temp->copy_template = preg_replace( "'\\[xfgiven_{$name}\\](.*?)\\[/xfgiven_{$name}\\]'is", 			"", 	$this->temp->copy_template);
									$this->temp->copy_template = str_replace("[xfvalue_{$name}]",												'',		$this->temp->copy_template);
									$this->temp->copy_template = preg_replace( "'\\[xfnotgiven_{$name}\\](.*?)\\[/xfnotgiven_{$name}\\]'si",	"\\1",	$this->temp->copy_template);
								}
								ELSE
								{
									$this->temp->copy_template = preg_replace( "'\\[xfnotgiven_{$name}\\](.*?)\\[/xfnotgiven_{$name}\\]'is", 	"", 	$this->temp->copy_template);
									$this->temp->copy_template = str_replace("[xfvalue_{$name}]",												$val,	$this->temp->copy_template);
									$this->temp->copy_template = preg_replace( "'\\[xfgiven_{$name}\\](.*?)\\[/xfgiven_{$name}\\]'si", 			"\\1",	$this->temp->copy_template);
								}
							}
						}
						$this->temp->copy_template = preg_replace( "'\\[xfgiven_(.*?)\\](.*?)\\[/xfgiven_(.*?)\\]'is", 			"", 	$this->temp->copy_template);
						$this->temp->copy_template = str_replace("[xfvalue_(.*?)]",												'',		$this->temp->copy_template);
						$this->temp->copy_template = preg_replace( "'\\[xfnotgiven_(.*?)\\](.*?)\\[/xfnotgiven_(.*?)\\]'is", 	"", 	$this->temp->copy_template);
						
						IF( strpos( $this->temp->copy_template, "[user_xf_" ) !== false OR strpos( $this->temp->copy_template, "[user_xg_" ) !== false )
						{
							$xf_user = $this->Field_Exp($row['user_xf']);
							FOREACH($xf_user AS $name => $val)
							{
								IF(empty( $xf_user[$name] ))
								{
									$this->temp->copy_template = preg_replace( "'\\[user_xg_{$name}\\](.*?)\\[/user_xg_{$name}\\]'is", 			"", 	$this->temp->copy_template);
									$this->temp->copy_template = str_replace("[user_xf_{$name}]",												'',		$this->temp->copy_template);
									$this->temp->copy_template = preg_replace( "'\\[user_nxg_{$name}\\](.*?)\\[/user_nxg_{$name}\\]'si",		"\\1",	$this->temp->copy_template);
								}
								ELSE
								{
									$this->temp->copy_template = preg_replace( "'\\[user_nxg_{$name}\\](.*?)\\[/user_nxg_{$name}\\]'is", 	"", 	$this->temp->copy_template);
									$this->temp->copy_template = str_replace("[user_xf_{$name}]",											$val,	$this->temp->copy_template);
									$this->temp->copy_template = preg_replace( "'\\[user_xg_{$name}\\](.*?)\\[/user_xg_{$name}\\]'si", 		"\\1",	$this->temp->copy_template);
								}
							}
						}
						$this->temp->copy_template = preg_replace( "'\\[user_xg_(.*?)\\](.*?)\\[/user_xg_(.*?)\\]'is", 			"", 	$this->temp->copy_template);
						$this->temp->copy_template = str_replace("[user_xf_(.*?)]",												'',		$this->temp->copy_template);
						$this->temp->copy_template = preg_replace( "'\\[user_nxg_(.*?)\\](.*?)\\[/user_nxg_(.*?)\\]'is", 		"", 	$this->temp->copy_template);
						
						if($row['allow_rate'])
						{
							if($this->config['version_id'] >= '10.4')
							{
								if ( $this->config['rating_type'] == "1" )
								{
									$this->temp->set( '[rating-type-2]', "" );
									$this->temp->set( '[/rating-type-2]', "" );
									$this->temp->set_block( "'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si", "" );
									$this->temp->set_block( "'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si", "" );
								}
								elseif ( $this->config['rating_type'] == "2" )
								{
									$this->temp->set( '[rating-type-3]', "" );
									$this->temp->set( '[/rating-type-3]', "" );
									$this->temp->set_block( "'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si", "" );
									$this->temp->set_block( "'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si", "" );
								}
								else
								{
									$this->temp->set( '[rating-type-1]', "" );
									$this->temp->set( '[/rating-type-1]', "" );
									$this->temp->set_block( "'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si", "" );
									$this->temp->set_block( "'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si", "" );	
								}
							}
							$this->temp->set( '[rating]', "" );
							$this->temp->set( '[/rating]', "" );
						}
						else
						{
							$this->temp->set_block( "'\\[rating\\](.*?)\\[/rating\\]'si", "" );
							$this->temp->set_block( "'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si", "" );
							$this->temp->set_block( "'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si", "" );
							$this->temp->set_block( "'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si", "" );
						}
						// Обработка фото автора комментария
						if($row['foto'] AND $row['is_register'] == 1) 
						{
							if ( count(explode("@", $row['foto'])) == 2 )
								$this->temp->set( '{foto}', '//www.gravatar.com/avatar/' . md5(trim($row['foto'])) . '?s=' . intval($this->group[$row['user_group']]['max_foto']) );
							else 
							{
								if($this->config['version_id'] >= '10.5') 
								{								
									if (strpos($row['foto'], "//") === 0) $avatar = "http:".$row['foto']; else $avatar = $row['foto'];
									$avatar = @parse_url ( $avatar );
									if( $avatar['host'] )
										$this->temp->set( '{foto}', $row['foto'] );
									else
										$this->temp->set( '{foto}', $this->config['http_home_url'] . "uploads/fotos/" . $row['foto'] );
								} 
								else
									if( $row['foto'] and (file_exists( ROOT_DIR . "/uploads/fotos/" . $row['foto'] )) ) $this->temp->set( '{foto}', $this->config['http_home_url'] . "uploads/fotos/" . $row['foto'] );
							}
						}
						else
							$this->temp->set( '{foto}', "{THEME}/dleimages/noavatar.png" );

						// Обработка ссылки автора комментария
						if ($allow_alt_url)
							$user_url = $this->config['http_home_url'] . "user/" . urlencode($row['autor']) . "/";
						else
							$user_url = "$PHP_SELF?subaction=userinfo&amp;user=" . urlencode($row['autor']);
						
						if ($row['is_register'] != 1)
							$user_url = 'mailto:' . $row['email'];
						
						// Обработка даты комментария
						$this->temp->copy_template = preg_replace_callback("#\{date=(.+?)\}#i", "formdate", $this->temp->copy_template); // формирование даты
						
						$this->temp->set('{text}', stripslashes($row['text'])); //текст комментария
						if ( preg_match( "#\\{text limit=['\"](.+?)['\"]\\}#i", $this->temp->copy_template, $matches ) ) // текст комментария с лимитом
						{
							$count= intval($matches[1]);
							$row['text'] = strip_tags( $row['text'] );
							if( $count AND dle_strlen( $row['text'], $this->config['charset'] ) > $count )
							{
								$row['text'] = dle_substr( $row['text'], 0, $count, $this->config['charset'] );
								if( ($temp_dmax = dle_strrpos( $row['text'], ' ', $this->config['charset'] )) ) $row['text'] = dle_substr( $row['text'], 0, $temp_dmax, $this->config['charset'] );
							}
							$this->temp->set( $matches[0], $row['text'] );
						}
						$this->temp->set('{user_url}', $user_url); // ссылка на автора
						$this->temp->set('{user_name}', $row['autor']); // просто ник автора
						$this->temp->set('[user_url]', "<a href=\"" . $user_url . "\">"); // оборачиваем в ссылку
						$this->temp->set('[/user_url]', "</a>"); // оборачиваем в ссылку
						$this->temp->set('{author}', $author); // автор с ссылкой на профиль с модальным окном
						$this->temp->set('[color]', $this->group[$row['user_group']]['group_prefix']); // префикс цвета группы
						$this->temp->set('[/color]', $this->group[$row['user_group']]['group_suffix']); // суфикс цвета группы
						
						$this->temp->set('{title}', stripslashes($row['title'])); // полный заголовок
						if ( preg_match( "#\\{title limit=['\"](.+?)['\"]\\}#i", $this->temp->copy_template, $matches ) ) // тайтл с лимитом
						{
							$count= intval($matches[1]);
							$row['title'] = strip_tags( $row['title'] );
							if( $count AND dle_strlen( $row['title'], $this->config['charset'] ) > $count )
							{
								$row['title'] = dle_substr( $row['title'], 0, $count, $this->config['charset'] );
								if( ($temp_dmax = dle_strrpos( $row['title'], ' ', $this->config['charset'] )) ) $row['title'] = dle_substr( $row['title'], 0, $temp_dmax, $this->config['charset'] );
							}
							$this->temp->set( $matches[0], $row['title'] );
						}
						
						$this->temp->set('{rating}', $row['rating']); // рейтинг новости
						$this->temp->set('{views}', $row['news_read']); // просмотров новости
						$this->temp->set('{full_link}', $full_link); // линк на комментарий
						$this->temp->set('{comm_num}', $row['comm_num']); // кол-во комментариев новости
						
						$this->temp->set("{error}", "");
						$this->temp->set( '[comm]', "" );
						$this->temp->set( '[/comm]', "" );
						$this->temp->set_block( "'\\[not-comm\\](.*?)\\[/not-comm\\]'si", "" );
						
						$this->temp->compile('comm'); //компиляция шаблона
					}		
					$this->db->free($sql); //очищаем от запросов
				}
				else
				{
					$this->temp->set("{error}", "Комментариев нету!");
					$this->temp->set_block( "'\\[comm\\](.*?)\\[/comm\\]'si", "" );
					$this->temp->set( '[not-comm]', "" );
					$this->temp->set( '[/not-comm]', "" );
					$this->temp->compile('comm');	
				}

				$this->temp->clear(); //очищаем шаблон
				$Comm = $this->temp->result['comm'];
				
				if (preg_match_all('/<!--dle_spoiler(.*?)<!--\/dle_spoiler-->/is', $Comm, $spoilers))
				{
					foreach ($spoilers as $spoiler)
					{
						$Comm = str_replace($spoiler, '<div class="quote">Для просмотра содержимого спойлера, перейдите к выбранному комментарию.</div>', $Comm);
					}
				}
				
				if ($this->group[$this->member['user_group']]['allow_hide'])
					$Comm = preg_replace("'\[hide\](.*?)\[/hide\]'si", "\\1", $Comm);
				else
					$Comm = preg_replace("'\[hide\](.*?)\[/hide\]'si", "<div class=\"quote\"> Для вашей группы скрытый текст не виден </div>", $Comm);
				
				$Comm = preg_replace( "#<!--dle_uppod_begin:(.+?)-->(.+?)<!--dle_uppod_end-->#is", '[uppod=\\1]', $Comm );
				$Comm = preg_replace( "#\[uppod=([^\]]+)\]#ies", "build_uppod('\\1')", $Comm );
				
				create_cache("news_Comm_", $Comm, $this->config['skin'] . $Comm_hash); //создаем кэш
				
				if ($is_change)
					$this->config['allow_cache'] = false; //выключаем кэш принудительно (возвращаем назад)
			}
				echo '<div class="iComm" id="iComm"><ul class="lastcomm">' .$Comm. '</ul> <!-- .lastcomm --></div>';
		}
		
		PRIVATE FUNCTION Explode_Category($category, $type) //работа с категориями
		{
			$temp_array = array();
			$category = explode (',', $category);
			
			foreach ($category as $value) {
				if( count(explode('-', $value)) == 2 ) $temp_array[] = get_mass_cats($value);
				else $temp_array[] = intval($value);
			}
			
			if($type)
				$temp_array = implode("|", $temp_array);
			else
				$temp_array = implode("','", $temp_array);
			
			return $temp_array;
			unset($temp_array);
		}
		
		PRIVATE FUNCTION Explode_NewsID($id , $type) //работа с новостями
		{
			$temp_array = array();
			$where_id = array();
			
			$id = explode (',', trim($id));

			foreach ($id as $value) {
				if( count(explode('-', $value)) == 2 ) {
					$value = explode('-', $value);
					if($type)
						$where_id[] = "p.id >= '" . intval($value[0]) . "' AND p.id <= '".intval($value[1])."'";
					else
						$where_id[] = "(p.id < '" . intval($value[0]) . "' OR p.id > '".intval($value[1])."')";
				} else $temp_array[] = intval($value);
			}

			if ( count($temp_array) )
			{	
				if($type)
					$where_id[] = "p.id IN ('" . implode("','", $temp_array) . "')";
				else
					$where_id[] = "p.id NOT IN ('" . implode("','", $temp_array) . "')";
			}
			
			if ( count($where_id) ) {
				if($type)
					$custom_id = implode(' OR ', $where_id);
				else
					$custom_id = implode(' AND ', $where_id);
				$id_news = $custom_id;
			}	
			unset($temp_array);
			unset($where_id);
			return $id_news;
		}
		
		PRIVATE FUNCTION Explode_User($user, $type) //работа с пользователями
		{
			if(!$type)
				$user = "c.autor NOT IN ('" . $user . "')";
			else
				$user = "c.autor IN ('" . $user . "')";
			
			return $user;
		}
		
		PRIVATE FUNCTION Explode_xField($xf, $type) //работа с дополнительными полями
		{
			$array = array();
			$xf_arr = array();
			foreach(explode('^', $string) as $value)
				$array[] = explode('|', $value);
				
			for ($i = 0; $i < count($array); $i++)
				$xf_arr[] = "SUBSTRING_INDEX( SUBSTRING_INDEX( {$type},  '{$array[$i][0]}|', -1 ) ,  '||', 1 ) LIKE '%{$array[$i][1]}%'";

			$xf_arr = $type . '!= "" AND ' . implode(' AND ', $xf_arr);
			
			unset($array);
			return $xf_arr;
		}
		
		PRIVATE FUNCTION Field_Exp($xfields) {
			if( $xfields == "" ) return;
			$xfieldsdata = explode( "||", $xfields );
			$xf_arr = array();
			foreach ( $xfieldsdata as $xfielddata ) {
				list ( $xfielddataname, $xfielddatavalue ) = explode( "|", $xfielddata );
				$xfielddataname = str_replace( "&#124;", "|", $xfielddataname );
				$xfielddataname = str_replace( "__NEWL__", "\r\n", $xfielddataname );
				$xfielddatavalue = str_replace( "&#124;", "|", $xfielddatavalue );
				$xfielddatavalue = str_replace( "__NEWL__", "\r\n", $xfielddatavalue );
				$xf_arr[$xfielddataname] = $xfielddatavalue;
			}
			return $xf_arr;
		}
	}
}

$CommCfg = array(
	'max_comm' => is_numeric($max_comm) ? intval($max_comm) : 10, 					// максимальное кол-во выводимых комментариев
	'stop_cat' => !empty($stop_cat) ? strip_tags(stripslashes($stop_cat)) : false, 	// из каких категорий не выводить
	'from_cat' => !empty($from_cat) ? strip_tags(stripslashes($from_cat)) : false, 	// из каких категорий выводить
	'stop_id' => !empty($stop_id) ? strip_tags(stripslashes($stop_id)) : false, 	// исключаем комментарии по id новостей
	'from_id' => !empty($from_id) ? strip_tags(stripslashes($from_id)) : false, 	// выводить комментарии только из этих новостей
	'only_avatar' => is_numeric($avatar) ? intval($avatar) : false, 				// выводить комментарии авторов которые имеют аватар
	'only_news' => is_numeric($news) ? intval($news) : false, 						// выводить только комментарии авторов которые имеют новости
	'news_user' => is_numeric($news_user) ? intval($news_user) : false, 			// выводить комментарии авторов которые имеют кол-во новостей больше чем
	'comm' => is_numeric($comm) ? intval($comm) : false, 							// выводить комментарии авторов которые имеют кол-во комментариев больше чем
	'ncomm' => is_numeric($ncomm) ? intval($ncomm) : false, 						// выводить комментарии только из тех новостей которые имеют кол-во комментариев больше чем
	'fixed' => is_numeric($fixed) ? intval($fixed) : false, 						// выводить только комментарии из тех новостей которые зафиксированы
	'tags' => !empty($tags) ? strip_tags(stripslashes($tags)) : false, 				// выводить только комментарии из тех новостей которые имеют теги
	'news_read' => is_numeric($read) ? intval($read) : false, 						// выводить только комментарии из тех новостей которые имеют просмотров больше чем
	'rating_news' => is_numeric($nrating) ? intval($nrating) : false, 				// выводить только комментарии из тех новостей которые имеют рейтинг больше чем
	'only_fav' => is_numeric($fav) ? intval($fav) : false, 							// выводить только комментарии авторов которые имеют закладки
	'only_fullname' => is_numeric($fullname) ? intval($fullname) : false, 			// выводить только комментарии авторов которые заполнили полное имя
	'only_land' => is_numeric($land) ? intval($land) : false, 						// выводить только комментарии авторов которые заполнили место жительства
	'rating_comm' => is_numeric($rating) ? intval($rating) : false, 				// выводить только комментарии у которых рейтинг больше чем
	'news_xfield' => !empty($nxf) ? strip_tags(stripslashes($nxf)) : false, 		// взаемодействие с дополнительными полями новостей
	'user_xfield' => !empty($uxf) ? strip_tags(stripslashes($uxf)) : false, 		// взаемодействие с дополнительными полями пользователей
	'user' => !empty($user) ? strip_tags(stripslashes($user)) : false, 				// вывод комментариев только этого/этих пользователя(ей)
	'not_user' => !empty($not_user) ? strip_tags(stripslashes($not_user)) : false, 	// вывод комментариев кроме этого/этих пользователя(ей)
	'date_news' => !empty($date_news) ? strip_tags(stripslashes($date_news)) : false, 	// вывод комментариев за определенную дату новостей
	'date_comm' => !empty($date_comm) ? strip_tags(stripslashes($date_comm)) : false, 	// вывод комментариев за определенную дату комментариев
	'day_news' => !empty($day_news) ? intval($day_news) : false, 					// вывод комментариев за определенные дни новостей
	'day_comm' => !empty($day_comm) ? intval($day_comm) : false, 					// вывод комментариев за определенные дни комментариев
	'temp' => !empty($temp) ? intval($temp) : false, 								// задать шаблон для вывода комментариев
);

$ShowComments = new Comments;
$ShowComments->Start($CommCfg);
?>
