=== Plugin Name ===
Contributors: Zaine
Donate link: http://www.innsmouthenterprises.com/eve-killboard-plugin
Tags: eve, eveonline, eve online, killboard, widget, EVE Development Network Killboard, EDK, Eve Kill, www.eve-kill.net
Requires at least: 2.8
Tested up to: 3.9
Stable tag: 1.4

Pull your latest kills from EDK  Killboard or www.eve-kill.net rss feed  and display them as a widget.

== Description ==

Do you play Eve Online?  Do you or your Corporation have a Wordpress blog?   Do you have a killboard?   Do you want to brag about your kills?  If so, than this is the plugin for you!

It's a Wordpress Widget which can pull kills from an installation of the EDK Killboard, or pull them from an eve-kill.net XML feed.  

For the feed, you'll need to have your kills present in eve-kill.net.  For the EDK access, you'll need connection details for your EDK killboard database

== Installation ==

1. Unzip eve-killboard.zip in the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Chose were you are getting the kills from, either EDK or Eve-Kill.
1. EDK - Add your EVE Development Network Killboard connection details in Settings > Eve Killboard
1. EDK - If you want to stop your own deaths from showing, remove your Corporation from the drop down
1. EDK - Repeat above for any Corporations you don't want to show losses from
1. Eve-Kill - Add your pilot ID.  This is your accounts userid which you can get from the Eve API page.
1. Select if you want character portraits to show and select the size
1. Tweak what killwords you want to use.
1. Insert the widget via Appearance > Widgets into a widget space in your theme
1. Add a title for the widget and select the number of kills to display
1. Customise style.css within the eve-killboard directory so it matches your theme. 
1. Drop me a comment on http://www.innsmouthenterprises.com/eve-killboard-plugin/ and let me know ;)

== Frequently Asked Questions ==

None yet!

== Screenshots ==

1. An example screenshot with victim portraits.
1. An example screenshot of the widget
1. Screen shot using the new Shark5060 layout

== Changelog ==

= 1.4 =
* Look and feel improvements from Shark5060
* Allowed user to select if they want original view with Killverbs, or Shark5060 more pretty view
* Added timeout value to Curl pull: http://wordpress.org/support/topic/plugin-eve-killboard-plugin-timing-out-or-lack-off
* EDK queries pulling more data now for the enhanced view, but should be fine
* Added some settings for saving truncation options for Shark5060 view
* Added option for just using first names or full names for the Killer.  (Zaine kibbled Dave sounds better than Zaine Maltis kibbled Dave)
* Added ability to overwrite "podding" which was hardcoded as suggested by http://balex.inspire-web-studio.com/

= 1.3.3 =
* Another stylesheet tweak to override some cascading issues when img is styled.

= 1.3.2 =
* Fixed a really stupid error in previous change

= 1.3.1 = 
* Changes to try a different way of working out what version of EDK is being run

= 1.3 =
* Changed EveKill functionality so that it supports their new Epic functionality.
* Added Pilot, Corporation, Alliance, System, Region to the selection criteria
* Allowed selection of number of results to return
* Removed 16px portrait (not support by CCP image servers)
* Removed option to change evekill url.  Pointless now because of epic api being specific to evekill
* Fixed bug with li's overlapping when kill text was short.
 
= 1.2.1 =
* Fixed stray <? which causes breakage when running on PHP v5.3+
* Added warning due to problems with Eve-Kill having decommissioned their old feed.

= 1.2 =
* Added logic to determine if the user is running EDK V4 and if so, us the new SQL needed to pull the kills
* Added version number ot end of style sheet to force css decaching after plugin updates

= 1.1.1 =
* Fix some daft used of a div clear="both" in the output which was breaking the layout on http://nwire-cosma.de/.

= 1.1 =
* Added functionality for pulling the kills from a eve-kill.net rss feed.  
* Added customisable caching period for eve-kill feed, minimum 5 minutes.
* Small amounts of tidying in code to support.

= 1.0 = 
* Changed SQL to be compatible with EDK3.2 alpha
* Added ability to remove/add corporations from kill list.  This replaces it matching out your own corp to exclude them as this functionality breaks in the 3.2 version of EDK.
* Replaced the direct linking to EDK image directories for portraits with the portrait action url from EDK.  Far less dumb that way!
* Allowed ability to select portrait sizes from 32, 64, 128, 256 and 512 sizes.
* Cleaned up some white space
* Changed to version 1.0 as this has been working pretty well for a while.

= 0.7.0 = 
* Added functionality to allow you to include your victim photo.  Initially concept created by Circuitbomb from MASS and hacked into style by Zaine 
* YOU WILL NEED TO PLAY WITH THE STYLE SHEET TO MAKE THIS FIT!
* Added linking to the killboard kill 
* Added admin option to limit the results to just one Pilot.  (as requested by nuramori)

= 0.6.2 =
* Fixed a really subtle bug which broke your install if you were installed across different databases.  Thanks to Mike from www.dead-fish.com for finding it and Lee Willis for debugging.

= 0.6.1 = 
* Minimised SQL further.
* Added podding detection.

= 0.6 =
* Tweaked SQL so it works on 2.0 version of EDK
* Tried to minimse SQL so it doesn't pull as much rubbish
* Pulled out the hardcoding for my original corp in there.  Oops.
* Added db set up code for 'killwords'.
* Added admin screen to allow you to remove, add or deactivate killwords
* Added admin button to reset killwords.

= 0.5 =
* First released version
* Added admin menu to allow custom location of killboard
* Added readme and screenshot for wordpress.org inclusion

= 0.1 =
* Created!

== Upgrade Notice ==
= 1.1 =
Required for eve-kill.net feed consumption

= 1.0 =
Required for EDK3.2 compatibility

= 0.6.2 =
Significant upgrade.  Versions before here won't work if your wordpress db is on a different location to your killboard db.