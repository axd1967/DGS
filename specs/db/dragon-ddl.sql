-- phpMyAdmin SQL Dump
-- version 2.11.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jun 10, 2012 at 12:59 PM
-- Server version: 5.0.95
-- PHP Version: 5.1.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- NOTE: Database-name can be named whatever you like!
--
--
-- Database: `dragon`
--
-- CREATE DATABASE `dragon` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
-- USE `dragon`;

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
  `uid` int(11) NOT NULL,
  `Category` varchar(40) NOT NULL,
  `Text` text NOT NULL,
  `SortOrder` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`,`SortOrder`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Bulletin`
--

CREATE TABLE IF NOT EXISTS `Bulletin` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL,
  `LockVersion` tinyint(3) unsigned NOT NULL default '0',
  `Category` enum('MAINT','ADM_MSG','TOURNEY','TNEWS','FEATURE','PRIV_MSG','AD') NOT NULL default 'PRIV_MSG',
  `Status` enum('NEW','PENDING','REJECTED','SHOW','ARCHIVE','DELETE') NOT NULL default 'NEW',
  `TargetType` enum('ALL','TD','TP','UL','MPG') NOT NULL,
  `Flags` tinyint(3) unsigned NOT NULL default '0',
  `PublishTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `ExpireTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `tid` int(11) NOT NULL default '0',
  `gid` int(11) NOT NULL default '0',
  `CountReads` mediumint(8) unsigned NOT NULL default '0',
  `AdminNote` varchar(255) NOT NULL default '',
  `Subject` varchar(255) NOT NULL,
  `Text` text NOT NULL,
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`ID`),
  KEY `Status` (`Status`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `BulletinRead`
--

CREATE TABLE IF NOT EXISTS `BulletinRead` (
  `bid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  PRIMARY KEY  (`bid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `BulletinTarget`
--

CREATE TABLE IF NOT EXISTS `BulletinTarget` (
  `bid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  PRIMARY KEY  (`bid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Clock`
--

CREATE TABLE IF NOT EXISTS `Clock` (
  `ID` smallint(6) NOT NULL,
  `Ticks` int(11) NOT NULL default '0',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `Finished` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ConfigBoard`
--

CREATE TABLE IF NOT EXISTS `ConfigBoard` (
  `User_ID` int(11) NOT NULL,
  `Stonesize` tinyint(3) unsigned NOT NULL default '25',
  `Woodcolor` tinyint(3) unsigned NOT NULL default '1',
  `BoardFlags` tinyint(3) unsigned NOT NULL default '0',
  `Boardcoords` smallint(5) unsigned NOT NULL default '31',
  `MoveNumbers` smallint(5) unsigned NOT NULL default '0',
  `MoveModulo` smallint(5) unsigned NOT NULL default '0',
  `NotesSmallHeight` tinyint(3) unsigned NOT NULL default '25',
  `NotesSmallWidth` tinyint(3) unsigned NOT NULL default '30',
  `NotesSmallMode` enum('RIGHT','BELOW','RIGHTOFF','BELOWOFF') NOT NULL default 'RIGHT',
  `NotesLargeHeight` tinyint(3) unsigned NOT NULL default '25',
  `NotesLargeWidth` tinyint(3) unsigned NOT NULL default '30',
  `NotesLargeMode` enum('RIGHT','BELOW','RIGHTOFF','BELOWOFF') NOT NULL default 'RIGHT',
  `NotesCutoff` tinyint(3) unsigned NOT NULL default '13',
  PRIMARY KEY  (`User_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ConfigPages`
--

CREATE TABLE IF NOT EXISTS `ConfigPages` (
  `User_ID` int(11) NOT NULL,
  `StatusFlags` smallint(6) NOT NULL default '3',
  `StatusFolders` char(40) NOT NULL default '',
  `ForumFlags` tinyint(3) unsigned NOT NULL default '8',
  `ColumnsStatusGames` int(11) NOT NULL default '-1',
  `ColumnsStatusTournaments` int(11) NOT NULL default '-1',
  `ColumnsWaitingroom` int(11) NOT NULL default '-1',
  `ColumnsUsers` int(11) NOT NULL default '-1',
  `ColumnsOpponents` int(11) NOT NULL default '-1',
  `ColumnsContacts` int(11) NOT NULL default '-1',
  `ColumnsGamesRunningAll` int(11) NOT NULL default '-1',
  `ColumnsGamesRunningAll2` int(11) NOT NULL default '-1',
  `ColumnsGamesRunningUser` int(11) NOT NULL default '-1',
  `ColumnsGamesRunningUser2` int(11) NOT NULL default '-1',
  `ColumnsGamesFinishedAll` int(11) NOT NULL default '-1',
  `ColumnsGamesFinishedAll2` int(11) NOT NULL default '-1',
  `ColumnsGamesFinishedUser` int(11) NOT NULL default '-1',
  `ColumnsGamesFinishedUser2` int(11) NOT NULL default '-1',
  `ColumnsGamesObserved` int(11) NOT NULL default '-1',
  `ColumnsGamesObserved2` int(11) NOT NULL default '-1',
  `ColumnsGamesObservedAll` int(11) NOT NULL default '-1',
  `ColumnsGamesObservedAll2` int(11) NOT NULL default '-1',
  `ColumnsBulletinList` int(11) NOT NULL default '-1',
  `ColumnsFeatureList` int(11) NOT NULL default '-1',
  `ColumnsTournaments` int(11) NOT NULL default '-1',
  `ColumnsTournamentParticipants` int(11) NOT NULL default '-1',
  `ColumnsTDTournamentParticipants` int(11) NOT NULL default '-1',
  `ColumnsTournamentResults` int(11) NOT NULL default '-1',
  `ColumnsTournamentLadderView` int(11) NOT NULL default '-1',
  `ColumnsTournamentPoolView` int(11) NOT NULL default '-1',
  PRIMARY KEY  (`User_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Contacts`
--

CREATE TABLE IF NOT EXISTS `Contacts` (
  `uid` int(11) NOT NULL,
  `cid` int(11) NOT NULL,
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
  `uid` int(11) NOT NULL default '0',
  `Handle` varchar(16) NOT NULL default '',
  `Message` varchar(32) NOT NULL,
  `Request` varchar(128) NOT NULL default '',
  `MysqlError` text NOT NULL,
  `Debug` text NOT NULL,
  `Date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `IP` varchar(16) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  KEY `Message` (`Message`(8)),
  KEY `Date` (`Date`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `FAQ`
--

CREATE TABLE IF NOT EXISTS `FAQ` (
  `ID` int(11) NOT NULL auto_increment,
  `Parent` int(11) NOT NULL default '0',
  `Level` tinyint(3) unsigned NOT NULL default '0',
  `SortOrder` smallint(6) NOT NULL default '0',
  `Question` int(11) NOT NULL default '0',
  `Answer` int(11) NOT NULL default '0',
  `Hidden` enum('N','Y') NOT NULL default 'N',
  `Reference` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  KEY `Parent` (`Parent`),
  KEY `Level` (`Level`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `FAQlog`
--

CREATE TABLE IF NOT EXISTS `FAQlog` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `Ref_ID` int(11) NOT NULL default '0',
  `Type` enum('FAQ','Links','Intro') NOT NULL default 'FAQ',
  `Question` text NOT NULL,
  `Answer` text NOT NULL,
  `Reference` varchar(255) NOT NULL default '',
  `Date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Feature`
--

CREATE TABLE IF NOT EXISTS `Feature` (
  `ID` int(11) NOT NULL auto_increment,
  `Status` enum('NEW','VOTE','WORK','DONE','LIVE','NACK') NOT NULL default 'NEW',
  `Size` enum('?','EPIC','XXL','XL','L','M','S') NOT NULL default '?',
  `Subject` varchar(255) NOT NULL,
  `Description` text NOT NULL,
  `Editor_ID` int(11) NOT NULL,
  `Created` datetime NOT NULL,
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`ID`),
  KEY `Status` (`Status`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `FeatureVote`
--

CREATE TABLE IF NOT EXISTS `FeatureVote` (
  `fid` int(11) NOT NULL,
  `Voter_ID` int(11) NOT NULL,
  `Points` tinyint(4) NOT NULL default '0',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`fid`,`Voter_ID`),
  KEY `Voter_ID` (`Voter_ID`),
  KEY `Points` (`Points`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Folders`
--

CREATE TABLE IF NOT EXISTS `Folders` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL,
  `Folder_nr` tinyint(4) NOT NULL default '0',
  `Name` char(40) NOT NULL,
  `BGColor` char(8) NOT NULL default 'f7f5e3FF',
  `FGColor` char(6) NOT NULL default '000000',
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`,`Folder_nr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Forumlog`
--

CREATE TABLE IF NOT EXISTS `Forumlog` (
  `ID` int(11) NOT NULL auto_increment,
  `User_ID` int(11) NOT NULL,
  `Thread_ID` int(11) NOT NULL,
  `Post_ID` int(11) NOT NULL,
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `Action` varchar(40) NOT NULL,
  `IP` varchar(16) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  KEY `User_ID` (`User_ID`),
  KEY `Time` (`Time`),
  KEY `Action` (`Action`(4))
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Forumreads`
--

CREATE TABLE IF NOT EXISTS `Forumreads` (
  `User_ID` int(11) NOT NULL default '0',
  `Forum_ID` smallint(6) NOT NULL default '0',
  `Thread_ID` int(11) NOT NULL default '0',
  `Time` datetime NOT NULL,
  `HasNew` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`Thread_ID`,`User_ID`,`Forum_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Forums`
--

CREATE TABLE IF NOT EXISTS `Forums` (
  `ID` smallint(6) NOT NULL auto_increment,
  `Name` char(40) NOT NULL default '',
  `Description` char(128) NOT NULL default '',
  `SortOrder` tinyint(3) unsigned NOT NULL default '0',
  `Options` int(11) unsigned NOT NULL default '0',
  `LastPost` int(11) NOT NULL default '0',
  `Updated` datetime NOT NULL default '0000-00-00 00:00:00',
  `ThreadsInForum` mediumint(8) unsigned NOT NULL default '0',
  `PostsInForum` mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (`ID`),
  KEY `SortOrder` (`SortOrder`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `GamePlayers`
--

CREATE TABLE IF NOT EXISTS `GamePlayers` (
  `ID` int(11) NOT NULL auto_increment,
  `gid` int(11) NOT NULL,
  `GroupColor` enum('B','W','G1','G2','BW') NOT NULL default 'BW',
  `GroupOrder` tinyint(4) NOT NULL default '0',
  `uid` int(11) NOT NULL default '0',
  `Flags` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`ID`),
  KEY `gidGroup` (`gid`,`GroupColor`,`GroupOrder`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Games`
--

CREATE TABLE IF NOT EXISTS `Games` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL default '0',
  `ShapeID` int(10) unsigned NOT NULL default '0',
  `Starttime` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `mid` int(11) NOT NULL default '0',
  `DoubleGame_ID` int(11) NOT NULL default '0',
  `Black_ID` int(11) NOT NULL,
  `White_ID` int(11) NOT NULL,
  `ToMove_ID` int(11) NOT NULL default '0',
  `GameType` enum('GO','TEAM_GO','ZEN_GO') NOT NULL default 'GO',
  `GamePlayers` char(5) NOT NULL default '',
  `Ruleset` enum('JAPANESE','CHINESE') NOT NULL default 'JAPANESE',
  `Size` tinyint(3) unsigned NOT NULL default '19',
  `Komi` decimal(4,1) NOT NULL default '6.5',
  `Handicap` tinyint(3) unsigned NOT NULL default '0',
  `Status` enum('KOMI','SETUP','INVITED','PLAY','PASS','SCORE','SCORE2','FINISHED') NOT NULL default 'INVITED',
  `Moves` smallint(5) unsigned NOT NULL default '0',
  `Black_Prisoners` smallint(5) unsigned NOT NULL default '0',
  `White_Prisoners` smallint(5) unsigned NOT NULL default '0',
  `Last_X` tinyint(4) NOT NULL default '-1',
  `Last_Y` tinyint(4) NOT NULL default '-1',
  `Last_Move` char(2) NOT NULL default '',
  `Flags` set('Ko','HiddenMsg','AdmResult','TGDetached') NOT NULL default '',
  `Score` decimal(5,1) NOT NULL default '0.0',
  `Maintime` smallint(6) NOT NULL default '0',
  `Byotype` enum('JAP','CAN','FIS') NOT NULL default 'JAP',
  `Byotime` smallint(6) NOT NULL default '0',
  `Byoperiods` tinyint(4) NOT NULL default '0',
  `Black_Maintime` smallint(6) NOT NULL default '0',
  `White_Maintime` smallint(6) NOT NULL default '0',
  `Black_Byotime` smallint(6) NOT NULL default '0',
  `White_Byotime` smallint(6) NOT NULL default '0',
  `Black_Byoperiods` tinyint(4) NOT NULL default '-1',
  `White_Byoperiods` tinyint(4) NOT NULL default '-1',
  `LastTicks` int(11) NOT NULL default '0',
  `ClockUsed` smallint(6) NOT NULL default '0',
  `TimeOutDate` int(11) NOT NULL default '0',
  `Rated` enum('N','Y','Done') NOT NULL default 'N',
  `StdHandicap` enum('N','Y') NOT NULL default 'N',
  `WeekendClock` enum('N','Y') NOT NULL default 'Y',
  `Black_Start_Rating` double NOT NULL default '-9999',
  `White_Start_Rating` double NOT NULL default '-9999',
  `Black_End_Rating` double NOT NULL default '-9999',
  `White_End_Rating` double NOT NULL default '-9999',
  `Snapshot` varchar(216) NOT NULL default '',
  `ShapeSnapshot` varchar(255) NOT NULL default '',
  `GameSetup` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  KEY `ToMove_ID` (`ToMove_ID`),
  KEY `Size` (`Size`),
  KEY `Lastchanged` (`Lastchanged`),
  KEY `Status` (`Status`),
  KEY `ClockUsed` (`ClockUsed`),
  KEY `Black_ID` (`Black_ID`),
  KEY `White_ID` (`White_ID`),
  KEY `Handicap` (`Handicap`),
  KEY `Moves` (`Moves`),
  KEY `tid` (`tid`),
  KEY `Score` (`Score`),
  KEY `Flags` (`Flags`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `GamesNotes`
--

CREATE TABLE IF NOT EXISTS `GamesNotes` (
  `gid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `Hidden` enum('N','Y') NOT NULL default 'N',
  `Notes` text NOT NULL,
  PRIMARY KEY  (`gid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `GamesPriority`
--

CREATE TABLE IF NOT EXISTS `GamesPriority` (
  `gid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `Priority` smallint(6) NOT NULL default '0',
  PRIMARY KEY  (`gid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `GoDiagrams`
--

CREATE TABLE IF NOT EXISTS `GoDiagrams` (
  `ID` int(11) NOT NULL auto_increment,
  `Size` tinyint(3) unsigned NOT NULL,
  `View_Left` tinyint(4) NOT NULL default '0',
  `View_Right` tinyint(4) NOT NULL default '0',
  `View_Up` tinyint(4) NOT NULL default '0',
  `View_Down` tinyint(4) NOT NULL default '0',
  `Date` datetime NOT NULL default '0000-00-00 00:00:00',
  `Saved` enum('Y','N') NOT NULL default 'N',
  `Data` text NOT NULL,
  `SGF` text NOT NULL,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Intro`
--

CREATE TABLE IF NOT EXISTS `Intro` (
  `ID` int(11) NOT NULL auto_increment,
  `Parent` int(11) NOT NULL default '0',
  `Level` tinyint(3) unsigned NOT NULL default '0',
  `SortOrder` smallint(6) NOT NULL default '0',
  `Question` int(11) NOT NULL default '0',
  `Answer` int(11) NOT NULL default '0',
  `Hidden` enum('N','Y') NOT NULL default 'N',
  `Reference` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  KEY `Parent` (`Parent`),
  KEY `Level` (`Level`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `IpStats`
--

CREATE TABLE IF NOT EXISTS `IpStats` (
  `uid` int(11) NOT NULL default '0',
  `Page` char(4) NOT NULL default '',
  `IP` char(16) NOT NULL,
  `Counter` int(10) unsigned NOT NULL default '0',
  `Created` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`uid`,`Page`,`IP`),
  KEY `Created` (`Created`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Links`
--

CREATE TABLE IF NOT EXISTS `Links` (
  `ID` int(11) NOT NULL auto_increment,
  `Parent` int(11) NOT NULL default '0',
  `Level` tinyint(3) unsigned NOT NULL default '0',
  `SortOrder` smallint(6) NOT NULL default '0',
  `Question` int(11) NOT NULL default '0',
  `Answer` int(11) NOT NULL default '0',
  `Hidden` enum('N','Y') NOT NULL default 'N',
  `Reference` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  KEY `Parent` (`Parent`),
  KEY `Level` (`Level`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `MessageCorrespondents`
--

CREATE TABLE IF NOT EXISTS `MessageCorrespondents` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `mid` int(11) NOT NULL default '0',
  `Folder_nr` tinyint(4) NOT NULL,
  `Sender` enum('M','N','Y','S') NOT NULL default 'Y',
  `Replied` enum('M','N','Y') NOT NULL default 'N',
  PRIMARY KEY  (`ID`),
  KEY `mid` (`mid`),
  KEY `uid` (`uid`),
  KEY `Sender` (`Sender`),
  KEY `Folder_nr` (`Folder_nr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Messages`
--

CREATE TABLE IF NOT EXISTS `Messages` (
  `ID` int(11) NOT NULL auto_increment,
  `Type` enum('NORMAL','INVITATION','DISPUTED','RESULT') NOT NULL default 'NORMAL',
  `Flags` tinyint(3) unsigned NOT NULL default '0',
  `Thread` int(11) NOT NULL default '0',
  `Level` smallint(6) NOT NULL default '0',
  `ReplyTo` int(11) NOT NULL default '0',
  `Game_ID` int(11) NOT NULL default '0',
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `Subject` varchar(80) NOT NULL default '',
  `Text` text NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `ReplyTo` (`ReplyTo`),
  KEY `Thread` (`Thread`),
  KEY `Time` (`Time`),
  FULLTEXT KEY `Subject` (`Subject`,`Text`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `MoveMessages`
--

CREATE TABLE IF NOT EXISTS `MoveMessages` (
  `gid` int(11) NOT NULL,
  `MoveNr` smallint(5) unsigned NOT NULL default '0',
  `Text` text NOT NULL,
  PRIMARY KEY  (`gid`,`MoveNr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `MoveStats`
--

CREATE TABLE IF NOT EXISTS `MoveStats` (
  `uid` int(11) NOT NULL default '0',
  `SlotTime` smallint(5) unsigned NOT NULL default '0',
  `SlotWDay` tinyint(4) NOT NULL default '0',
  `SlotWeek` tinyint(4) NOT NULL default '0',
  `Counter` mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (`uid`,`SlotTime`,`SlotWDay`,`SlotWeek`),
  KEY `SlotWeek` (`SlotWeek`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Moves`
--

CREATE TABLE IF NOT EXISTS `Moves` (
  `ID` int(11) NOT NULL auto_increment,
  `gid` int(11) NOT NULL,
  `MoveNr` smallint(5) unsigned NOT NULL,
  `Stone` tinyint(3) unsigned NOT NULL default '0',
  `PosX` tinyint(4) NOT NULL,
  `PosY` tinyint(4) NOT NULL,
  `Hours` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `gid` (`gid`,`MoveNr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Observers`
--

CREATE TABLE IF NOT EXISTS `Observers` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL,
  `gid` int(11) NOT NULL,
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
  `Type` smallint(5) unsigned NOT NULL default '0',
  `Handle` varchar(16) NOT NULL,
  `Password` varchar(41) NOT NULL,
  `Newpassword` varchar(41) NOT NULL default '',
  `Sessioncode` varchar(41) NOT NULL default '',
  `Sessionexpire` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastaccess` datetime NOT NULL default '0000-00-00 00:00:00',
  `LastQuickAccess` datetime NOT NULL default '0000-00-00 00:00:00',
  `LastMove` datetime NOT NULL default '0000-00-00 00:00:00',
  `Registerdate` date NOT NULL,
  `Hits` int(11) NOT NULL default '0',
  `VaultCnt` smallint(5) unsigned NOT NULL default '0',
  `VaultTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `Moves` int(11) NOT NULL default '0',
  `Activity` int(11) NOT NULL default '15000',
  `Name` varchar(40) NOT NULL default '',
  `Email` varchar(80) NOT NULL default '',
  `Rank` varchar(40) NOT NULL default '',
  `SendEmail` set('ON','MOVE','BOARD','MESSAGE') NOT NULL default '',
  `Notify` enum('NONE','NEXT','NOW','DONE') NOT NULL default 'NONE',
  `NotifyFlags` tinyint(3) unsigned NOT NULL default '0',
  `CountMsgNew` mediumint(9) NOT NULL default '-1',
  `CountFeatNew` smallint(6) NOT NULL default '-1',
  `CountBulletinNew` smallint(6) NOT NULL default '-1',
  `Adminlevel` smallint(5) unsigned NOT NULL default '0',
  `AdminOptions` smallint(5) unsigned NOT NULL default '0',
  `AdminNote` varchar(100) NOT NULL default '',
  `Timezone` varchar(40) NOT NULL default 'GMT',
  `Nightstart` smallint(6) NOT NULL default '22',
  `ClockUsed` smallint(6) NOT NULL default '22',
  `ClockChanged` enum('N','Y') NOT NULL default 'Y',
  `Rating` double default NULL,
  `Rating2` double default NULL,
  `RatingMin` double default NULL,
  `RatingMax` double default NULL,
  `InitialRating` double NOT NULL default '-9999',
  `RatingStatus` enum('NONE','INIT','RATED') NOT NULL default 'NONE',
  `Open` varchar(60) NOT NULL default '',
  `Lang` varchar(20) NOT NULL default 'C',
  `VacationDays` float NOT NULL default '14',
  `OnVacation` float NOT NULL default '0',
  `Running` smallint(5) unsigned NOT NULL default '0',
  `Finished` mediumint(8) unsigned NOT NULL default '0',
  `RatedGames` mediumint(8) unsigned NOT NULL default '0',
  `Won` mediumint(8) unsigned NOT NULL default '0',
  `Lost` mediumint(8) unsigned NOT NULL default '0',
  `GamesMPG` smallint(5) unsigned NOT NULL default '0',
  `Translator` varchar(80) NOT NULL default '',
  `IP` varchar(16) NOT NULL default '',
  `Browser` varchar(150) NOT NULL default '',
  `Country` char(2) NOT NULL default '',
  `BlockReason` text NOT NULL,
  `ForumReadTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `ForumReadNew` tinyint(4) NOT NULL default '0',
  `UserFlags` int(11) NOT NULL default '0',
  `SkinName` varchar(32) NOT NULL default '',
  `MenuDirection` enum('VERTICAL','HORIZONTAL') NOT NULL default 'VERTICAL',
  `TableMaxRows` smallint(5) unsigned NOT NULL default '20',
  `Button` tinyint(4) NOT NULL default '0',
  `UserPicture` varchar(48) NOT NULL default '',
  `NextGameOrder` enum('LASTMOVED','MOVES','PRIO','TIMELEFT') NOT NULL default 'LASTMOVED',
  `SkipBulletin` tinyint(3) unsigned NOT NULL default '4',
  `RejectTimeoutWin` tinyint(4) NOT NULL default '-1',
  PRIMARY KEY  (`ID`),
  UNIQUE KEY `Handle` (`Handle`),
  KEY `Rating2` (`Rating2`),
  KEY `Name` (`Name`),
  KEY `Activity` (`Activity`),
  KEY `Country` (`Country`),
  KEY `Lastaccess` (`Lastaccess`),
  KEY `Adminlevel` (`Adminlevel`),
  KEY `AdminOptions` (`AdminOptions`),
  KEY `Type` (`Type`),
  KEY `CountFeatNew` (`CountFeatNew`),
  KEY `OnVacation` (`OnVacation`),
  KEY `VacationDays` (`VacationDays`),
  KEY `Notify` (`Notify`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Posts`
--

CREATE TABLE IF NOT EXISTS `Posts` (
  `ID` int(11) NOT NULL auto_increment,
  `Forum_ID` smallint(6) NOT NULL default '0',
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastedited` datetime NOT NULL default '0000-00-00 00:00:00',
  `Subject` varchar(80) NOT NULL default '',
  `Text` text NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Parent_ID` int(11) NOT NULL default '0',
  `Thread_ID` int(11) NOT NULL default '0',
  `Flags` tinyint(3) unsigned NOT NULL default '0',
  `AnswerNr` mediumint(8) unsigned NOT NULL default '0',
  `Depth` tinyint(3) unsigned NOT NULL default '0',
  `crc32` int(11) default NULL,
  `PosIndex` varchar(80) character set latin1 collate latin1_bin NOT NULL default '',
  `old_ID` int(11) NOT NULL default '0',
  `Approved` enum('Y','N','P') NOT NULL default 'Y',
  `PostsInThread` mediumint(8) unsigned NOT NULL default '0',
  `Hits` int(11) NOT NULL default '0',
  `LastPost` int(11) NOT NULL default '0',
  PRIMARY KEY  (`ID`),
  KEY `Thread_ID` (`Thread_ID`),
  KEY `PosIndex` (`PosIndex`),
  KEY `Forum_ID` (`Forum_ID`),
  KEY `Lastchanged` (`Lastchanged`),
  KEY `Parent_ID` (`Parent_ID`),
  KEY `Time` (`Time`),
  KEY `Approved` (`Approved`),
  KEY `User_ID` (`User_ID`),
  FULLTEXT KEY `Subject` (`Subject`,`Text`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Profiles`
--

CREATE TABLE IF NOT EXISTS `Profiles` (
  `ID` int(11) NOT NULL auto_increment,
  `User_ID` int(11) NOT NULL,
  `Type` smallint(6) NOT NULL,
  `SortOrder` smallint(6) NOT NULL default '1',
  `Active` enum('Y','N') NOT NULL default 'N',
  `Name` varchar(60) NOT NULL default '',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `Text` blob NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `UserType` (`User_ID`,`Type`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `RatingChangeAdmin`
--

CREATE TABLE IF NOT EXISTS `RatingChangeAdmin` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL,
  `Created` datetime NOT NULL default '0000-00-00 00:00:00',
  `Changes` tinyint(4) NOT NULL default '0',
  `Rating` double NOT NULL default '-9999',
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`),
  KEY `Created` (`Created`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Ratinglog`
--

CREATE TABLE IF NOT EXISTS `Ratinglog` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL,
  `gid` int(11) NOT NULL,
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `Rating` double NOT NULL,
  `RatingMin` double NOT NULL,
  `RatingMax` double NOT NULL,
  `RatingDiff` float NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `gid` (`gid`),
  KEY `UserTime` (`uid`,`Time`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Shape`
--

CREATE TABLE IF NOT EXISTS `Shape` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL,
  `Name` varchar(40) NOT NULL default '',
  `Size` tinyint(3) unsigned NOT NULL,
  `Flags` tinyint(3) unsigned NOT NULL default '0',
  `Snapshot` varchar(216) NOT NULL default '',
  `Notes` text NOT NULL,
  `Created` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`ID`),
  UNIQUE KEY `Name` (`Name`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Statistics`
--

CREATE TABLE IF NOT EXISTS `Statistics` (
  `ID` int(11) NOT NULL auto_increment,
  `Time` datetime NOT NULL,
  `Hits` int(11) NOT NULL,
  `Users` int(11) NOT NULL,
  `Moves` int(11) NOT NULL,
  `MovesFinished` int(11) NOT NULL,
  `MovesRunning` int(11) NOT NULL,
  `Games` int(11) NOT NULL,
  `GamesFinished` int(11) NOT NULL,
  `GamesRunning` int(11) NOT NULL,
  `Activity` int(11) NOT NULL,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Survey`
--

CREATE TABLE IF NOT EXISTS `Survey` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL,
  `Type` enum('POINTS','SUM','SINGLE','MULTI') NOT NULL default 'POINTS',
  `Status` enum('NEW','ACTIVE','CLOSED','DELETE') NOT NULL default 'NEW',
  `Flags` tinyint(3) unsigned NOT NULL default '0',
  `MinPoints` tinyint(4) NOT NULL default '0',
  `MaxPoints` tinyint(4) NOT NULL default '0',
  `UserCount` mediumint(8) unsigned NOT NULL default '0',
  `Created` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `Title` varchar(255) NOT NULL,
  `Header` text NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`),
  KEY `Status` (`Status`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `SurveyOption`
--

CREATE TABLE IF NOT EXISTS `SurveyOption` (
  `ID` int(11) NOT NULL auto_increment,
  `sid` int(11) NOT NULL default '0',
  `Tag` tinyint(3) unsigned NOT NULL default '0',
  `SortOrder` tinyint(3) unsigned NOT NULL default '0',
  `MinPoints` tinyint(4) NOT NULL default '0',
  `Score` int(11) NOT NULL default '0',
  `Title` varchar(255) NOT NULL default '',
  `Text` text NOT NULL,
  PRIMARY KEY  (`ID`),
  UNIQUE KEY `sidTag` (`sid`,`Tag`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `SurveyUser`
--

CREATE TABLE IF NOT EXISTS `SurveyUser` (
  `sid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  PRIMARY KEY  (`sid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `SurveyVote`
--

CREATE TABLE IF NOT EXISTS `SurveyVote` (
  `soid` int(11) NOT NULL,
  `uid` int(11) NOT NULL default '0',
  `Points` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`soid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Tournament`
--

CREATE TABLE IF NOT EXISTS `Tournament` (
  `ID` int(11) NOT NULL auto_increment,
  `Scope` enum('DRAGON','PUBLIC','PRIVATE') NOT NULL default 'PUBLIC',
  `Type` enum('LADDER','ROUNDROBIN') NOT NULL,
  `WizardType` tinyint(4) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text NOT NULL,
  `Owner_ID` int(11) NOT NULL,
  `Status` enum('ADM','NEW','REG','PAIR','PLAY','CLOSED','DEL') NOT NULL default 'NEW',
  `Flags` smallint(5) unsigned NOT NULL default '0',
  `Created` datetime NOT NULL,
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `ChangedBy` varchar(54) NOT NULL default '',
  `StartTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `EndTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `Rounds` tinyint(3) unsigned NOT NULL default '1',
  `CurrentRound` tinyint(3) unsigned NOT NULL default '1',
  `RegisteredTP` smallint(6) NOT NULL default '0',
  `LockNote` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  KEY `Status` (`Status`),
  KEY `StartTime` (`StartTime`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentDirector`
--

CREATE TABLE IF NOT EXISTS `TournamentDirector` (
  `tid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `Flags` smallint(5) unsigned NOT NULL default '0',
  `Comment` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`tid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentExtension`
--

CREATE TABLE IF NOT EXISTS `TournamentExtension` (
  `tid` int(11) NOT NULL,
  `Property` smallint(5) unsigned NOT NULL,
  `IntValue` int(11) NOT NULL default '0',
  `DateValue` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `ChangedBy` char(54) NOT NULL default '',
  PRIMARY KEY  (`tid`,`Property`),
  KEY `DateValue` (`DateValue`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentGames`
--

CREATE TABLE IF NOT EXISTS `TournamentGames` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `Round_ID` int(11) NOT NULL default '0',
  `Pool` smallint(5) unsigned NOT NULL default '0',
  `gid` int(11) NOT NULL default '0',
  `Status` enum('INIT','PLAY','SCORE','WAIT','DONE') NOT NULL default 'INIT',
  `TicksDue` int(11) NOT NULL default '0',
  `Flags` smallint(5) unsigned NOT NULL default '0',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `Challenger_uid` int(11) NOT NULL,
  `Challenger_rid` int(11) NOT NULL,
  `Defender_uid` int(11) NOT NULL,
  `Defender_rid` int(11) NOT NULL,
  `StartTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `EndTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `Score` decimal(5,1) NOT NULL default '0.0',
  PRIMARY KEY  (`ID`),
  KEY `Challenger_uid` (`Challenger_uid`),
  KEY `Defender_uid` (`Defender_uid`),
  KEY `gid` (`gid`),
  KEY `Status_Ticks` (`Status`,`TicksDue`),
  KEY `tidRoundPool` (`tid`,`Round_ID`,`Pool`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentLadder`
--

CREATE TABLE IF NOT EXISTS `TournamentLadder` (
  `tid` int(11) NOT NULL,
  `rid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `Created` datetime NOT NULL default '0000-00-00 00:00:00',
  `RankChanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `Rank` smallint(5) unsigned NOT NULL default '0',
  `BestRank` smallint(5) unsigned NOT NULL default '0',
  `StartRank` smallint(5) unsigned NOT NULL default '0',
  `PeriodRank` smallint(5) unsigned NOT NULL default '0',
  `HistoryRank` smallint(5) unsigned NOT NULL default '0',
  `ChallengesIn` tinyint(3) unsigned NOT NULL default '0',
  `ChallengesOut` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`tid`,`rid`),
  KEY `uid` (`uid`),
  KEY `Rank` (`tid`,`Rank`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentLadderProps`
--

CREATE TABLE IF NOT EXISTS `TournamentLadderProps` (
  `tid` int(11) NOT NULL,
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `ChangedBy` char(54) NOT NULL default '',
  `ChallengeRangeAbsolute` smallint(6) NOT NULL default '0',
  `ChallengeRangeRelative` tinyint(3) unsigned NOT NULL default '0',
  `ChallengeRangeRating` smallint(6) NOT NULL default '-32768',
  `ChallengeRematchWait` smallint(5) unsigned NOT NULL default '0',
  `MaxDefenses` tinyint(3) unsigned NOT NULL,
  `MaxDefenses1` tinyint(3) unsigned NOT NULL default '0',
  `MaxDefenses2` tinyint(3) unsigned NOT NULL default '0',
  `MaxDefensesStart1` tinyint(3) unsigned NOT NULL default '0',
  `MaxDefensesStart2` tinyint(3) unsigned NOT NULL default '0',
  `MaxChallenges` tinyint(3) unsigned NOT NULL default '0',
  `GameEndNormal` enum('CH_ABOVE','CH_BELOW','SWITCH','DF_BELOW','DF_LAST') NOT NULL default 'CH_ABOVE',
  `GameEndJigo` enum('NO_CHANGE','CH_ABOVE','CH_BELOW') NOT NULL default 'CH_BELOW',
  `GameEndTimeoutWin` enum('NO_CHANGE','CH_ABOVE','CH_BELOW','SWITCH','DF_BELOW','DF_LAST','DF_DEL') NOT NULL default 'DF_BELOW',
  `GameEndTimeoutLoss` enum('NO_CHANGE','CH_LAST','CH_DEL') NOT NULL default 'CH_LAST',
  `UserJoinOrder` enum('REGTIME','RATING','RANDOM') NOT NULL default 'REGTIME',
  `UserAbsenceDays` tinyint(3) unsigned NOT NULL default '0',
  `RankPeriodLength` tinyint(3) unsigned NOT NULL default '1',
  `CrownKingHours` smallint(5) unsigned NOT NULL default '0',
  `CrownKingStart` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`tid`),
  KEY `UserAbsenceDays` (`UserAbsenceDays`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Tournamentlog`
--

CREATE TABLE `Tournamentlog` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `Date` datetime NOT NULL default '0000-00-00 00:00:00',
  `Type` char(2) NOT NULL,
  `Object` varchar(16) NOT NULL default 'T',
  `Action` varchar(16) NOT NULL,
  `actuid` int(11) NOT NULL default '0',
  `Message` text NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `tid` (`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentNews`
--

CREATE TABLE IF NOT EXISTS `TournamentNews` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `Status` enum('NEW','SHOW','ARCHIVE','DELETE') NOT NULL default 'NEW',
  `Flags` tinyint(3) unsigned NOT NULL default '0',
  `Published` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `ChangedBy` varchar(54) NOT NULL default '',
  `Subject` varchar(255) NOT NULL,
  `Text` text NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `tidPublished` (`tid`,`Published`),
  KEY `Status` (`Status`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentParticipant`
--

CREATE TABLE IF NOT EXISTS `TournamentParticipant` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `Status` enum('APPLY','REGISTER','INVITE') NOT NULL default 'APPLY',
  `Flags` smallint(5) unsigned NOT NULL default '0',
  `Rating` double NOT NULL default '-9999',
  `StartRound` tinyint(3) unsigned NOT NULL default '1',
  `NextRound` tinyint(3) unsigned NOT NULL default '0',
  `Created` datetime NOT NULL default '0000-00-00 00:00:00',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `ChangedBy` varchar(54) NOT NULL default '',
  `Comment` varchar(60) NOT NULL default '',
  `Notes` text NOT NULL,
  `UserMessage` text NOT NULL,
  `AdminMessage` text NOT NULL,
  `Finished` mediumint(8) unsigned NOT NULL default '0',
  `Won` mediumint(8) unsigned NOT NULL default '0',
  `Lost` mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY  (`ID`),
  KEY `uid` (`uid`),
  KEY `tid_status` (`tid`,`Status`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentPool`
--

CREATE TABLE IF NOT EXISTS `TournamentPool` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `Round` tinyint(3) unsigned NOT NULL,
  `Pool` smallint(5) unsigned NOT NULL,
  `uid` int(11) NOT NULL default '0',
  `Rank` tinyint(4) NOT NULL default '-100',
  PRIMARY KEY  (`ID`),
  KEY `tidRoundPool` (`tid`,`Round`,`Pool`),
  KEY `uid` (`uid`),
  KEY `Rank` (`Rank`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentProperties`
--

CREATE TABLE IF NOT EXISTS `TournamentProperties` (
  `tid` int(11) NOT NULL,
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `ChangedBy` char(54) NOT NULL default '',
  `MinParticipants` smallint(6) NOT NULL default '2',
  `MaxParticipants` smallint(6) NOT NULL default '0',
  `RatingUseMode` enum('COPY_CUSTOM','CURR_FIX','COPY_FIX') NOT NULL default 'COPY_CUSTOM',
  `RegisterEndTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `UserMinRating` smallint(6) NOT NULL default '-9999',
  `UserMaxRating` smallint(6) NOT NULL default '-9999',
  `UserRated` enum('N','Y') NOT NULL default 'N',
  `UserMinGamesFinished` smallint(6) NOT NULL default '0',
  `UserMinGamesRated` smallint(6) NOT NULL default '0',
  `Notes` text NOT NULL,
  PRIMARY KEY  (`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentResult`
--

CREATE TABLE IF NOT EXISTS `TournamentResult` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `rid` int(11) NOT NULL default '0',
  `Rating` double NOT NULL default '-9999',
  `Type` tinyint(3) unsigned NOT NULL default '0',
  `StartTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `EndTime` datetime NOT NULL default '0000-00-00 00:00:00',
  `Round` tinyint(3) unsigned NOT NULL default '1',
  `Rank` smallint(5) unsigned NOT NULL default '0',
  `RankKept` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`ID`),
  KEY `tidRank` (`tid`,`Rank`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentRound`
--

CREATE TABLE IF NOT EXISTS `TournamentRound` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `Round` tinyint(3) unsigned NOT NULL default '1',
  `Status` enum('INIT','POOL','PAIR','PLAY','DONE') NOT NULL default 'INIT',
  `MinPoolSize` tinyint(3) unsigned NOT NULL default '0',
  `MaxPoolSize` tinyint(3) unsigned NOT NULL default '0',
  `MaxPoolCount` smallint(5) unsigned NOT NULL default '0',
  `Pools` smallint(5) unsigned NOT NULL default '0',
  `PoolSize` tinyint(3) unsigned NOT NULL default '0',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `ChangedBy` char(54) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  UNIQUE KEY `tidRound` (`tid`,`Round`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentRules`
--

CREATE TABLE IF NOT EXISTS `TournamentRules` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `ShapeID` int(10) unsigned NOT NULL default '0',
  `Lastchanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `ChangedBy` varchar(54) NOT NULL default '',
  `Flags` smallint(5) unsigned NOT NULL default '0',
  `Ruleset` enum('JAPANESE','CHINESE') NOT NULL default 'JAPANESE',
  `Size` tinyint(3) unsigned NOT NULL default '19',
  `Handicaptype` enum('CONV','PROPER','NIGIRI','DOUBLE','BLACK','WHITE') NOT NULL default 'CONV',
  `AdjKomi` decimal(4,1) NOT NULL default '0.0',
  `JigoMode` enum('KEEP_KOMI','ALLOW_JIGO','NO_JIGO') NOT NULL default 'KEEP_KOMI',
  `Handicap` tinyint(3) unsigned NOT NULL default '0',
  `Komi` decimal(4,1) NOT NULL default '6.5',
  `AdjHandicap` tinyint(4) NOT NULL default '0',
  `MinHandicap` tinyint(3) unsigned NOT NULL default '0',
  `MaxHandicap` tinyint(3) unsigned NOT NULL default '127',
  `StdHandicap` enum('N','Y') NOT NULL default 'N',
  `Maintime` smallint(6) NOT NULL default '0',
  `Byotype` enum('JAP','CAN','FIS') NOT NULL default 'JAP',
  `Byotime` smallint(6) NOT NULL default '0',
  `Byoperiods` tinyint(4) NOT NULL default '0',
  `WeekendClock` enum('N','Y') NOT NULL default 'Y',
  `Rated` enum('N','Y') NOT NULL default 'N',
  `ShapeSnapshot` varchar(255) NOT NULL default '',
  `Notes` text NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `tid` (`tid`),
  KEY `Size` (`Size`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationFoundInGroup`
--

CREATE TABLE IF NOT EXISTS `TranslationFoundInGroup` (
  `Text_ID` int(11) NOT NULL,
  `Group_ID` int(11) NOT NULL,
  PRIMARY KEY  (`Text_ID`,`Group_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationGroups`
--

CREATE TABLE IF NOT EXISTS `TranslationGroups` (
  `ID` int(11) NOT NULL auto_increment,
  `Groupname` char(32) NOT NULL,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationLanguages`
--

CREATE TABLE IF NOT EXISTS `TranslationLanguages` (
  `ID` int(11) NOT NULL auto_increment,
  `Language` char(32) NOT NULL,
  `Name` char(32) NOT NULL,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationPages`
--

CREATE TABLE IF NOT EXISTS `TranslationPages` (
  `Page` char(64) NOT NULL,
  `Group_ID` int(11) NOT NULL,
  PRIMARY KEY  (`Page`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationTexts`
--

CREATE TABLE IF NOT EXISTS `TranslationTexts` (
  `ID` int(11) NOT NULL auto_increment,
  `Text` text NOT NULL,
  `Type` enum('NONE','FAQ','LINKS','INTRO','SRC') NOT NULL default 'NONE',
  `Translatable` enum('Y','N','Done','Changed') NOT NULL default 'Y',
  `Status` enum('USED','CHECK','ORPHAN') NOT NULL default 'USED',
  `Updated` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`ID`),
  KEY `Text` (`Text`(4))
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Translationlog`
--

CREATE TABLE IF NOT EXISTS `Translationlog` (
  `ID` int(11) NOT NULL auto_increment,
  `Player_ID` int(11) NOT NULL,
  `Language_ID` int(11) NOT NULL,
  `Original_ID` int(11) NOT NULL default '0',
  `OldTranslation` blob NOT NULL,
  `Translation` blob NOT NULL,
  `Date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`ID`),
  KEY `PlayerLang` (`Player_ID`,`Language_ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Translations`
--

CREATE TABLE IF NOT EXISTS `Translations` (
  `Original_ID` int(11) NOT NULL,
  `Language_ID` int(11) NOT NULL,
  `Text` blob NOT NULL,
  `Translated` enum('Y','N') NOT NULL default 'N',
  `Updated` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`Language_ID`,`Original_ID`),
  KEY `Original_ID` (`Original_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `UserQuota`
--

CREATE TABLE IF NOT EXISTS `UserQuota` (
  `uid` int(11) NOT NULL,
  `FeaturePoints` smallint(5) NOT NULL default '25',
  `FeaturePointsUpdated` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`uid`),
  KEY `FeaturePointsUpdated` (`FeaturePointsUpdated`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `Waitingroom`
--

CREATE TABLE IF NOT EXISTS `Waitingroom` (
  `ID` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL,
  `gid` int(11) NOT NULL default '0',
  `ShapeID` int(10) unsigned NOT NULL default '0',
  `nrGames` tinyint(3) unsigned NOT NULL default '1',
  `Time` datetime NOT NULL default '0000-00-00 00:00:00',
  `GameType` enum('GO','TEAM_GO','ZEN_GO') NOT NULL default 'GO',
  `GamePlayers` char(5) NOT NULL default '',
  `Ruleset` enum('JAPANESE','CHINESE') NOT NULL default 'JAPANESE',
  `Size` tinyint(3) unsigned NOT NULL default '19',
  `Komi` decimal(4,1) NOT NULL default '6.5',
  `Handicap` tinyint(3) unsigned NOT NULL default '0',
  `Handicaptype` enum('conv','proper','nigiri','double','black','white','auko_sec','auko_opn','div_ykic','div_ikyc') NOT NULL default 'conv',
  `AdjKomi` decimal(4,1) NOT NULL default '0.0',
  `JigoMode` enum('KEEP_KOMI','ALLOW_JIGO','NO_JIGO') NOT NULL default 'KEEP_KOMI',
  `AdjHandicap` tinyint(4) NOT NULL default '0',
  `MinHandicap` tinyint(3) unsigned NOT NULL default '0',
  `MaxHandicap` tinyint(3) unsigned NOT NULL default '127',
  `Maintime` smallint(6) NOT NULL default '0',
  `Byotype` enum('JAP','CAN','FIS') NOT NULL default 'JAP',
  `Byotime` smallint(6) NOT NULL default '0',
  `Byoperiods` tinyint(4) NOT NULL default '0',
  `Rated` enum('N','Y') NOT NULL default 'N',
  `StdHandicap` enum('N','Y') NOT NULL default 'N',
  `WeekendClock` enum('N','Y') NOT NULL default 'Y',
  `MustBeRated` enum('N','Y') NOT NULL default 'N',
  `RatingMin` smallint(6) NOT NULL default '-9999',
  `RatingMax` smallint(6) NOT NULL default '-9999',
  `MinRatedGames` smallint(6) NOT NULL default '0',
  `SameOpponent` tinyint(4) NOT NULL default '0',
  `ShapeSnapshot` varchar(255) NOT NULL default '',
  `Comment` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`ID`),
  KEY `Handicaptype` (`Handicaptype`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `WaitingroomJoined`
--

CREATE TABLE IF NOT EXISTS `WaitingroomJoined` (
  `opp_id` int(11) NOT NULL,
  `wroom_id` int(11) NOT NULL,
  `JoinedCount` tinyint(4) NOT NULL default '0',
  `ExpireDate` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`wroom_id`,`opp_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

