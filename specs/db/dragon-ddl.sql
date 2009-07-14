-- phpMyAdmin SQL Dump
-- version 2.11.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jul 14, 2009 at 07:05 PM
-- Server version: 5.0.45
-- PHP Version: 5.1.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `dragondb`
--
-- CREATE DATABASE `dragondb` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
-- USE `dragondb`;

-- --------------------------------------------------------

--
-- Table structure for table `Adminlog`
--

CREATE TABLE IF NOT EXISTS `Adminlog` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `Handle` varchar(16) NOT NULL default '',
  `Message` text NOT NULL,
  `Date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `IP` varchar(16) NOT NULL default '',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Bio`
--

CREATE TABLE IF NOT EXISTS `Bio` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `Category` varchar(40) NOT NULL default '',
  `Text` text NOT NULL,
  `SortOrder` int(11) NOT NULL default '0',
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Clock`
--

CREATE TABLE IF NOT EXISTS `Clock` (
  `ID` int(11) NOT NULL default '0',
  `Ticks` int(11) default '0',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Contacts`
--

CREATE TABLE IF NOT EXISTS `Contacts` (
  `uid` int(11) NOT NULL default '0',
  `cid` int(11) NOT NULL default '0',
  `SystemFlags` smallint(5) unsigned NOT NULL default '0',
  `UserFlags` int(10) unsigned NOT NULL default '0',
  `Created` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `Notes` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`uid`,`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Errorlog`
--

CREATE TABLE IF NOT EXISTS `Errorlog` (
  `ID` int(11) NOT NULL auto_increment,
  `Handle` varchar(16) NOT NULL default '',
  `Message` text NOT NULL,
  `MysqlError` text NOT NULL,
  `Debug` text NOT NULL,
  `Date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `IP` varchar(16) NOT NULL default '',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `FAQ`
--

CREATE TABLE IF NOT EXISTS `FAQ` (
  `ID` int(11) NOT NULL auto_increment,
  `Parent` int(11) NOT NULL default '0',
  `Level` int(11) NOT NULL default '0',
  `SortOrder` int(11) NOT NULL default '0',
  `Question` int(11) NOT NULL default '0',
  `Answer` int(11) NOT NULL default '0',
  `Hidden` enum('N','Y') NOT NULL default 'N',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `FAQlog`
--

CREATE TABLE IF NOT EXISTS `FAQlog` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `FAQID` int(11) NOT NULL default '0',
  `Question` text,
  `Answer` text,
  `Date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Folders`
--

CREATE TABLE IF NOT EXISTS `Folders` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `Folder_nr` int(11) NOT NULL default '0',
  `Name` varchar(40) NOT NULL default '',
  `BGColor` varchar(8) NOT NULL default 'f7f5e3FF',
  `FGColor` varchar(6) NOT NULL default '000000',
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`,`Folder_nr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Forumlog`
--

CREATE TABLE IF NOT EXISTS `Forumlog` (
  `ID` int(11) NOT NULL auto_increment,
  `User_ID` int(11) NOT NULL default '0',
  `Thread_ID` int(11) NOT NULL default '0',
  `Post_ID` int(11) NOT NULL default '0',
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `Action` varchar(40) NOT NULL default '',
  `IP` varchar(16) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  KEY `User_ID` (`User_ID`),
  KEY `Time` (`Time`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Forumreads`
--

CREATE TABLE IF NOT EXISTS `Forumreads` (
  `User_ID` int(11) NOT NULL default '0',
  `Thread_ID` int(11) NOT NULL default '0',
  `Time` datetime default NULL,
  PRIMARY KEY  (`User_ID`,`Thread_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Forums`
--

CREATE TABLE IF NOT EXISTS `Forums` (
  `ID` int(11) NOT NULL auto_increment,
  `Name` varchar(40) default NULL,
  `Description` varchar(255) default NULL,
  `SortOrder` int(11) NOT NULL default '0',
  `Options` int(11) unsigned NOT NULL default '0',
  `LastPost` int(11) default NULL,
  `PostsInForum` int(11) NOT NULL default '0',
  PRIMARY KEY  (`ID`),
  KEY `SortOrder` (`SortOrder`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Games`
--

CREATE TABLE IF NOT EXISTS `Games` (
  `ID` int(11) NOT NULL auto_increment,
  `Starttime` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `mid` int(11) NOT NULL default '0',
  `Black_ID` int(11) NOT NULL default '0',
  `White_ID` int(11) NOT NULL default '0',
  `ToMove_ID` int(11) NOT NULL default '0',
  `Size` int(11) NOT NULL default '19',
  `Komi` decimal(6,1) NOT NULL default '6.5',
  `Handicap` int(11) NOT NULL default '0',
  `Status` enum('INVITED','PLAY','PASS','SCORE','SCORE2','FINISHED') NOT NULL default 'INVITED',
  `Moves` int(11) NOT NULL default '0',
  `Black_Prisoners` int(11) NOT NULL default '0',
  `White_Prisoners` int(11) NOT NULL default '0',
  `Last_X` int(11) NOT NULL default '-1',
  `Last_Y` int(11) NOT NULL default '-1',
  `Last_Move` char(2) NOT NULL default '',
  `Flags` set('Ko') NOT NULL default '',
  `Score` decimal(7,1) NOT NULL default '0.0',
  `Maintime` int(11) NOT NULL default '0',
  `Byotype` enum('JAP','CAN','FIS') NOT NULL default 'JAP',
  `Byotime` int(11) NOT NULL default '0',
  `Byoperiods` int(11) NOT NULL default '0',
  `Black_Maintime` int(11) NOT NULL default '0',
  `White_Maintime` int(11) NOT NULL default '0',
  `Black_Byotime` int(11) NOT NULL default '0',
  `White_Byotime` int(11) NOT NULL default '0',
  `Black_Byoperiods` int(11) NOT NULL default '-1',
  `White_Byoperiods` int(11) NOT NULL default '-1',
  `LastTicks` int(11) NOT NULL default '0',
  `ClockUsed` int(11) NOT NULL default '0',
  `Rated` enum('N','Y','Done') NOT NULL default 'N',
  `StdHandicap` enum('N','Y') NOT NULL default 'N',
  `WeekendClock` enum('N','Y') NOT NULL default 'Y',
  `Black_Start_Rating` double NOT NULL default '-9999',
  `White_Start_Rating` double NOT NULL default '-9999',
  `Black_End_Rating` double NOT NULL default '-9999',
  `White_End_Rating` double NOT NULL default '-9999',
  PRIMARY KEY  (`ID`),
  KEY `ToMove_ID` (`ToMove_ID`),
  KEY `Size` (`Size`),
  KEY `Lastchanged` (`Lastchanged`),
  KEY `Status` (`Status`),
  KEY `ClockUsed` (`ClockUsed`),
  KEY `Maintime` (`Maintime`),
  KEY `Byotime` (`Byotime`),
  KEY `Black_ID` (`Black_ID`),
  KEY `White_ID` (`White_ID`),
  KEY `Handicap` (`Handicap`),
  KEY `Moves` (`Moves`),
  KEY `Score` (`Score`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `GamesNotes`
--

CREATE TABLE IF NOT EXISTS `GamesNotes` (
  `ID` int(11) unsigned NOT NULL auto_increment,
  `gid` int(11) NOT NULL default '0',
  `player` enum('B','W') NOT NULL default 'B',
  `Hidden` enum('N','Y') NOT NULL default 'N',
  `Notes` text NOT NULL,
  PRIMARY KEY  (`gid`,`player`),
  KEY `ID` (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `GoDiagrams`
--

CREATE TABLE IF NOT EXISTS `GoDiagrams` (
  `ID` int(11) NOT NULL auto_increment,
  `Size` int(11) default NULL,
  `View_Left` int(11) default NULL,
  `View_Right` int(11) default NULL,
  `View_Up` int(11) default NULL,
  `View_Down` int(11) default NULL,
  `Date` datetime NOT NULL default '0000-00-00 00:00:00',
  `Saved` enum('Y','N') NOT NULL default 'N',
  `Data` text NOT NULL,
  `SGF` text NOT NULL,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `MessageCorrespondents`
--

CREATE TABLE IF NOT EXISTS `MessageCorrespondents` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `mid` int(11) NOT NULL default '0',
  `Folder_nr` int(11) default NULL,
  `Sender` enum('M','N','Y','S') NOT NULL default 'Y',
  `Replied` enum('M','N','Y') NOT NULL default 'N',
  PRIMARY KEY  (`ID`),
  KEY `mid` (`mid`),
  KEY `uid` (`uid`),
  KEY `Folder_nr` (`Folder_nr`,`uid`),
  KEY `Sender` (`Sender`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Messages`
--

CREATE TABLE IF NOT EXISTS `Messages` (
  `ID` int(11) NOT NULL auto_increment,
  `Type` enum('NORMAL','INVITATION','ACCEPTED','DECLINED','DELETED','DISPUTED','RESULT') NOT NULL default 'NORMAL',
  `ReplyTo` int(11) NOT NULL default '0',
  `Game_ID` int(11) NOT NULL default '0',
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `Subject` varchar(80) NOT NULL default '',
  `Text` text NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `ReplyTo` (`ReplyTo`),
  FULLTEXT KEY `Subject` (`Subject`,`Text`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `MoveMessages`
--

CREATE TABLE IF NOT EXISTS `MoveMessages` (
  `gid` int(11) NOT NULL,
  `MoveNr` smallint(5) unsigned NOT NULL default '0',
  `Text` text,
  PRIMARY KEY  (`gid`,`MoveNr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Moves`
--

CREATE TABLE IF NOT EXISTS `Moves` (
  `ID` int(11) NOT NULL auto_increment,
  `gid` int(11) NOT NULL default '0',
  `MoveNr` smallint(5) unsigned default NULL,
  `Stone` smallint(5) unsigned NOT NULL default '0',
  `PosX` smallint(6) default NULL,
  `PosY` smallint(6) default NULL,
  `Hours` smallint(5) unsigned default NULL,
  PRIMARY KEY  (`ID`),
  KEY `gid` (`gid`,`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Observers`
--

CREATE TABLE IF NOT EXISTS `Observers` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) default NULL,
  `gid` int(11) default NULL,
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`),
  KEY `gid` (`gid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Players`
--

CREATE TABLE IF NOT EXISTS `Players` (
  `ID` int(11) NOT NULL auto_increment,
  `Handle` varchar(16) NOT NULL default '',
  `Password` varchar(41) NOT NULL default '',
  `Newpassword` varchar(41) NOT NULL default '',
  `Sessioncode` varchar(41) NOT NULL default '',
  `Sessionexpire` datetime default NULL,
  `Lastaccess` datetime default NULL,
  `LastMove` datetime default NULL,
  `Registerdate` date default NULL,
  `Hits` int(11) NOT NULL default '0',
  `VaultCnt` smallint(5) unsigned NOT NULL default '0',
  `VaultTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `Moves` int(11) default '0',
  `Activity` double NOT NULL default '15',
  `Name` varchar(40) NOT NULL default '',
  `Email` varchar(80) default NULL,
  `Rank` varchar(40) default NULL,
  `Stonesize` tinyint(3) unsigned NOT NULL default '25',
  `SendEmail` set('ON','MOVE','BOARD','MESSAGE') NOT NULL default '',
  `Notify` enum('NONE','NEXT','NOW','DONE') NOT NULL default 'NONE',
  `MenuDirection` enum('VERTICAL','HORIZONTAL') NOT NULL default 'VERTICAL',
  `Adminlevel` int(11) NOT NULL default '0',
  `AdminOptions` int(11) unsigned NOT NULL default '0',
  `AdminNote` varchar(100) NOT NULL default '',
  `MayPostOnForum` enum('N','Y','M') NOT NULL default 'Y',
  `Timezone` varchar(40) NOT NULL default 'GMT',
  `Nightstart` int(11) NOT NULL default '22',
  `ClockUsed` int(11) NOT NULL default '22',
  `ClockChanged` enum('N','Y') NOT NULL default 'Y',
  `Rating` double default NULL,
  `Rating2` double default NULL,
  `RatingMin` double default NULL,
  `RatingMax` double default NULL,
  `InitialRating` double default NULL,
  `RatingStatus` enum('INIT','RATED') default NULL,
  `Open` varchar(40) NOT NULL default '',
  `Lang` varchar(20) NOT NULL default 'C',
  `VacationDays` double NOT NULL default '14',
  `OnVacation` double NOT NULL default '0',
  `SkinName` varchar(32) NOT NULL default '',
  `Woodcolor` int(11) NOT NULL default '1',
  `Boardcoords` int(11) NOT NULL default '31',
  `MoveNumbers` smallint(5) unsigned NOT NULL default '0',
  `MoveModulo` smallint(5) unsigned NOT NULL default '0',
  `Button` int(11) NOT NULL default '0',
  `UsersColumns` int(10) unsigned NOT NULL default '62',
  `GamesColumns` int(10) unsigned NOT NULL default '593910',
  `RunningGamesColumns` int(10) unsigned NOT NULL default '593910',
  `FinishedGamesColumns` int(10) unsigned NOT NULL default '593910',
  `ObservedGamesColumns` int(10) unsigned NOT NULL default '593910',
  `TournamentsColumns` int(10) unsigned NOT NULL default '62',
  `WaitingroomColumns` int(10) unsigned NOT NULL default '253',
  `ContactColumns` int(10) unsigned NOT NULL default '225',
  `Running` int(11) NOT NULL default '0',
  `Finished` int(11) NOT NULL default '0',
  `RatedGames` int(11) NOT NULL default '0',
  `Won` int(11) NOT NULL default '0',
  `Lost` int(11) NOT NULL default '0',
  `Translator` varchar(80) NOT NULL default '',
  `StatusFolders` varchar(40) NOT NULL default '',
  `IP` varchar(16) NOT NULL default '',
  `Browser` varchar(100) NOT NULL default '',
  `Country` char(2) NOT NULL default '',
  `NotesSmallHeight` tinyint(3) unsigned NOT NULL default '25',
  `NotesSmallWidth` tinyint(3) unsigned NOT NULL default '30',
  `NotesSmallMode` enum('RIGHT','BELOW','RIGHTOFF','BELOWOFF') NOT NULL default 'RIGHT',
  `NotesLargeHeight` tinyint(3) unsigned NOT NULL default '25',
  `NotesLargeWidth` tinyint(3) unsigned NOT NULL default '30',
  `NotesLargeMode` enum('RIGHT','BELOW','RIGHTOFF','BELOWOFF') NOT NULL default 'RIGHT',
  `NotesCutoff` tinyint(3) unsigned NOT NULL default '13',
  `TableMaxRows` smallint(5) unsigned NOT NULL default '20',
  `BlockReason` text NOT NULL,
  PRIMARY KEY  (`ID`),
  UNIQUE KEY `Handle` (`Handle`),
  KEY `Rating2` (`Rating2`),
  KEY `Name` (`Name`),
  KEY `Activity` (`Activity`),
  KEY `Country` (`Country`),
  KEY `Lastaccess` (`Lastaccess`),
  KEY `Adminlevel` (`Adminlevel`),
  KEY `AdminOptions` (`AdminOptions`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Posts`
--

CREATE TABLE IF NOT EXISTS `Posts` (
  `ID` int(11) NOT NULL auto_increment,
  `Forum_ID` int(11) NOT NULL default '0',
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastedited` datetime NOT NULL default '0000-00-00 00:00:00',
  `Subject` varchar(80) NOT NULL default '',
  `Text` text NOT NULL,
  `User_ID` int(11) NOT NULL default '0',
  `Parent_ID` int(11) NOT NULL default '0',
  `Thread_ID` int(11) NOT NULL default '0',
  `AnswerNr` int(11) NOT NULL default '0',
  `Depth` int(11) NOT NULL default '0',
  `crc32` int(11) default NULL,
  `PosIndex` varchar(80) character set latin1 collate latin1_bin NOT NULL default '',
  `old_ID` int(11) NOT NULL default '0',
  `Approved` enum('Y','N') NOT NULL default 'Y',
  `PostsInThread` int(11) NOT NULL default '0',
  `LastPost` int(11) NOT NULL default '0',
  `PendingApproval` enum('Y','N') NOT NULL default 'N',
  PRIMARY KEY  (`ID`),
  KEY `PendingApproval` (`PendingApproval`,`Time`),
  KEY `Thread_ID` (`Thread_ID`),
  KEY `PosIndex` (`PosIndex`),
  KEY `Forum_ID` (`Forum_ID`),
  KEY `Lastchanged` (`Lastchanged`),
  KEY `Parent_ID` (`Parent_ID`),
  KEY `Time` (`Time`),
  FULLTEXT KEY `Subject` (`Subject`,`Text`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `RatingChange`
--

CREATE TABLE IF NOT EXISTS `RatingChange` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `gid` int(11) NOT NULL default '0',
  `diff` double default NULL,
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Ratinglog`
--

CREATE TABLE IF NOT EXISTS `Ratinglog` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `gid` int(11) NOT NULL default '0',
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `Rating` double default NULL,
  `RatingMin` double default NULL,
  `RatingMax` double default NULL,
  `RatingDiff` double default NULL,
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`),
  KEY `gid` (`gid`,`uid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Statistics`
--

CREATE TABLE IF NOT EXISTS `Statistics` (
  `ID` int(11) NOT NULL auto_increment,
  `Time` datetime default NULL,
  `Hits` int(11) default NULL,
  `Users` int(11) default NULL,
  `Moves` int(11) default NULL,
  `MovesFinished` int(11) default NULL,
  `MovesRunning` int(11) default NULL,
  `Games` int(11) default NULL,
  `GamesFinished` int(11) default NULL,
  `GamesRunning` int(11) default NULL,
  `Activity` int(11) default NULL,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationFoundInGroup`
--

CREATE TABLE IF NOT EXISTS `TranslationFoundInGroup` (
  `Text_ID` int(11) NOT NULL default '0',
  `Group_ID` int(11) NOT NULL default '0',
  PRIMARY KEY  (`Text_ID`,`Group_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationGroups`
--

CREATE TABLE IF NOT EXISTS `TranslationGroups` (
  `ID` int(11) NOT NULL auto_increment,
  `Groupname` varchar(32) NOT NULL default '',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationLanguages`
--

CREATE TABLE IF NOT EXISTS `TranslationLanguages` (
  `ID` int(11) NOT NULL auto_increment,
  `Language` varchar(32) default NULL,
  `Name` varchar(32) default NULL,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationPages`
--

CREATE TABLE IF NOT EXISTS `TranslationPages` (
  `ID` int(11) NOT NULL auto_increment,
  `Page` varchar(64) default NULL,
  `Group_ID` int(11) default NULL,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationTexts`
--

CREATE TABLE IF NOT EXISTS `TranslationTexts` (
  `ID` int(11) NOT NULL auto_increment,
  `Text` text NOT NULL,
  `Ref_ID` int(11) default NULL,
  `Translatable` enum('Y','N','Done','Changed') NOT NULL default 'Y',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Translationlog`
--

CREATE TABLE IF NOT EXISTS `Translationlog` (
  `ID` int(11) NOT NULL auto_increment,
  `Player_ID` int(11) default NULL,
  `Language_ID` int(11) default NULL,
  `Original_ID` int(11) default NULL,
  `Handle` varchar(16) default NULL,
  `Language` varchar(16) default NULL,
  `CString` text NOT NULL,
  `OldTranslation` blob NOT NULL,
  `Translation` blob NOT NULL,
  `Date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Translations`
--

CREATE TABLE IF NOT EXISTS `Translations` (
  `Original_ID` int(11) NOT NULL default '0',
  `Language_ID` int(11) NOT NULL default '0',
  `Text` blob NOT NULL,
  `Translated` enum('Y','N') NOT NULL default 'N',
  PRIMARY KEY  (`Language_ID`,`Original_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Waitingroom`
--

CREATE TABLE IF NOT EXISTS `Waitingroom` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `nrGames` int(11) NOT NULL default '1',
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `Size` int(11) NOT NULL default '19',
  `Komi` decimal(6,1) NOT NULL default '6.5',
  `Handicap` int(11) NOT NULL default '0',
  `Handicaptype` enum('conv','proper','nigiri','double') NOT NULL default 'conv',
  `Maintime` int(11) NOT NULL default '0',
  `Byotype` enum('JAP','CAN','FIS') NOT NULL default 'JAP',
  `Byotime` int(11) NOT NULL default '0',
  `Byoperiods` int(11) NOT NULL default '0',
  `Rated` enum('N','Y','Done') NOT NULL default 'N',
  `StdHandicap` enum('N','Y') NOT NULL default 'N',
  `WeekendClock` enum('N','Y') NOT NULL default 'Y',
  `MustBeRated` enum('N','Y','Done') NOT NULL default 'N',
  `Ratingmin` double NOT NULL default '-9999',
  `Ratingmax` double NOT NULL default '-9999',
  `Comment` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

