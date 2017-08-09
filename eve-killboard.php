<?php
/*
 * Plugin Name:	 Eve Killboard Plugin
 * Plugin URI:	 http://www.innsmouthenterprises.com/eve-killboard-plugin
 * Version:		 1.4
 * Author:		 Zaine Maltis
 * Description:	 Pull latest kills from EVE Development Network Killboard, or rss from eve-kill.net
 * 				 and displays them on your wordpress blog as a widget
 * 				 For info about the killboard, see: http://eve-id.net/forum/
 * 				 For info about the feed, see: http://www.eve-kill.net
 *
 * Todo:
 *  Copyright 2011  Zaine Maltis (email : zaine@innsmouthenterprises.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

if ( !class_exists ( EveKillboardWidget ) ) {
	class EveKillboardWidget extends WP_Widget {
		var $adminOptionsName = "eveAdminOptions";
		var $plugin_version = "1.4";

		/**
		 * Declares the EveKillboardWidget class.
		 *
		 */
		function EveKillboardWidget () {
			global $wpdb;
			$this->killboard_table = $wpdb->prefix . "killboardverbs";
			$widget_ops = array ( 'classname' => 'widget_eve_killboard', 'description' => __ ( "Displays your latest kills" ) );
			$control_ops = array ( 'width' => 250, 'height' => 300 );
			$this->WP_Widget ( 'killboard', __ ( 'Eve Killboard Widget' ), $widget_ops, $control_ops );
		}

		function resetKillboardVerbs () {
			global $wpdb;
			$table_name = $this->killboard_table;
			$wpdb->query ( "DROP TABLE $table_name" );
			// SQL to create the killword table
			$sql = "CREATE TABLE " . $table_name . " (
						id mediumint(9) NOT NULL AUTO_INCREMENT,
						verb varchar(30) NOT NULL,
						active bool DEFAULT '1',
						UNIQUE KEY id (id)
				);";

			// create or modify the array
			require_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta ( $sql );

			// set up an array of our verbs.
			$initial_install = array ( "INSERT INTO $table_name set verb = 'destroyed'",
									   "INSERT INTO $table_name set verb = 'nuked'",
									   "INSERT INTO $table_name set verb = 'fragged'",
									   "INSERT INTO $table_name set verb = 'melted'",
									   "INSERT INTO $table_name set verb = 'recycled'",
									   "INSERT INTO $table_name set verb = 'gibbed'",
									   "INSERT INTO $table_name set verb = 'popped'",
									   "INSERT INTO $table_name set verb = 'peeled open'",
									   "INSERT INTO $table_name set verb = 'atomised'",
									   "INSERT INTO $table_name set verb = 'exploded'",
									   "INSERT INTO $table_name set verb = 'p0wned'",
									   "INSERT INTO $table_name set verb = 'obliterated'",
									   "INSERT INTO $table_name set verb = 'zorched'",
									   "INSERT INTO $table_name set verb = 'detonated'",
									   "INSERT INTO $table_name set verb = 'explodinated'",
									   "INSERT INTO $table_name set verb = 'devastated'",
									   "INSERT INTO $table_name set verb = 'slagged'",
									   "INSERT INTO $table_name set verb = 'blew up'",
									   "INSERT INTO $table_name set verb = 'murdered'",
									   "INSERT INTO $table_name set verb = 'dismantled'",
									   "INSERT INTO $table_name set verb = 'ravaged'",
									   "INSERT INTO $table_name set verb = 'eliminated'",
									   "INSERT INTO $table_name set verb = 'cremated'",
									   "INSERT INTO $table_name set verb = 'vapourised'",
									   "INSERT INTO $table_name set verb = 'kibbled'",
									   "INSERT INTO $table_name set verb = 'slagged'",
									   "INSERT INTO $table_name set verb = 'incinerated'",
									   "INSERT INTO $table_name set verb = 'annihilated'"
									 );

			// loop over the array and insert them.
			foreach ( $initial_install as &$insert ) {
				$results = $wpdb->query ( $insert );
			}
			unset ( $insert );
		}

		/**
		 * An init function to set up the admin options on activation
		 *
		 */
		function init () {
			// sort out our tables
			global $wpdb;
			$table_name = $this->killboard_table;

			// drops old table and populates
			$this->resetKillboardVerbs ();

			// register the version so we know.
			add_option ( "kill_db_version", "$this->kill_db_version" );

			// sort out the admin options
			$this->getEveAdminOptions ();
		}

		/**
		 * Get a verb
		 *
		 */
		function getActionVerb () {
			global $wpdb;
			$tablename = $this->killboard_table;
			$sql = "SELECT verb FROM $tablename WHERE active = 1";
			$num_verbs = $wpdb->query ( $sql );
			if ( $num_verbs < 1 ) {
				// no verbs!
				return ( -1 );
			}

			$results = $wpdb->get_row ( $sql, ARRAY_N, rand ( 0, --$num_verbs ) );
			return ( $results[0] );
		}

		/**
		 * This is an error catcher. Means when a db error happens, the widget doesn't get broken and break the page
		 * Instead it outputs the essentials plus the error message
		 */
		function killboard_fail ( $error ) {
			echo $beforewidget;
			echo $error;
			echo $after_widget;
			return;
		}

		/** 
		 * Get kills from EDK
		 *
		 */
		function getEDKKills ( $killnum, $adminOptions ) {
			$killdb_link = @mysql_connect ( $adminOptions['eveDatabaseServer'], $adminOptions['eveDatabaseUsername'], $adminOptions['eveDatabaseSecret'], TRUE);
			if (! $killdb_link) {
				$errormsg = "Could not connect: " . mysql_errno () . " " . mysql_error ();
				$this->killboard_fail ( $errormsg );
				return;
			}

			$db_selected = &mysql_select_db ( $adminOptions['eveDatabaseName'], $killdb_link );
			if ( !$db_selected ) {
				$errormsg = "Could not select db " . $adminOptions['eveDatabaseName'] . ": " . mysql_error ();
				$this->killboard_fail ( $errormsg );
				return;
			}

			// get kb host/domain from installed killboard
			$query = "SELECT cfg_value FROM kb3_config WHERE cfg_key = 'cfg_kbhost'";
			$kb_result = @mysql_query ( $query, $killdb_link );
			if ( !$kb_result ) {
				$errormsg = "Can't find Killboard Host: " . mysql_error ();
				$this->killboard_fail ( $errormsg );
				return;
			}

			$kbhost_row = mysql_fetch_assoc ( $kb_result );
			$kb_host = $kbhost_row['cfg_value'];

			// create a WHERE clause.
			$corpexclusions = unserialize ( $adminOptions['eveCorpExcl'] );
			if ( !empty ( $corpexclusions ) || ( isSet ( $adminOptions['evePilotName'] ) && ( strlen( $adminOptions['evePilotName'] ) > 1) ) ) {
				$whereclause = "WHERE ";
				if ( !empty ( $corpexclusions ) ) {
					$whereclause = $whereclause . "kll.kll_crp_id NOT IN ( " . implode ( ",", array_keys ( $corpexclusions ) ) . ")";
					if ( isSet ( $adminOptions['evePilotName'] ) && strlen ( $adminOptions['evePilotName'] ) > 1 ) {
						$whereclause = $whereclause . " AND ";
					}
				}

				// if they are limiting functionality to one pilot, add clause
				if ( isSet ( $adminOptions['evePilotName'] ) && strlen ( $adminOptions['evePilotName'] ) > 1 ) {
					$whereclause = $whereclause . "fbplt.plt_name = '" . $adminOptions['evePilotName'] . "'";
				}
			}

			$sql = "SELECT * FROM information_schema.COLUMNS
					WHERE TABLE_SCHEMA = '" . $adminOptions['eveDatabaseName'] . "'
					AND TABLE_NAME = 'kb3_kills'
					AND COLUMN_NAME = 'shp_name'";
			$version_result = @mysql_query ( $sql, $killdb_link );
			if ( !$version_result ) {
				$errormsg = "db version query failed: " . mysql_error ();
				$this->killboard_fail ( $errormsg );
				return;
			}

 			$row = mysql_fetch_assoc ( $version_result );
			$dbVersion = $row['COLUMN_NAME'];
			if ( !isset ( $dbVersion ) ) {
				// edkv4 version DB
				$sql = "SELECT 	kll.kll_id,
						kll.kll_isk_loss AS isk_loss,
						kll.kll_ship_id AS shp_id,
						plt.plt_name,
						plt.plt_externalid,
						sys.sys_name,
						fbplt.plt_name AS fbplt_name,
						inv.typeName AS shp_name,
						sys.sys_sec
					FROM kb3_kills kll
						INNER JOIN kb3_invtypes inv ON ( inv.typeID = kll.kll_ship_id )
						INNER JOIN kb3_pilots plt ON ( plt.plt_id = kll.kll_victim_id )
						INNER JOIN kb3_pilots fbplt ON ( fbplt.plt_id = kll.kll_fb_plt_id )
						INNER JOIN kb3_systems sys ON ( sys.sys_id = kll.kll_system_id )
						$whereclause order by kll_timestamp desc limit 0, $killnum";
			} else {
				// older db
				$sql = "SELECT	kll.kll_id,
						kll.kll_isk_loss AS isk_loss,
						kll.kll_ship_id AS shp_id,
						plt.plt_name,
						plt.plt_externalid,
						shp.shp_name,
						sys.sys_name,
						sys.sys_sec,
						fbplt.plt_name as fbplt_name
					FROM kb3_kills kll
						INNER JOIN kb3_ships shp ON ( shp.shp_id = kll.kll_ship_id )
						INNER JOIN kb3_pilots plt ON ( plt.plt_id = kll.kll_victim_id )
						INNER JOIN kb3_pilots fbplt ON ( fbplt.plt_id = kll.kll_fb_plt_id )
						INNER JOIN kb3_systems sys ON ( sys.sys_id = kll.kll_system_id )
						$whereclause order by kll_timestamp desc limit 0, $killnum";
			}

			$kills_result = @mysql_query ( $sql, $killdb_link );
			if ( !$kills_result ) {
				$errormsg = "Query failed: " . mysql_error ();
				$this->killboard_fail ( $errormsg );
				return;
			}

			$image_size = $adminOptions['image_size'];
			$kill_list = array ();
			$killcounter = 0;
			while ( $row = mysql_fetch_assoc ( $kills_result ) ) {
				$involved_result = @mysql_query ( "SELECT count(*) ipc FROM kb3_inv_detail WHERE ind_kll_id = " . $row['kll_id'], $killdb_link );
				if( !$involved_result ) {
					$errormsg = "Query failed: " . mysql_error ();
					$this->killboard_fail ( $errormsg );
					return;
				}

				$result = mysql_fetch_assoc ( $involved_result );
				$involved = ( int ) $result['ipc'] or 1;
				$kill_list[$killcounter]["victim"] = $row['plt_name'];
				$kill_list[$killcounter]["system"] = $row['sys_name'];
				$kill_list[$killcounter]["security"] = $row['sys_sec'];

				// if they want short names, give them one.
				if ( $adminOptions['eveShortNames'] == TRUE ) {
					$killer = split ( " ", $row['fbplt_name'] );
					$kill_list[$killcounter]["killer"] = $killer[0];
				} else {
					$kill_list[$killcounter]["killer"] = $row['fbplt_name'];
				}

				$kill_list[$killcounter]["shp_name"] = $row['shp_name'];
				$kill_list[$killcounter]["kill_lnk"] = $kb_host . "/?a=kill_detail&amp;kll_id=" . $row['kll_id'];
				$kill_list[$killcounter]["kill_isk"] = $row['isk_loss'];
				$kill_list[$killcounter]["involved"] = $involved;

				// display a pos image if it is tower, rather than a player (Control Tower's have no Pilot, so no valid image would be displayed)
				if ( strpos ( $kill_list[$killcounter]["shp_name"], 'Control Tower' ) !== false ) {
					$kill_list[$killcounter]["victim_img"] = "http://image.eveonline.com/Render/" . $row['shp_id'] . "_" . $image_size . ".png";
				} else {
					$kill_list[$killcounter]["victim_img"] = $kb_host . "/?a=thumb&amp;id=" . $row['plt_externalid'] . "&amp;size=" . $image_size . ".jpg";
				}

				$killcounter++;
			}
			return ( $kill_list );
		}

		function getEveKillKills ( $killnum, $adminOptions ) {
			// Work out cache time.
			$time = time (); //Current Time
			$cachedatetime = $adminOptions['eveCacheDateTime'];
			if ( $time < $cachedatetime ) {
				// the current time is less than the cachedate time, therefore return cached kills
				print "\n<!-- Cached feed -->";
				print "\n<!-- Cache will expire on: " . date ( 'l jS \of F Y h:i:s A', $adminOptions['eveCacheDateTime'] ) . " -->\n";
				return ( unserialize ( $adminOptions['eveCachedKillList'] ) );
			}

			// either the cache has expired or this is the first time
			// grab the json file and create a kill array.
			print "\n<!-- Cached expired -->\n";
			$evekilldata = preg_replace ( "/\s/", "_", $adminOptions['eveEveKillName'] );
			$evekilltype = $adminOptions['eveEveKillType'];

			$image_size = $adminOptions['image_size'];
			$eveEveKillNum = $adminOptions['eveEveKillNum'];

			// these are how the url is built
			// new url: http://eve-kill.net/epic/involvedPilot:$evekilldata/mask:9208
			// new url: http://eve-kill.net/epic/involvedCorporation:$evekilldata/mask:9208
			// new url: http://eve-kill.net/epic/involvedAlliance:$evekilldata/mask:9208
			// new url: http://eve-kill.net/epic/System:$evekilldata/mask:9208
			// new url: http://eve-kill.net/epic/Region:$evekilldata/mask:9208

			/* Bitmask operators:
			1: Show the URL to EVE-KILL. Example: {"url":"http:\/\/eve-kill.net\/?a=kill_detail&kll_id=11920528"}
			2: Show the Timestamp. Example: {"timestamp":"2011-12-17 20:15:00"}
			4: Show the internal EVE-KILL kill ID. Example: {"internalID":11920528}
			8: Show the CCP API kill ID. Example: {"externalID":21393914}
			16: Show the Victim Name. Example: {"VictimName":"Count MonteCarlo"}
			32: Show the Victim External ID. Example: {"VictimExternalID":519277171}
			64: Show the Victim Corp Name. Example: {"VictimCorpName":"Genos Occidere"}
			128: Show the Victim Alliance Name. Example: {"VictimAllianceName":"HYDRA RELOADED"}
			256: Show the Victim Ship Name. Example: {"VictimShipName":"Tornado"}
			512: Show the Victim Ships Class Name. Example: {"VictimShipClass":"Battlecruiser"}
			1024: Show the Victim Ships External ID. Example: {"VictimShipID":4310}
			2048: Show the FB Pilots name. Example: {"FBPilotName":"Irad Novar"}
			4096: Show the FB Pilots corp name. Example: {"FBCorpName":"ZERO T0LERANCE"}
			8192: Show the FB Pilots alliance name. Example: {"FBAllianceName":"RAZOR Alliance"}
			16384: Show the count of involved pilots. Example: {"InvolvedPartyCount":7}
			32768: Show the Solar System the kill happened in. Example: {"SolarSystemName":"EC-P8R"}
			65536: Show the Solar System security. Example: {"SolarSystemSecurity":-0.44658}
			131072: Show the Regions name. Example: {"RegionName":"Pure Blind"}
			262144: Show the ISK value of the kill (total loss (ship + modules)). Example: {"ISK":301368000}
			524288: Show the pilots involved
			*/

			$eveKill_url = "http://eve-kill.net/epic/$evekilltype:$evekilldata/mask:380209/mailLimit:$eveEveKillNum";
			$ch = curl_init ();
			curl_setopt ( $ch, CURLOPT_URL, $eveKill_url );
			curl_setopt ( $ch, CURLOPT_HEADER, 0 );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

			// timeout set to 10 seconds. Thanks Richard Coan
			// http://wordpress.org/support/topic/plugin-eve-killboard-plugin-timing-out-or-lack-off
			curl_setopt ( $ch, CURLOPT_TIMEOUT, 10 );
			$eveKill_data = curl_exec ( $ch );
			curl_close ( $ch );

			// turn the json into an array
			$data = json_decode ( $eveKill_data, true );
			if ( is_null ( $data ) ) {
				// the JSON isn't valid
				$kill_list['0']["failed"] = true;
			} else if ( $data == false ) {
				// Nothing in there
				$kill_list['0']["empty"] = true;
			} else {
				$killcounter = 0;
				$kill_list = array();
				foreach ( $data as $killmail ) {
					// if they want short names, give them one.
					if ( $adminOptions['eveShortNames'] == TRUE ) {
						$killer = split ( " ", $killmail['FBPilotName'] );
						$kill_list[$killcounter]["killer"] = $killer[0];
					} else {
						$kill_list[$killcounter]["killer"] = $killmail['FBPilotName'];
					}
					$kill_list[$killcounter]["victim"] = $killmail['victimName'];
					$kill_list[$killcounter]["system"] = $killmail['solarSystemName'];
					$kill_list[$killcounter]["security"] = $killmail['solarSystemSecurity'];
					$kill_list[$killcounter]["shp_name"] = $killmail['victimShipName'];
					$kill_list[$killcounter]["kill_lnk"] = $killmail['url'];
					$kill_list[$killcounter]["kill_isk"] = $killmail['ISK'];
					$kill_list[$killcounter]["involved"] = $killmail['involvedPartyCount'];

					// display a pos image if it is tower, rather than a player (Control Tower's have no Pilot, so no valid image would be displayed)
					if ( strpos ( $kill_list[$killcounter]["shp_name"], 'Control Tower' ) !== false ) {
						$kill_list[$killcounter]["victim_img"] = "http://image.eveonline.com/Render/" . $killmail['victimShipID'] . "_" . $image_size . ".png";
					} else {
						$kill_list[$killcounter]["victim_img"] = "http://image.eveonline.com/Character/" . $killmail['victimExternalID'] . "_".$image_size . ".jpg";
					}

					$killcounter++;
				}
			}

			$cachetime = $adminOptions['eveCacheTime'];
			$adminOptions['eveCachedKillList'] = serialize ( $kill_list );
			$adminOptions['eveCacheDateTime'] = $time + 60 * $cachetime;
			update_option ( $this->adminOptionsName, $adminOptions );
			print "<!-- Cache will expire on: " . date ( 'l jS \of F Y h:i:s A', $adminOptions['eveCacheDateTime'] ) . " -->\n";
			return ( $kill_list );
		}

		/**
		 * Displays the Widget
		 *
		 */

		function widget ( $args, $instance ) {
			// conversion function to return 3 digits
			function isk_number($num) {
				$num = number_format($num, 2); // all numbers will have a decimal
				$decimal = strpos($num, '.'); // get position of decimal
				$decimals = 2; // default round to 2 decimals

				// if length is too large change number of decimals
				$length = strlen($num);
					if ($length > 4) {
					$decimals = ($decimal > 2 ? 0 : 1);
				}

				return number_format($num, $decimals);
			}
			extract ( $args );
			$title = apply_filters ( 'widget_title', empty ( $instance['title'] ) ? '&nbsp;' : $instance['title'] );
			$killnum = empty ( $instance['killnum'] ) ? '5' : $instance['killnum'];

			// get the kills
			$adminOptions = $this->getEveAdminOptions ();
			$display_image = $adminOptions['eveKillImg'];
			$image_size = $adminOptions['image_size'];

			// work out how we are getting the kills
			if ( $adminOptions['eveConnectionType'] == "EDK" ) {
				$kill_list = $this->getEDKKills ( $killnum, $adminOptions );
			} else {
				$kill_list = $this->getEveKillKills ( $killnum, $adminOptions );
			}

			// Before the widget
			echo $before_widget;

			$version = $this->plugin_version;
			$version_comment = "<!-- Eve Killboard Widget v" . $version . " begins -->\n";
			
			echo "$version_comment";
			// The title.  If it's set, display it.
			if ( $title ) {
				echo $before_title . $title . $after_title . "\n";
			}

			// loop out the unordered list with the kills
			if ( $display_image > 0 ) {
				// use the style without the bullet
				echo ( "<ul class='kill'>\n" );
			} else {
				// use the style with the bullet
				echo ( "<ul class='kill_bull'>\n" );
			}

			if ( $kill_list['0']["failed"] == true ) {
				echo ( "Failed to pull from " . $adminOptions['eveEveKillUrl'] . "\n" . "API unavailable or timeout." );
			} else if ( $kill_list['0']["empty"] == true ) {
				echo ( "No kills this month." );
			} else {
				foreach ( $kill_list as $kill ) {
					$killer = $kill['killer'];
					$victim = $kill['victim'];
					$ship = $kill['shp_name'];
					$system = $kill['system'];
					$kill_lnk = $kill['kill_lnk'];
					$victim_img = $kill['victim_img'];
					$security = $kill['security'];
					$isk = $kill['kill_isk'];
					$involved = $kill['involved'];
					$security = number_format ( $kill["security"], 1 );

					switch($isk) {
						case $isk >= 1000000000:
							$isk = isk_number($isk / 1000000000) . "b";
							break;
						case $isk >= 1000000:
							$isk = isk_number($isk / 1000000) . "m";
							break;
						case $isk >= 1000:
							$isk = isk_number($isk / 1000) . "k";
							break;
						default:
							$isk = isk_number($isk);
							break;
					}

					// Truncate line after x chars. This INCLUDES the additional info (ISK and involved).
					// basicly the info "Ship: Redeemer (1.43b)" requires 16 chars (8 shipname, 5 isk, 3 brackets + space)
					// the info "Kill: Redamok Houssa (+14)" would require 20 chars and is shortened to
					// "Kill: Redamok H. (+14)" which only requires 16 chars.
					// The brackets and the space is already added in the script, so it should not be calculated into this number.
					$adminOptions['eveVictimTruncateNum'] = 20; // +0
					$adminOptions['eveShipTruncateNum'] = 17; // +3  " (<isk>)"
					$adminOptions['eveKillerTruncateNum'] = 16; // +4 " (+<involved>)"
					
					// Name formatting. Long names like "Caldari Control Tower" are shortened to Caldari C. T.
					$shiplen = strlen ( $ship ) + strlen ( $isk ) + 3;
					if ( $adminOptions['eveShipTruncate'] == TRUE ) {
						if ( $shiplen >= $adminOptions['eveShipTruncateNum'] ) {
							$ship_name = preg_split ( '/\s|-/', $ship, null, PREG_SPLIT_NO_EMPTY );
							$ship_count = 1;
							foreach ( $ship_name as $data ) {
								if ( $ship_count == 1 ) {
									$ship = $data . " ";
								} elseif ( $ship_count <= 3 ) {
									$ship .= substr ( $data, 0, 1 ) . ". ";
								}
								$ship_count++;
							}
						}
						if ( ( strlen ( $ship ) + strlen ( $isk ) + 3 ) >= $adminOptions['eveShipTruncateNum'] ) {
							substr ( $ship, 0, ( $adminOptions['eveShipTruncateNum'] - 3 ) ) . "...";
						}
					}

					$killerlen = strlen ( $killer ) + strlen ( $involved ) + 4;
					if ( $adminOptions['eveKillerTruncate'] == TRUE ) {
						if ( $killerlen >= $adminOptions['eveKillerTruncateNum'] ) {
							$kill_name = preg_split ( '/\s|-/', $killer, null, PREG_SPLIT_NO_EMPTY );
							$kill_count = 1;
							foreach ( $kill_name as $data ) {
								if ( $kill_count == 1 ) {
									$killer = $data . " ";
								} elseif ( $kill_count <= 3 ) {
									$killer .= substr ( $data, 0, 1 ) . ". ";
								}
								$kill_count++;
							}
						}
						if ( ( strlen ( $killer ) + strlen ( $involved ) + 4 ) >= $adminOptions['eveKillerTruncateNum'] ) {
							substr ( $killer, 0, ( $adminOptions['eveKillerTruncateNum'] - 3 ) ) . "...";
						}
					}

					if ( $adminOptions['eveVictimTruncate'] == TRUE ) {
						if ( strlen ( $victim ) >= $adminOptions['eveVictimTruncateNum'] ) {
							$vic_name = preg_split ( '/\s|-/', $victim, null, PREG_SPLIT_NO_EMPTY );
							$vic_count = 1;
							foreach ( $vic_name as $data ) {
								if( $vic_count == 1 ) {
									$victim = $data . " ";
								} elseif ( $vic_count <= 3 ) {
									$victim .= substr ( $data, 0, 1 ) . ". ";
								}
								$vic_count++;
							}
						}
						if ( strlen ( $victim ) >= $adminOptions['eveVictimTruncateNum'] ) {
							substr ( $victim, 0, ( $adminOptions['eveVictimTruncateNum'] - 3 ) ) . "...";
						}
					}

					// System Security coloring.
					$sec_array = array ( "#F00000", "#D73000", "#F04800", "#F06000", "#D77700", "#EFEF00", "#8FEF2F", "#00F000", "#00EF47", "#48F0C0", "#2FEFEF" );
					if ( $security < 0 ) {
						$seccolor = $sec_array[0];
					} else {
						$seccolor = $sec_array[$security*10];
					}

					$li_height = $image_size + 2;
					$kill_line = "<li style='min-height: " . $li_height . "px;' class='kill'>";
					if ( $adminOptions['eveLayout'] == "Shark5060" ) {
						if ( $display_image > 0 ) {
							$kill_line .= "<span class='kill_image'><a class='kill' href='$kill_lnk' target='_blank'><img src='$victim_img' width='$image_size' height='$image_size' alt='Victim' /></a></span>";
						}

						$kill_line .= "<span class='kill_text'>";
						$kill_line .= "<span class='kill_text_heading'>Pilot: </span>" . $victim . "<br />";
						$kill_line .= "<span class='kill_text_heading'>Ship: </span>" . $ship . " (" . $isk .")<br />";
						$kill_line .= "<span class='kill_text_heading'>System: </span>" . $system . " (<span style='color:" . $seccolor . ";'>" . $security . "</span>)<br />";
						$kill_line .= "<span class='kill_text_heading'>Kill: </span>" . $killer . " (+" . ( $involved - 1 ) . ")</span></li>\n";
					} else {
						if ( $display_image > 0 ) {
							$kill_line .= "<span class='kill_image'><img src='$victim_img' width='$image_size' height='$image_size' class='victim_image' /></span>";
						}
						// link to kill
						$kill_line .= "<span class='kill_text'><a class='kill' href='$kill_lnk'>";
						if ( $ship == "Capsule" ) {
							// it was a podding!
							if ( isset ( $adminOptions['evePodWord'] ) ) {
								$poddterm = $adminOptions['evePodWord'];
							} else {
								$poddterm = "podded";
							}
							$kill_line .= $killer . " " . $poddterm . " " . $victim . " in " . $system . "</a></li>\n";
						} else {
							$verb = $this->getActionVerb (); // this grabs a random verb for use
							if ( $verb == -1 ) {
								// this is an error, abort
								echo "No verbs available. Please enable some, or reinstall plugin<br>";
								break;
							}
							$kill_line .= $killer . " " . $verb . " " . $victim . "'s " . $ship . " in " . $system . "</a></span></li>\n";
						}
					}
					echo $kill_line;
				}
			}
			echo ( "</ul>\n" );

			echo "<!-- Eve Killboard Widget Ends -->\n";
			// After the widget
			echo $after_widget;
		}

		/**
		 * Saves the widgets settings.
		 *
		 */
		function update ( $new_instance, $old_instance ) {
			$instance = $old_instance;
			$instance['title'] = strip_tags ( stripslashes ( $new_instance['title'] ) );
			$instance['killnum'] = strip_tags ( stripslashes ( $new_instance['killnum'] ) );
			return $instance;
		}

		/**
		 * Creates the edit form for the widget.
		 *
		 */
		function form ( $instance ) {
			// Defaults
			$instance = wp_parse_args ( ( array ) $instance, array ( 'title' => '', 'killnum' => '5' ) );
			$title = htmlspecialchars ( $instance['title'] );
			$killnum = htmlspecialchars ( $instance['killnum'] );

			// Output the options
			echo '<p><label for="' . $this->get_field_name ( 'title' ) . '">' . __ ( 'Title:' ) . ' <input style="widefat" id="' . $this->get_field_id ( 'title' ) . '" name="' . $this->get_field_name ( 'title' ) . '" type="text" value="' . $title . '" /></label></p>';
			// Number of kills to display
			echo '<p><label for="' . $this->get_field_name ( 'killnum' ) . '">' . __ ( 'Number of kills to display:' ) . ' <input style="width: 50px;" id="' . $this->get_field_id ( 'killnum' ) . '" name="' . $this->get_field_name ( 'killnum' ) . '" type="text" value="' . $killnum . '" /></label></p>';
		}

		/**
		 * Adds the stylesheet to the head section
		 *
		 */
		function addHeaderItems () {
			$baseurl = get_bloginfo ( 'url' );
			$version = $this->plugin_version;
			?>
			<!-- EveKillboard stylesheet //-->
			<link rel="stylesheet" type="text/css" href="<?php echo ( $baseurl ); ?>/wp-content/plugins/eve-killboard/style.css?<?php echo ( $version ); ?>">
			<?php
		}

		function regAdminMenu () {
			add_options_page ( 'Eve Killboard', 'Eve Killboard', 9, 'eve_killboard', array ( &$this, 'printAdminPage' ) );
		}

		function getEveAdminOptions () {
			// grabs the options from the DB, or sets them up and stores them if not.
			$eveAdminOptions = array ( 'eveDatabaseServer' => '',
									   'eveDatabaseName' => '',
									   'eveDatabaseUsername' => '',
									   'eveDatabaseSecret' => '',
									   'eveKillImg' => '',
									   'eveCorpExcl' => ''
									 );
			$eveOptions = get_option ( $this->adminOptionsName );
			if ( !empty ( $eveOptions ) ) {
				foreach ( $eveOptions as $key => $option ) {
					$eveAdminOptions[$key] = $option;
				}
			}
			update_option ( $this->adminOptionsName, $eveAdminOptions );
			return ( $eveAdminOptions );
		}

		function getActionVerbs () {
			// get our exciting action verbs
			global $wpdb;
			$tablename = $this->killboard_table;
			$sql = "SELECT id, verb, active FROM $tablename;";
			return ( $wpdb->get_results ( $sql, ARRAY_A ) );
		}

		function getCorporations ( $adminOptions ) {
			$corpexclusions = unserialize ( $adminOptions['eveCorpExcl'] );
			// grab the corporations from the killboard DB and remove our selected ones.
			// connect to the killboard DB to get list of corporations in there
			$killdb_link = @mysql_connect ( $adminOptions['eveDatabaseServer'], $adminOptions['eveDatabaseUsername'], $adminOptions['eveDatabaseSecret'], TRUE );
			$db_selected = &mysql_select_db ( $adminOptions['eveDatabaseName'], $killdb_link );
			if (! $db_selected) {
				$errormsg = "Could not select db " . $adminOptions['eveDatabaseName'] . ": " . mysql_error ();
				$this->killboard_fail ( $errormsg );
				return;
			}

			// generate exclusion list
			if ( !empty ( $corpexclusions ) ) {
				$whereclause = "WHERE crp_id NOT IN ( " . implode ( ",", ( array_keys ( $corpexclusions ) ) ) . ")";
			} else {
				$whereclause = " ";
			}

			$sql = "SELECT crp_id, crp_name FROM `kb3_corps` $whereclause ORDER BY crp_name";
			$corp_result = @mysql_query ( $sql, $killdb_link );
			if ( !$corp_result ) {
				print "Failed to get list of Corporations from Killboard: " . mysql_error ();
				return;
			}

			$corporation = array ();
			// loop over and build array of corps
			while ( $row = mysql_fetch_assoc ( $corp_result ) ) {
				array_push ( $corporation, $row );
			}
			return ( $corporation );
		}

		function getCorporationName ( $adminOptions, $corpID ) {
			// connect to the killboard DB to get list of corporations in there
			$killdb_link = @mysql_connect ( $adminOptions['eveDatabaseServer'], $adminOptions['eveDatabaseUsername'], $adminOptions['eveDatabaseSecret'], TRUE);
			$db_selected = &mysql_select_db ( $adminOptions['eveDatabaseName'], $killdb_link );
			if ( !$db_selected ) {
				$errormsg = "Could not select db " . $adminOptions['eveDatabaseName'] . ": " . mysql_error ();
				$this->killboard_fail ( $errormsg );
				return;
			}

			$sql = "SELECT crp_name FROM `kb3_corps` WHERE crp_id = '$corpID'";
			$corp_result = @mysql_query ( $sql, $killdb_link );
			if ( !$corp_result ) {
				print "Failed to get corporation name from Killboard: " . mysql_error ();
				return;
			}
			return ( mysql_result ( $corp_result, 0 ) );
		}

		function printAdminPage () {
			global $wpdb;
			$tablename = $this->killboard_table;
			$adminOptions = $this->getEveAdminOptions ();

			// check for connection type change
			if (isset ( $_POST['update_eveConnectionType'] ) ) {
				$adminOptions['eveConnectionType'] = $_POST['eveConnectionType'];
				update_option ( $this->adminOptionsName, $adminOptions );
			}

			// check for layout type change
			if ( isset ( $_POST['update_eveLayout'] ) ) {
				$adminOptions['eveLayout'] = $_POST['eveLayout'];
				update_option ( $this->adminOptionsName, $adminOptions );
			}

			// check for settings changes.
			if ( isset ( $_POST['update_eveKillboardOptions'] ) ) {
				if ( isset ( $_POST['eveDatabaseServer'] ) ) {
					// killboard server
					$adminOptions['eveDatabaseServer'] = $_POST['eveDatabaseServer'];
				}
				if ( isset ( $_POST['eveDatabaseName'] ) ) {
					// killboard database name
					$adminOptions['eveDatabaseName'] = $_POST['eveDatabaseName'];
				}
				if ( isset ( $_POST['eveDatabaseUsername'] ) ) {
					// killboard username
					$adminOptions['eveDatabaseUsername'] = $_POST['eveDatabaseUsername'];
				}
				if ( isset ( $_POST['eveDatabaseSecret'] ) ) {
					// killboard password
					$adminOptions['eveDatabaseSecret'] = $_POST['eveDatabaseSecret'];
				}
				if ( isset ( $_POST['check_eveVictimTruncate'] ) == "ON" ) {
					// truncate the victims name if too long
					$adminOptions['eveVictimTruncate'] = '1';
				} else {
					$adminOptions['eveVictimTruncate'] = '0';
				}
				if ( isset ( $_POST['check_eveKillerTruncate'] ) == "ON" ) {
					// truncate the killers name if too long
					$adminOptions['eveKillerTruncate'] = '1';
				} else {
					$adminOptions['eveKillerTruncate'] = '0';
				}
				if ( isset ( $_POST['check_eveShipTruncate'] ) == "ON" ) {
					// truncate the ship name if too long
					$adminOptions['eveShipTruncate'] = '1';
				} else {
					$adminOptions['eveShipTruncate'] = '0';
				}
				if ( isset ( $_POST['check_eveShortNames'] ) == "ON" ) {
				// show only first name of killer
					$adminOptions['eveShortNames'] = '1';
				} else {
					$adminOptions['eveShortNames'] = '0';
				}
				if ( isset ( $_POST['check_eveKillboardVictim'] ) == "ON" ) {
					// show victim images
					$adminOptions['eveKillImg'] = '1';
				} else {
					$adminOptions['eveKillImg'] = '0';
				}
				if ( isset ( $_POST['image_size'] ) ) {
					// save the image size
					$adminOptions['image_size'] = $_POST['image_size'];
				}
				if ( isset ( $_POST['evePilotName'] ) ) {
					// save a pilots name
					$adminOptions['evePilotName'] = $_POST['evePilotName'];
				}
				if ( isset ( $_POST['eveEveKillType'] ) ) {
					// save a EveKill type of search
					$adminOptions['eveEveKillType'] = $_POST['eveEveKillType'];
				}
				if ( isset ( $_POST['eveEveKillName'] ) ) {
					// save the EveKill data
					$adminOptions['eveEveKillName'] = $_POST['eveEveKillName'];
				}
				if ( isset ( $_POST['eveEveKillNum'] ) ) {
					// save the eveEveKillNum
					$adminOptions['eveEveKillNum'] = $_POST['eveEveKillNum'];
				}
				if ( isset ( $_POST['eveCacheTime'] ) ) {
					// minutes to run cache
					$cachetime = $_POST['eveCacheTime'];
					if ( $cachetime < 5 ) {
						$cachetime = 5;
					}
					$adminOptions['eveCacheTime'] = $cachetime;
				}

				// expire our cache for saved feeds.
				$adminOptions['eveCacheDateTime'] = 0;
				update_option ( $this->adminOptionsName, $adminOptions );
			}

			// Corporations
			if ( isset ( $_POST['remove_eveCorporationName'] ) ) {
				// grab the current exclusion from the WP DB and add to corp and save back
				$corpexclusions = unserialize ( $adminOptions['eveCorpExcl'] );
				$corpname = $this->getCorporationName ( $adminOptions, $_POST['delcorporation'] );
				if ( empty ( $corpexclusions ) ) {
					$corpexclusions[$_POST['delcorporation']] = $corpname;
				} else {
					$corpexclusions[$_POST['delcorporation']] = $corpname;
				}

				// add to array and serialize for storing
				$adminOptions['eveCorpExcl'] = serialize ( $corpexclusions );
				update_option ( $this->adminOptionsName, $adminOptions );
			}

			if ( isset ( $_POST['add_eveCorporationName'] ) ) {
				// grab the current exclusion from the WP DB, remove and save back
				$corpexclusions = unserialize ( $adminOptions['eveCorpExcl'] );
				unset ( $corpexclusions[$_POST['addcorporation']] );
				$adminOptions['eveCorpExcl'] = serialize ( $corpexclusions );
				update_option ( $this->adminOptionsName, $adminOptions );
			}

			// killverbs:
			if ( isset ( $_POST['update_eveKillboardVerbs'] ) ) {
				// set all as deactive
				$sql = "UPDATE $tablename SET active = '0'";
				$wpdb->query ( $sql );
				if ( $_POST[verb] ) { // incase none were submitted
					foreach ( $_POST[verb] as $verb ) {
						// each checked gets set active
						$sql = "UPDATE $tablename set active = '1' WHERE id = $verb";
						$wpdb->query ( $sql );
					}
				}
			}

			if ( isset ( $_POST['add_eveKillboardVerb'] ) ) {
				$sql = "INSERT INTO $tablename (verb, active) VALUES (%s, '1')";
				$wpdb->query ( $wpdb->prepare ( $sql, $_POST[newkillword] ) );
			}
			if ( isset ( $_POST['remove_eveKillboardVerb'] ) ) {
				$sql = "DELETE FROM $tablename WHERE id = %d";
				$wpdb->query ( $wpdb->prepare ( $sql, $_POST[delkillword] ) );
			}
			if ( isset ( $_POST['add_evePodWord'] ) ) {
				// overwrite the default term "podded"
				$adminOptions['evePodWord'] = $_POST['evePodWord'];
				update_option ( $this->adminOptionsName, $adminOptions );
			}
			if ( isset ( $_POST['reset_eveKillboardVerbs'] ) ) {
				$this->resetKillboardVerbs ();
			}

			// refresh the admin options incase they've been changed
			$adminOptions = $this->getEveAdminOptions ();
			$actionVerbs = $this->getActionVerbs ();
			if ( isset ( $_POST['update_eveKillboardOptions'] ) OR
				 isset ( $_POST['update_eveKillboardVerbs'] ) OR
				 isset ( $_POST['add_eveKillboardVerb'] ) OR
				 isset ( $_POST['reset_eveKillboardVerbs'] ) OR
				 isset ( $_POST['add_evePodWord'] ) OR
				 isset ( $_POST['remove_eveKillboardVerb'] ) ) {

				// then display a message saying we've updated the settings
				?>
				<div class="updated"><p><strong><?php _e ( "Settings Updated.", "EveKillBoard" ); ?></strong></p></div>
			<?php } ?>

			<div class="wrap">
				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<h2>Eve Killboard Plugin</h2>
					<h3>Connection Type</h3>
					<input type="radio" name="eveConnectionType" value="EDK" <?php if ( $adminOptions['eveConnectionType'] != "EVEKILL" ) print "checked "; ?>> EDK Killboard
					<input type="radio" name="eveConnectionType" value="EVEKILL" <?php if ( $adminOptions['eveConnectionType'] == "EVEKILL" ) print "checked "; ?>> Eve Kill RSS Feed.
					<p class="submit"><input type="submit" name="update_eveConnectionType" class="button-primary" value="<?php _e ( 'Save Changes' )?>" /></p>
				</form>
				<h3>Widget Layout</h3>
				<p>How do you want this to look?</p>
				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<input type="radio" name="eveLayout" value="Default" <?php if ( $adminOptions['eveLayout'] == "Default" or !isset ( $adminOptions['eveLayout'] ) ) print "checked "; ?>> Default (includes Killverbs)
					<input type="radio" name="eveLayout" value="Shark5060" <?php if ( $adminOptions['eveLayout'] == "Shark5060" ) print "checked "; ?>> Shark5060's cool layout.
					<p class="submit"><input type="submit" name="update_eveLayout" class="button-primary" value="<?php _e ( 'Save Changes' ) ?>" /></p>
				</form>
				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<?php if ( $adminOptions['eveConnectionType'] == "EDK" ) { ?>
					<h3>EDK Database Connection</h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">Database server</th>
							<td><input type="text" size="50" name="eveDatabaseServer" value="<?php echo $adminOptions['eveDatabaseServer']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row">Database name</th>
							<td><input type="text" size="50" name="eveDatabaseName" value="<?php echo $adminOptions['eveDatabaseName']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row">Username</th>
							<td><input type="text" size="50" name="eveDatabaseUsername" value="<?php echo $adminOptions['eveDatabaseUsername']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row">Password</th>
							<td><input type="password" size="50" name="eveDatabaseSecret" value="<?php echo $adminOptions['eveDatabaseSecret']; ?>" /></td>
						</tr>
					</table>
					<?php } elseif ( $adminOptions['eveConnectionType'] == "EVEKILL" ) { ?>
					<h3>Feed Details</h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">Type</th>
							<td>
								<select name="eveEveKillType">
									<option value="involvedPilot" <?php if ( $adminOptions['eveEveKillType'] == 'involvedPilot' ) { echo "selected "; } ?>/>Pilot</option>
									<option value="involvedCorp" <?php if ( $adminOptions['eveEveKillType'] == 'involvedCorp' ) { echo "selected "; } ?>/>Corporation</option>
									<option value="involvedAlliance" <?php if ( $adminOptions['eveEveKillType'] == 'involvedAlliance' ) { echo "selected "; } ?>/>Alliance</option>
									<option value="system" <?php if ( $adminOptions['eveEveKillType'] == 'system' ) { echo "selected "; } ?>/>System</option>
									<option value="region" <?php if ( $adminOptions['eveEveKillType'] == 'region' ) { echo "selected "; } ?> />Region</option>
								</select>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Name</th>
							<td><input type="text" size="50" name="eveEveKillName" value="<?php echo $adminOptions['eveEveKillName']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row">Number of kills to return</th>
							<td><input type="text" size="50" name="eveEveKillNum" value="<?php if ( isset ( $adminOptions['eveEveKillNum'] ) ) { echo $adminOptions['eveEveKillNum']; } else { print "5"; } ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row">Cache Time (minutes)</th>
							<td><input type="text" size="50" name="eveCacheTime" value="<?php if ( isset ( $adminOptions['eveCacheTime'] ) ) { echo $adminOptions['eveCacheTime']; } else { print "5"; } ?>" /></td>
						</tr>
					</table>
					<?php } if ( $adminOptions['eveLayout'] == "Shark5060" ) { ?>
					<h3>Shark5060 Layout Options</h3>
					<p>Sometimes names can be longer than fit in your layout. Select if you want to truncate them.</p>
					<div class="killoptions">
						<p>Truncate Victim's Name: <input type="checkbox" name="check_eveVictimTruncate" value="ON" <?php if ( $adminOptions['eveVictimTruncate'] == '1' ) { echo 'checked="checked"'; } ?> /></p>
						<p>Truncate Killer's Name: <input type="checkbox" name="check_eveKillerTruncate" value="ON" <?php if ( $adminOptions['eveKillerTruncate'] == '1' ) { echo 'checked="checked"'; } ?> /></p>
						<p>Truncate Ship Name: <input type="checkbox" name="check_eveShipTruncate" value="ON" <?php if ( $adminOptions['eveShipTruncate'] == '1' ) { echo 'checked="checked"'; } ?> /></p>
					</div>
					<?php } ?>
					<h3>Killers first names only?</h3>
					<div class="killoptions">
						<p>Killers first name only: <input type="checkbox" name="check_eveShortNames" value="ON" <?php if ( $adminOptions['eveShortNames'] == '1' ) { echo 'checked="checked"'; } ?> /></p>
					</div>
					<h3>Victim Images</h3>
					<div class="killoptions">
						<p>Display Victim Portrait: <input type="checkbox" name="check_eveKillboardVictim" value="ON" <?php if ( $adminOptions['eveKillImg'] == '1' ) { echo 'checked="checked"'; } ?> /></p>
					</div>
					<div class="killoptions">
						<p>Size of Portrait: <select name="image_size">
							<option value="32" <?php if ( $adminOptions['image_size'] == "32" ) echo "selected='selected'"; ?>>32 pixels</option>
							<option value="64" <?php if ( $adminOptions['image_size'] == "64" ) echo "selected='selected'"; ?>>64 pixels</option>
							<option value="128" <?php if ( $adminOptions['image_size'] == "128" ) echo "selected='selected'"; ?>>128 pixels</option>
							<option value="256" <?php if ( $adminOptions['image_size'] == "256" ) echo "selected='selected'"; ?>>256 pixels</option>
							<option value="512" <?php if ( $adminOptions['image_size'] == "512" ) echo "selected='selected'"; ?>>512 pixels</option>
						</select></p>
					</div>
					<?php if ( $adminOptions['eveConnectionType'] == "EDK" ) { ?>
					<h3>Only Me?</h3>
					<p>Add your pilot's name to here if you only want your kills to display, otherwise leave it blank for the whole corp.</p>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">Pilot Name</th>
							<td><input type="text" name="evePilotName" value="<?php echo $adminOptions['evePilotName']; ?>" /></td>
						</tr>
					</table>
					<?php } ?>
					<p class="submit"><input type="submit" name="update_eveKillboardOptions" class="button-primary" value="<?php _e ( 'Save Changes' ) ?>" /></p>
				</form>
				<?php if ( $adminOptions['eveConnectionType'] == "EDK" ) {
						   $corporations = $this->getCorporations ( $adminOptions );
						   $excludedcorporations = unserialize ( $adminOptions['eveCorpExcl'] );
				?>
				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<h3>Exclude these Corps</h3>
					<div class="killoptions">
						<p>Exclude a Corporation from the Kill List. You probably want to exclude your own Corporation from here, unless you like displaying how you fail.</p>
						<p>Exclude the following Corporations from the Kill List: <select name="delcorporation">
							<?php foreach ( $corporations as $corprow ) { echo " <option value='$corprow[crp_id]'>$corprow[crp_name]</option>"; } ?>
							</select>
							<input type="submit" 	name="remove_eveCorporationName" class="button-primary" value="<?php _e ( 'Remove Corporation' ) ?>" />
						</p>
					</div>
				</form>
				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<h3>Add these Corps back</h3>
					<div class="killoptions">
						<p>Add the following Corporations back into the Kill List: <select name="addcorporation">
							<?php foreach ( $excludedcorporations as $corpid => $corpname ) { echo " <option value='$corpid'>$corpname</option>"; } ?>
							</select>
							<input type="submit" name="add_eveCorporationName" class="button-primary" value="<?php _e ( 'Add Corporation' ) ?>" />
						</p>
					</div>
				</form>
				<?php } if ( $adminOptions['eveLayout'] == "Default" or !isset ( $adminOptions['eveLayout'] ) ) { ?>
				<h3>Kill Words!</h3>
				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<div class="killwords">
					<?php
						$loopcount = 0;
						if ( $actionVerbs ) {
							foreach ( $actionVerbs as $actionrow ) {
								$loopcount ++;
								echo '<input type="checkbox" name="verb[]" value="' . $actionrow[id] . '"';
								if ( $actionrow[active] == 1 ) {
									echo ' checked="checked"';
								}
								echo '/>' . $actionrow[verb] . '<br/>';
								$modcheck = $loopcount % 10;
								if ( $modcheck == 0 ) {
									echo '</div><div class="killwords">';
								}
							}
						} else { ?>
							No Verbs in DB!
						<?php } ?>
					</div>
					<div style="clear: both"></div>
					<p class="submit">
						<input type="submit" name="update_eveKillboardVerbs" class="button-primary" value="<?php _e ( 'Save Changes' ) ?>" />
					</p>
				</form>
				<div style="clear: both"></div>
				<form method="post"	action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<h3>Add a new killword</h3>
					<div class="killoptions">
						<p>Killword to add: <input type="text" name="newkillword"> <input type="submit" name="add_eveKillboardVerb" class="button-primary" value="<?php _e ( 'Add Killword' ) ?>" /></p>
					</div>
				</form>
				<div style="clear: both"></div>
				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<h3>Delete a killword</h3>
					<div class="killoptions">
						<p>Select the killword to remove from the dropdown: <select	name="delkillword">
						<?php foreach ( $actionVerbs as $actionrow ) { echo " <option value='$actionrow[id]'>$actionrow[verb]</option>"; } ?>
						</select> <input type="submit" name="remove_eveKillboardVerb" class="button-primary" value="<?php _e ( 'Delete Killword' ) ?>" /></p>
					</div>
				</form>
				<div style="clear: both"></div>
				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<h3>Call "podding" something other than podding?</h3>
					<div class="killoptions">
						<p>Change here: <input type="text" name="evePodWord" value="<?php echo ( $adminOptions['evePodWord'] ); ?>"> <input type="submit" name="add_evePodWord" class="button-primary" value="<?php _e ( 'Submit' ) ?>" /></p>
					</div>
				</form>
				<div style="clear: both"></div>
				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
					<h3>Big Bad Reset Button!</h3>
					<div class="killoptions">
						<p>If something screwy has happened to your verb database, use this button to reset it to default <input type="submit" name="reset_eveKillboardVerbs" class="button-primary" value="<?php _e ( 'Reset Me!' ) ?>" /></p>
					</div>
				</form>
				<?php } ?>
			</div>
<?php } // End printAdminPage()
	} // END class
}
/**
 * Register widget.
 * Calls 'widgets_init' action after the widget has been registered.
 */
$eveKillboard = new EveKillboardWidget ();

// registers the class as a widget.
add_action ( 'widgets_init', create_function ( '', 'return register_widget("EveKillboardWidget");' ) );

// registers a function for the head section. addHeaderItems() outputs the stylesheet.
add_action ( 'wp_head', array ( &$eveKillboard, 'addHeaderItems' ) );

// registers an administration menu
add_action ( 'admin_menu', array ( &$eveKillboard, 'regAdminMenu' ) );

// makes sure we have options created at activation
add_action ( 'activate_eve-killboard/eve-killboard.php', array ( &$eveKillboard, 'init' ) );
?>