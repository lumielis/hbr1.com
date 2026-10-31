-- HBR1 schema baseline
-- database: hbr1
-- generated: 2026-10-31T18:03:12+00:00

-- access
CREATE TABLE `access` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `group_id` int(10) unsigned NOT NULL DEFAULT 0,
  `timestamp_start` bigint(20) NOT NULL DEFAULT 0,
  `timestamp_update` bigint(20) NOT NULL DEFAULT 0,
  `session_key` char(32) NOT NULL DEFAULT '',
  `duration` int(10) NOT NULL DEFAULT 0,
  `hits` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=103996 DEFAULT CHARSET=latin1;

-- access_group
CREATE TABLE `access_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL DEFAULT 0,
  `name` char(64) NOT NULL DEFAULT '',
  `info` char(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_id` (`group_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

-- access_module
CREATE TABLE `access_module` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `module` char(128) NOT NULL DEFAULT '',
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `group_id` int(10) unsigned NOT NULL DEFAULT 0,
  `access` int(10) unsigned NOT NULL DEFAULT 420,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module` (`module`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

-- access_user
CREATE TABLE `access_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `group_id` int(10) unsigned NOT NULL DEFAULT 0,
  `timestamp_start` bigint(20) NOT NULL DEFAULT 0,
  `timestamp_update` bigint(20) NOT NULL DEFAULT 0,
  `name` char(64) NOT NULL DEFAULT '',
  `info` char(128) NOT NULL DEFAULT '',
  `passwd` char(64) NOT NULL DEFAULT '',
  `random_key` char(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `name` (`name`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

-- chat
CREATE TABLE `chat` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `remote_addr` varchar(15) NOT NULL DEFAULT '',
  `name` varchar(50) NOT NULL DEFAULT '',
  `content` varchar(255) NOT NULL DEFAULT '',
  `color` varchar(6) NOT NULL DEFAULT 'CCCCCC',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=50560 DEFAULT CHARSET=latin1;

-- chat_history
CREATE TABLE `chat_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `remote_addr` varchar(15) NOT NULL DEFAULT '',
  `name` varchar(50) NOT NULL DEFAULT '',
  `content` varchar(255) NOT NULL DEFAULT '',
  `color` varchar(6) NOT NULL DEFAULT 'CCCCCC',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- client_account
CREATE TABLE `client_account` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client` varchar(32) NOT NULL DEFAULT '',
  `time` bigint(20) unsigned NOT NULL DEFAULT 0,
  `info` blob NOT NULL,
  `sender` varchar(32) NOT NULL DEFAULT '',
  `amount` bigint(20) NOT NULL DEFAULT 0,
  `charge` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

-- client_category
CREATE TABLE `client_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `fixed` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

-- client_person
CREATE TABLE `client_person` (
  `id` varchar(32) NOT NULL DEFAULT '',
  `pin` varchar(32) NOT NULL DEFAULT '',
  `category` int(10) unsigned NOT NULL DEFAULT 0,
  `first_name` varchar(50) NOT NULL DEFAULT '',
  `last_name` varchar(50) NOT NULL DEFAULT '',
  `street` varchar(50) NOT NULL DEFAULT '',
  `state` varchar(50) NOT NULL DEFAULT '',
  `zip` varchar(10) NOT NULL DEFAULT '',
  `city` varchar(50) NOT NULL DEFAULT '',
  `country` char(2) NOT NULL DEFAULT '',
  `birthday` varchar(8) NOT NULL DEFAULT '',
  `phone` varchar(30) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `homepage` varchar(255) NOT NULL DEFAULT '',
  `time` bigint(20) unsigned NOT NULL DEFAULT 0,
  `info` mediumblob NOT NULL,
  `charge` bigint(20) NOT NULL DEFAULT 0,
  `fingerprint` varchar(32) NOT NULL DEFAULT '',
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `category` (`category`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- country
CREATE TABLE `country` (
  `id` char(2) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- gallery
CREATE TABLE `gallery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL DEFAULT 0,
  `submitter` varchar(32) NOT NULL DEFAULT '',
  `password` varchar(32) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `info` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `create_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `edit_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `client` (`submitter`),
  KEY `category` (`category_id`)
) ENGINE=MyISAM AUTO_INCREMENT=45 DEFAULT CHARSET=latin1;

-- gallery_picture
CREATE TABLE `gallery_picture` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gallery_id` int(10) unsigned NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `file` varchar(100) NOT NULL DEFAULT '',
  `copyright` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `gallery` (`gallery_id`)
) ENGINE=MyISAM AUTO_INCREMENT=723 DEFAULT CHARSET=latin1;

-- GAL_Gallery
CREATE TABLE `GAL_Gallery` (
  `GAL_Index` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `GAL_MCG_Index` int(10) unsigned NOT NULL DEFAULT 0,
  `GAL_Submitter` varchar(32) NOT NULL,
  `GAL_Password` varchar(32) NOT NULL,
  `GAL_Title` varchar(100) NOT NULL,
  `GAL_Info` varchar(255) NOT NULL,
  `GAL_Status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `GAL_TimeCreate` bigint(20) unsigned NOT NULL DEFAULT 0,
  `GAL_TimeModify` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`GAL_Index`),
  KEY `client` (`GAL_Submitter`),
  KEY `category` (`GAL_MCG_Index`)
) ENGINE=MyISAM AUTO_INCREMENT=46 DEFAULT CHARSET=latin1;

-- GAP_GalleryPicture
CREATE TABLE `GAP_GalleryPicture` (
  `GAP_Index` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `GAP_GAL_Index` int(10) unsigned NOT NULL DEFAULT 0,
  `GAP_Title` varchar(100) NOT NULL,
  `GAP_File` varchar(100) NOT NULL,
  `GAP_Copyright` varchar(255) NOT NULL,
  PRIMARY KEY (`GAP_Index`),
  KEY `gallery` (`GAP_GAL_Index`)
) ENGINE=MyISAM AUTO_INCREMENT=723 DEFAULT CHARSET=latin1;

-- guestbook
CREATE TABLE `guestbook` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `email` varchar(255) NOT NULL DEFAULT '',
  `submitter` varchar(255) NOT NULL DEFAULT '',
  `content` text NOT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `remote_addr` varchar(15) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `timestamp` (`timestamp`,`status`),
  KEY `remote_addr` (`remote_addr`)
) ENGINE=MyISAM AUTO_INCREMENT=7593 DEFAULT CHARSET=latin1;

-- guestbook_filter
CREATE TABLE `guestbook_filter` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Field` varchar(128) NOT NULL DEFAULT '',
  `Match` varchar(128) NOT NULL DEFAULT '',
  `Case` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=latin1;

-- labels
CREATE TABLE `labels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `home` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `hits` bigint(20) unsigned DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1133 DEFAULT CHARSET=latin1;

-- live_broadcast
CREATE TABLE `live_broadcast` (
  `Id` varchar(16) NOT NULL DEFAULT '',
  `Artist` varchar(64) NOT NULL DEFAULT '',
  `Title` varchar(64) NOT NULL DEFAULT '',
  `Html_LBot` text NOT NULL,
  `Status` tinyint(2) unsigned NOT NULL DEFAULT 0,
  `Station` varchar(16) NOT NULL DEFAULT 'Trance',
  `Fixed` tinyint(2) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- mailinglist
CREATE TABLE `mailinglist` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `timestamp_create` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `timestamp_modify` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `verify` varchar(12) NOT NULL DEFAULT '',
  `status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `group_id` int(10) unsigned NOT NULL DEFAULT 0,
  `access` int(10) unsigned NOT NULL DEFAULT 420,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- MCG_MediaCategory
CREATE TABLE `MCG_MediaCategory` (
  `MCG_Index` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `MCG_Name` varchar(85) NOT NULL,
  `MCG_Path` varchar(85) NOT NULL,
  `MCG_Fixed` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `MCG_Status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `MCG_Flags` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`MCG_Index`),
  KEY `MCG_Status` (`MCG_Status`,`MCG_Flags`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8;

-- media_base
CREATE TABLE `media_base` (
  `Id` char(16) NOT NULL DEFAULT '',
  `Title` char(64) NOT NULL DEFAULT '',
  `Info` char(128) NOT NULL DEFAULT '',
  `File` char(128) NOT NULL DEFAULT '',
  `Author` char(64) NOT NULL DEFAULT '',
  `Copyright` char(64) NOT NULL DEFAULT '',
  `Type` char(4) NOT NULL DEFAULT '',
  PRIMARY KEY (`Id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- media_category
CREATE TABLE `media_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `path` varchar(255) NOT NULL DEFAULT '',
  `fixed` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=latin1;

-- media_playlist
CREATE TABLE `media_playlist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `is_def` tinyint(2) NOT NULL DEFAULT 0,
  `name` char(128) NOT NULL DEFAULT '',
  `title` char(128) NOT NULL DEFAULT '',
  `file` char(128) NOT NULL DEFAULT '',
  `type` char(6) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

-- message_picture
CREATE TABLE `message_picture` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(10) unsigned NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `file` varchar(100) NOT NULL DEFAULT '',
  `copyright` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `gallery` (`message_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

-- message_thread
CREATE TABLE `message_thread` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL DEFAULT 0,
  `submitter` varchar(32) NOT NULL DEFAULT '',
  `password` varchar(32) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `memo` blob NOT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `create_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `edit_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `client` (`submitter`),
  KEY `category` (`category_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

-- mixes
CREATE TABLE `mixes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(10) unsigned NOT NULL DEFAULT 0,
  `dj` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `length` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `name` (`name`),
  FULLTEXT KEY `dj` (`dj`)
) ENGINE=MyISAM AUTO_INCREMENT=890 DEFAULT CHARSET=latin1;

-- news_ticker
CREATE TABLE `news_ticker` (
  `Id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Message` text NOT NULL,
  `Status` tinyint(2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

-- nowplaying
CREATE TABLE `nowplaying` (
  `Id` varchar(32) NOT NULL DEFAULT '',
  `Station` varchar(32) NOT NULL DEFAULT '',
  `Artist` varchar(255) NOT NULL DEFAULT '',
  `Title` varchar(255) NOT NULL DEFAULT '',
  `Track` varchar(255) NOT NULL DEFAULT '',
  `Filename` varchar(255) NOT NULL DEFAULT '',
  `Genre` varchar(255) NOT NULL DEFAULT '',
  `Length` varchar(64) NOT NULL DEFAULT '',
  `Quality` varchar(4) NOT NULL DEFAULT '',
  `Year` varchar(4) NOT NULL DEFAULT '',
  `Album` varchar(255) NOT NULL DEFAULT '',
  `Comment` varchar(255) NOT NULL DEFAULT '',
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`Id`),
  KEY `Station` (`Station`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- our_music
CREATE TABLE `our_music` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `time` bigint(20) unsigned NOT NULL DEFAULT 0,
  `length` int(10) unsigned NOT NULL DEFAULT 0,
  `file` varchar(255) NOT NULL DEFAULT '',
  `quality` int(10) unsigned NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `info` varchar(100) NOT NULL DEFAULT '',
  `mixedby` varchar(100) NOT NULL DEFAULT '',
  `style` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=43 DEFAULT CHARSET=latin1;

-- party
CREATE TABLE `party` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `start_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `end_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `type` varchar(20) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `djs` text NOT NULL,
  `live` text NOT NULL,
  `chillout` text NOT NULL,
  `deco` text NOT NULL,
  `location` text NOT NULL,
  `hotline` varchar(100) NOT NULL DEFAULT '',
  `organizer` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `url` varchar(100) NOT NULL DEFAULT 'http://',
  `memo` text NOT NULL,
  `submitter` varchar(32) NOT NULL DEFAULT '',
  `create_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `edit_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `flyer` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(32) NOT NULL DEFAULT '',
  `source_url` varchar(255) NOT NULL DEFAULT '',
  `source_name` varchar(100) NOT NULL DEFAULT '',
  `source_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `start_timestamp` (`start_timestamp`),
  KEY `end_timestamp` (`end_timestamp`),
  KEY `status` (`status`),
  KEY `source_id` (`source_id`),
  FULLTEXT KEY `title` (`title`),
  FULLTEXT KEY `country` (`country`),
  FULLTEXT KEY `memo` (`memo`),
  FULLTEXT KEY `chillout` (`chillout`),
  FULLTEXT KEY `live` (`live`),
  FULLTEXT KEY `djs` (`djs`),
  FULLTEXT KEY `submiter` (`submitter`),
  FULLTEXT KEY `url` (`url`),
  FULLTEXT KEY `email` (`email`),
  FULLTEXT KEY `organizer` (`organizer`),
  FULLTEXT KEY `location` (`location`),
  FULLTEXT KEY `type` (`type`)
) ENGINE=MyISAM AUTO_INCREMENT=7460 DEFAULT CHARSET=latin1;

-- party_comment
CREATE TABLE `party_comment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `party_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `create_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `submitter` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `info` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `party_id` (`party_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- party_comment_1
CREATE TABLE `party_comment_1` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `party_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `create_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `submitter` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `info` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `party_id` (`party_id`)
) ENGINE=MyISAM AUTO_INCREMENT=42550 DEFAULT CHARSET=latin1;

-- party_sav
CREATE TABLE `party_sav` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `start_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `end_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `type` varchar(20) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `djs` varchar(255) NOT NULL DEFAULT '',
  `live` varchar(255) NOT NULL DEFAULT '',
  `chillout` varchar(255) NOT NULL DEFAULT '',
  `deco` varchar(255) NOT NULL DEFAULT '',
  `location` varchar(255) NOT NULL DEFAULT '',
  `hotline` varchar(100) NOT NULL DEFAULT '',
  `organizer` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `url` varchar(100) NOT NULL DEFAULT 'http://',
  `memo` varchar(255) NOT NULL DEFAULT '',
  `submitter` varchar(32) NOT NULL DEFAULT '',
  `create_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `edit_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `flyer` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(32) NOT NULL DEFAULT '',
  `source_url` varchar(255) NOT NULL DEFAULT '',
  `source_name` varchar(100) NOT NULL DEFAULT '',
  `source_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `start_timestamp` (`start_timestamp`),
  KEY `end_timestamp` (`end_timestamp`),
  KEY `status` (`status`),
  KEY `source_id` (`source_id`),
  FULLTEXT KEY `title` (`title`),
  FULLTEXT KEY `country` (`country`),
  FULLTEXT KEY `memo` (`memo`),
  FULLTEXT KEY `chillout` (`chillout`),
  FULLTEXT KEY `live` (`live`),
  FULLTEXT KEY `djs` (`djs`),
  FULLTEXT KEY `submiter` (`submitter`),
  FULLTEXT KEY `url` (`url`),
  FULLTEXT KEY `email` (`email`),
  FULLTEXT KEY `organizer` (`organizer`),
  FULLTEXT KEY `location` (`location`),
  FULLTEXT KEY `type` (`type`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=latin1;

-- rating_category
CREATE TABLE `rating_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `fixed` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

-- rating_object
CREATE TABLE `rating_object` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(10) unsigned NOT NULL DEFAULT 0,
  `object` bigint(20) unsigned NOT NULL DEFAULT 0,
  `choice_1` int(10) unsigned NOT NULL DEFAULT 0,
  `choice_2` int(10) unsigned NOT NULL DEFAULT 0,
  `choice_3` int(10) unsigned NOT NULL DEFAULT 0,
  `choice_4` int(10) unsigned NOT NULL DEFAULT 0,
  `choice_5` int(10) unsigned NOT NULL DEFAULT 0,
  `choice_6` int(10) unsigned NOT NULL DEFAULT 0,
  `result` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- referer
CREATE TABLE `referer` (
  `url` varchar(255) NOT NULL DEFAULT '',
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`url`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- releases
CREATE TABLE `releases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mix` bigint(20) unsigned NOT NULL DEFAULT 0,
  `type` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `label` int(10) unsigned NOT NULL DEFAULT 0,
  `url_order` varchar(255) DEFAULT NULL,
  `pos` int(10) unsigned NOT NULL DEFAULT 0,
  `hits` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `mix` (`mix`),
  FULLTEXT KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=5461 DEFAULT CHARSET=latin1;

-- release_types
CREATE TABLE `release_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

-- runtime
CREATE TABLE `runtime` (
  `Name` varchar(64) NOT NULL DEFAULT '',
  `Value` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`Name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- settings
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `parent` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `variable` varchar(255) NOT NULL DEFAULT '',
  `value` varchar(255) NOT NULL DEFAULT '',
  `default_value` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(50) NOT NULL DEFAULT 'tagout',
  `choice` varchar(255) NOT NULL DEFAULT '',
  `required` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`),
  KEY `TYPE` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- thread_category
CREATE TABLE `thread_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `fixed` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

-- thread_entry
CREATE TABLE `thread_entry` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent` bigint(20) unsigned NOT NULL DEFAULT 0,
  `category` int(10) unsigned NOT NULL DEFAULT 0,
  `client` bigint(20) unsigned NOT NULL DEFAULT 0,
  `time` bigint(20) unsigned NOT NULL DEFAULT 0,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `message` mediumblob NOT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- ticker
CREATE TABLE `ticker` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `content` blob NOT NULL,
  `image` varchar(255) NOT NULL DEFAULT '',
  `show_image` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `bg_color` varchar(6) NOT NULL DEFAULT '100040',
  `delay` int(10) unsigned NOT NULL DEFAULT 15,
  `title_font_family` varchar(100) NOT NULL DEFAULT '',
  `title_font_size` varchar(10) NOT NULL DEFAULT '',
  `title_color` varchar(6) NOT NULL DEFAULT '',
  `show_title` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `content_font_family` varchar(100) NOT NULL DEFAULT 'Verdana, Arial, mono',
  `content_font_size` varchar(10) NOT NULL DEFAULT '10px',
  `content_color` varchar(6) NOT NULL DEFAULT 'FFEEDD',
  `anchor_color` varchar(6) NOT NULL DEFAULT 'CCFF00',
  `create_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `edit_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `border_horizontal` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `border_vertical` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `object_type` enum('n','f') NOT NULL DEFAULT 'n',
  `flash_movie` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=384 DEFAULT CHARSET=latin1;

-- tz_rules
CREATE TABLE `tz_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` char(16) NOT NULL DEFAULT '',
  `from` char(32) NOT NULL DEFAULT '',
  `to` char(32) NOT NULL DEFAULT '',
  `type` char(1) NOT NULL DEFAULT '',
  `in` char(32) NOT NULL DEFAULT '',
  `on` char(64) NOT NULL DEFAULT '',
  `at` char(6) NOT NULL DEFAULT '',
  `save` int(11) NOT NULL DEFAULT 0,
  `letter` char(4) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1607 DEFAULT CHARSET=latin1;

-- tz_zones
CREATE TABLE `tz_zones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` char(128) NOT NULL DEFAULT '',
  `gmtoff` int(11) NOT NULL DEFAULT 0,
  `rules` char(64) NOT NULL DEFAULT '',
  `format` char(64) NOT NULL DEFAULT '',
  `until` char(64) NOT NULL DEFAULT '',
  `is_def` tinyint(2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `is_def` (`is_def`)
) ENGINE=MyISAM AUTO_INCREMENT=1053 DEFAULT CHARSET=latin1;

-- user_info
CREATE TABLE `user_info` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `title` char(32) NOT NULL DEFAULT '',
  `name` char(50) NOT NULL DEFAULT '',
  `surname` char(50) NOT NULL DEFAULT '',
  `street` char(50) NOT NULL DEFAULT '',
  `state` char(50) NOT NULL DEFAULT '',
  `zip` char(10) NOT NULL DEFAULT '',
  `city` char(50) NOT NULL DEFAULT '',
  `country` char(2) NOT NULL DEFAULT '',
  `birthday` char(10) NOT NULL DEFAULT '',
  `phone` char(32) NOT NULL DEFAULT '',
  `handy` char(32) NOT NULL DEFAULT '',
  `email` char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

